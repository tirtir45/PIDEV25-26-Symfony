<?php

namespace App\Controller;

use App\Entity\Reservations;
use App\Repository\EvenementsRepository;
use App\Repository\ReservationsRepository;
use App\Repository\UtilisateursRepository;
use App\Service\FacturePdfService;
use App\Service\ReservationMailerService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenements')]
class UserEvenementController extends AbstractController
{
    private function requireAuth(Request $request): ?Response
    {
        if (!$request->getSession()->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    private function getSessionUser(Request $request, UtilisateursRepository $repo): ?\App\Entity\Utilisateurs
    {
        $id = $request->getSession()->get('user_id');
        return $id ? $repo->find($id) : null;
    }

    #[Route('/', name: 'user_evenements', methods: ['GET'])]
    public function index(EvenementsRepository $repo, ReservationsRepository $reservationRepo, UtilisateursRepository $userRepo, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $search     = $request->query->get('search', '');
        $sort       = $request->query->get('sort', 'date_asc');
        $prixMinRaw = $request->query->get('prix_min');
        $prixMaxRaw = $request->query->get('prix_max');
        $prixMin    = ($prixMinRaw !== null && $prixMinRaw !== '') ? (float) $prixMinRaw : null;
        $prixMax    = ($prixMaxRaw !== null && $prixMaxRaw !== '') ? (float) $prixMaxRaw : null;
        $evenements = $repo->findWithFilters($search ?: null, $sort, $prixMin, $prixMax);

        $user        = $this->getSessionUser($request, $userRepo);
        $reservedIds = [];
        if ($user) {
            foreach ($reservationRepo->findByUtilisateur($user->getId()) as $res) {
                if ($res->getEvenement()) {
                    $reservedIds[] = $res->getEvenement()->getId_evenement();
                }
            }
        }

        return $this->render('evenement/user_evenements.html.twig', [
            'evenements'   => $evenements,
            'search'       => $search,
            'sort'         => $sort,
            'prix_min'     => $prixMin,
            'prix_max'     => $prixMax,
            'reserved_ids' => $reservedIds,
        ]);
    }

    #[Route('/mes-reservations', name: 'user_mes_reservations', methods: ['GET'])]
    public function mesReservations(ReservationsRepository $repo, UtilisateursRepository $userRepo, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $user    = $this->getSessionUser($request, $userRepo);
        $statut  = $request->query->get('statut', '');
        $periode = $request->query->get('periode', '');
        $now     = new \DateTime();

        $all = $user ? $repo->findByUtilisateur($user->getId()) : [];

        $reservations = array_filter($all, function (Reservations $r) use ($statut, $periode, $now) {
            if ($statut !== '' && $r->getStatutPaiement() !== $statut) return false;
            if ($periode === 'futur' && !($r->getEvenement()?->getDate_evenement() > $now)) return false;
            if ($periode === 'passe' && !($r->getEvenement()?->getDate_evenement() <= $now)) return false;
            return true;
        });

        return $this->render('evenement/mes_reservations.html.twig', [
            'reservations' => array_values($reservations),
            'total'        => count($all),
            'utilisateur'  => $user,
            'statut'       => $statut,
            'periode'      => $periode,
        ]);
    }

    #[Route('/facture/{id}', name: 'user_facture', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function facture(int $id, ReservationsRepository $repo, UtilisateursRepository $userRepo, FacturePdfService $pdf, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $user        = $this->getSessionUser($request, $userRepo);
        $reservation = $repo->find($id);

        if (!$reservation || !$user || $reservation->getUtilisateur()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        $filename = sprintf('billet_starthub_%d.pdf', $reservation->getId_reservation());
        return new Response($pdf->generatePdf($reservation), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/verify/{token}', name: 'user_reservation_verify', methods: ['GET'])]
    public function verify(string $token, ReservationsRepository $repo, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $isAdmin = $request->getSession()->get('user_role') === 'Administrateur';

        if (!$isAdmin) {
            return $this->render('evenement/verify.html.twig', [
                'admin_required' => true,
                'valid'          => false,
                'reservation'    => null,
                'evenement'      => null,
                'utilisateur'    => null,
            ]);
        }

        $res = $repo->findOneBy(['tokenVerification' => $token]);
        return $this->render('evenement/verify.html.twig', [
            'admin_required' => false,
            'valid'          => $res !== null,
            'reservation'    => $res,
            'evenement'      => $res?->getEvenement(),
            'utilisateur'    => $res?->getUtilisateur(),
        ]);
    }

    #[Route('/{id}/checkout', name: 'user_checkout', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function checkout(int $id, EvenementsRepository $evenementRepo, ReservationsRepository $reservationRepo, UtilisateursRepository $userRepo, EntityManagerInterface $em, StripeService $stripe, ReservationMailerService $mailer, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        if (!$this->isCsrfTokenValid('checkout' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('user_evenements');
        }

        $user      = $this->getSessionUser($request, $userRepo);
        $evenement = $evenementRepo->find($id);

        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('user_evenements');
        }
        if (!$user || !$user->isActif()) {
            $this->addFlash('error', 'Votre compte est désactivé. Contactez l\'administrateur.');
            return $this->redirectToRoute('user_evenements');
        }
        if ($evenement->getCapacite() <= 0) {
            $this->addFlash('error', 'Cet événement est complet.');
            return $this->redirectToRoute('user_evenements');
        }
        if ($reservationRepo->existeDeja($evenement->getId_evenement(), $user->getId())) {
            $this->addFlash('warning', 'Vous avez déjà une réservation pour cet événement.');
            return $this->redirectToRoute('user_evenements');
        }

        $reservation = new Reservations();
        $reservation->setEvenement($evenement);
        $reservation->setUtilisateur($user);
        $reservation->setStatutPaiement($evenement->getPrix() > 0 ? 'pending' : 'free');
        $evenement->setCapacite($evenement->getCapacite() - 1);
        $em->persist($reservation);
        $em->flush();

        if ($evenement->getPrix() <= 0) {
            try { $mailer->sendConfirmation($reservation); } catch (\Throwable) {}
            $this->addFlash('success', 'Réservation confirmée ! Votre billet PDF a été envoyé par email.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        return $this->launchStripe($reservation, $evenement, $stripe, $em);
    }

    #[Route('/payer/{id}', name: 'user_pay_pending', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function payPending(int $id, ReservationsRepository $repo, UtilisateursRepository $userRepo, EntityManagerInterface $em, StripeService $stripe, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        if (!$this->isCsrfTokenValid('payer' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        $user        = $this->getSessionUser($request, $userRepo);
        $reservation = $repo->find($id);

        if (!$reservation || !$user || $reservation->getUtilisateur()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('user_mes_reservations');
        }
        if ($reservation->getStatutPaiement() !== 'pending') {
            $this->addFlash('warning', 'Cette réservation est déjà confirmée.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        $evenement = $reservation->getEvenement();
        if (!$evenement || $evenement->getDate_evenement() <= new \DateTime()) {
            $this->addFlash('error', 'Impossible de payer : l\'événement est déjà passé.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        return $this->launchStripe($reservation, $evenement, $stripe, $em);
    }

    private function launchStripe(Reservations $reservation, $evenement, StripeService $stripe, EntityManagerInterface $em): Response
    {
        try {
            $successUrl = $this->generateUrl('user_payment_success', [], 0)
                . '?session_id={CHECKOUT_SESSION_ID}&res_id=' . $reservation->getId_reservation();
            $cancelUrl  = $this->generateUrl('user_payment_cancel', ['res_id' => $reservation->getId_reservation()], 0);

            $session = $stripe->createCheckoutSession($evenement, $reservation->getId_reservation(), $successUrl, $cancelUrl);
            $reservation->setStripePaymentId($session->id);
            $em->flush();
            return $this->redirect($session->url);
        } catch (\Throwable $t) {
            $this->addFlash('error', 'Erreur Stripe : ' . $t->getMessage());
            return $this->redirectToRoute('user_mes_reservations');
        }
    }

    #[Route('/payment/success', name: 'user_payment_success', methods: ['GET'])]
    public function paymentSuccess(Request $request, ReservationsRepository $repo, StripeService $stripe, ReservationMailerService $mailer, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $sessionId   = $request->query->get('session_id');
        $resId       = (int) $request->query->get('res_id');
        $reservation = $repo->find($resId);

        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('user_evenements');
        }

        try {
            $session = $stripe->retrieveSession($sessionId);
            if ($session->payment_status === 'paid') {
                $reservation->setStatutPaiement('paid');
                $reservation->setStripePaymentId($sessionId);
                $em->flush();
                try { $mailer->sendConfirmation($reservation); } catch (\Throwable) {}
                $this->addFlash('success', 'Paiement confirmé ! Votre billet PDF a été envoyé par email.');
            } else {
                $this->addFlash('warning', 'Paiement en attente de confirmation Stripe.');
            }
        } catch (\Throwable) {
            $this->addFlash('warning', 'Impossible de vérifier le paiement. Contactez le support.');
        }
        return $this->redirectToRoute('user_mes_reservations');
    }

    #[Route('/payment/cancel', name: 'user_payment_cancel', methods: ['GET'])]
    public function paymentCancel(Request $request, ReservationsRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $resId       = (int) $request->query->get('res_id');
        $reservation = $repo->find($resId);

        if ($reservation && $reservation->getStatutPaiement() === 'pending') {
            $evenement = $reservation->getEvenement();
            if ($evenement) $evenement->setCapacite($evenement->getCapacite() + 1);
            $em->remove($reservation);
            $em->flush();
        }

        $this->addFlash('warning', 'Paiement annulé. Votre réservation a été libérée.');
        return $this->redirectToRoute('user_evenements');
    }

    #[Route('/annuler/{id}', name: 'user_annuler_reservation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function annuler(int $id, ReservationsRepository $reservationRepo, UtilisateursRepository $userRepo, EntityManagerInterface $em, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        if (!$this->isCsrfTokenValid('annuler' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        $user        = $this->getSessionUser($request, $userRepo);
        $reservation = $reservationRepo->find($id);

        if (!$reservation || !$user || $reservation->getUtilisateur()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        $wasPaid   = $reservation->getStatutPaiement() === 'paid';
        $evenement = $reservation->getEvenement();
        if ($evenement) $evenement->setCapacite($evenement->getCapacite() + 1);
        $em->remove($reservation);
        $em->flush();

        if ($wasPaid) {
            $this->addFlash('success', 'Réservation annulée. Notre équipe vous contactera dans les 48h pour le remboursement.');
        } else {
            $this->addFlash('success', 'Réservation annulée avec succès.');
        }
        return $this->redirectToRoute('user_mes_reservations');
    }
}

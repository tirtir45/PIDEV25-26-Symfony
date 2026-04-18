<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\EvenementRepository;
use App\Repository\ReservationRepository;
use App\Service\FacturePdfService;
use App\Service\ReservationMailerService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/evenements')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserEvenementController extends AbstractController
{
    // ── Browse ────────────────────────────────────────────────────────────────
    #[Route('/', name: 'user_evenements', methods: ['GET'])]
    public function index(EvenementRepository $repo, ReservationRepository $reservationRepo, Request $request): Response
    {
        $search     = $request->query->get('search', '');
        $sort       = $request->query->get('sort', 'date_asc');
        $prixMinRaw = $request->query->get('prix_min');
        $prixMaxRaw = $request->query->get('prix_max');
        $prixMin    = ($prixMinRaw !== null && $prixMinRaw !== '') ? (float) $prixMinRaw : null;
        $prixMax    = ($prixMaxRaw !== null && $prixMaxRaw !== '') ? (float) $prixMaxRaw : null;
        $evenements = $repo->findWithFilters($search ?: null, $sort, $prixMin, $prixMax);

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $reservedIds = [];
        foreach ($reservationRepo->findByUtilisateur($user->getIdUtilisateur()) as $r) {
            if ($r->getEvenement()) {
                $reservedIds[] = $r->getEvenement()->getIdEvenement();
            }
        }

        return $this->render('user/evenements.html.twig', [
            'evenements'   => $evenements,
            'search'       => $search,
            'sort'         => $sort,
            'prix_min'     => $prixMin,
            'prix_max'     => $prixMax,
            'active'       => 'events',
            'reserved_ids' => $reservedIds,
        ]);
    }

    // ── My reservations ───────────────────────────────────────────────────────
    #[Route('/mes-reservations', name: 'user_mes_reservations', methods: ['GET'])]
    public function mesReservations(ReservationRepository $repo, Request $request): Response
    {
        /** @var Utilisateur $user */
        $user   = $this->getUser();
        $statut = $request->query->get('statut', '');
        $periode = $request->query->get('periode', '');

        $all = $repo->findByUtilisateur($user->getIdUtilisateur());

        // PHP-level filters
        $now = new \DateTime();
        $reservations = array_filter($all, function (Reservation $r) use ($statut, $periode, $now) {
            if ($statut !== '' && $r->getStatutPaiement() !== $statut) {
                return false;
            }
            if ($periode === 'futur' && !($r->getEvenement()?->getDateEvenement() > $now)) {
                return false;
            }
            if ($periode === 'passe' && !($r->getEvenement()?->getDateEvenement() <= $now)) {
                return false;
            }
            return true;
        });

        return $this->render('user/mes_reservations.html.twig', [
            'reservations' => array_values($reservations),
            'total'        => count($all),
            'utilisateur'  => $user,
            'active'       => 'reservations',
            'statut'       => $statut,
            'periode'      => $periode,
        ]);
    }

    // ── Download PDF billet ───────────────────────────────────────────────────
    #[Route('/facture/{id}', name: 'user_facture', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function facture(int $id, ReservationRepository $repo, FacturePdfService $pdf): Response
    {
        /** @var Utilisateur $user */
        $user        = $this->getUser();
        $reservation = $repo->find($id);
        if (!$reservation || $reservation->getUtilisateur()?->getIdUtilisateur() !== $user->getIdUtilisateur()) {
            throw $this->createNotFoundException();
        }
        $filename = sprintf('billet_starthub_%d.pdf', $reservation->getIdReservation());
        return new Response($pdf->generatePdf($reservation), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ── QR verification (admin only — friendly gate, no hard exception) ───────
    #[Route('/verify/{token}', name: 'user_reservation_verify', methods: ['GET'])]
    public function verify(string $token, ReservationRepository $repo): Response
    {
        // Non-admin: show a friendly "admin required" page instead of a 403
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->render('user/verify.html.twig', [
                'admin_required' => true,
                'valid'          => false,
                'reservation'    => null,
                'evenement'      => null,
                'utilisateur'    => null,
            ]);
        }

        $r = $repo->findOneBy(['tokenVerification' => $token]);
        return $this->render('user/verify.html.twig', [
            'admin_required' => false,
            'valid'          => $r !== null,
            'reservation'    => $r,
            'evenement'      => $r?->getEvenement(),
            'utilisateur'    => $r?->getUtilisateur(),
        ]);
    }

    // ── Checkout: free → confirm, paid → Stripe ───────────────────────────────
    #[Route('/{id}/checkout', name: 'user_checkout', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function checkout(
        int $id,
        EvenementRepository      $evenementRepo,
        ReservationRepository    $reservationRepo,
        EntityManagerInterface   $em,
        StripeService            $stripe,
        ReservationMailerService $mailer,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid('checkout'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('user_evenements');
        }

        /** @var Utilisateur $user */
        $user      = $this->getUser();
        $evenement = $evenementRepo->find($id);

        if (!$evenement) {
            $this->addFlash('error', 'Événement introuvable.');
            return $this->redirectToRoute('user_evenements');
        }
        if (!$user->isActif()) {
            $this->addFlash('error', 'Votre compte est désactivé. Contactez l\'administrateur.');
            return $this->redirectToRoute('user_evenements');
        }
        if ($evenement->getCapacite() <= 0) {
            $this->addFlash('error', 'Cet événement est complet.');
            return $this->redirectToRoute('user_evenements');
        }
        if ($reservationRepo->existeDeja($evenement->getIdEvenement(), $user->getIdUtilisateur())) {
            $this->addFlash('warning', 'Vous avez déjà une réservation pour cet événement.');
            return $this->redirectToRoute('user_evenements');
        }

        $reservation = new Reservation();
        $reservation->setEvenement($evenement);
        $reservation->setUtilisateur($user);
        $reservation->setStatutPaiement($evenement->getPrix() > 0 ? 'pending' : 'free');
        $evenement->setCapacite($evenement->getCapacite() - 1);
        $em->persist($reservation);
        $em->flush();

        if ($evenement->getPrix() <= 0) {
            try { $mailer->sendConfirmation($reservation); } catch (\Throwable $t) {}
            $this->addFlash('success', 'Réservation confirmée ! Votre billet PDF a été envoyé par email.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        return $this->launchStripe($reservation, $evenement, $stripe, $em);
    }

    // ── Pay a pending reservation ─────────────────────────────────────────────
    #[Route('/payer/{id}', name: 'user_pay_pending', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function payPending(
        int $id,
        ReservationRepository  $repo,
        EntityManagerInterface $em,
        StripeService          $stripe,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid('payer'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('user_mes_reservations');
        }
        /** @var Utilisateur $user */
        $user        = $this->getUser();
        $reservation = $repo->find($id);

        if (!$reservation || $reservation->getUtilisateur()?->getIdUtilisateur() !== $user->getIdUtilisateur()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('user_mes_reservations');
        }
        if ($reservation->getStatutPaiement() !== 'pending') {
            $this->addFlash('warning', 'Cette réservation est déjà confirmée.');
            return $this->redirectToRoute('user_mes_reservations');
        }
        $evenement = $reservation->getEvenement();
        if (!$evenement || $evenement->getDateEvenement() <= new \DateTime()) {
            $this->addFlash('error', 'Impossible de payer : l\'événement est déjà passé.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        return $this->launchStripe($reservation, $evenement, $stripe, $em);
    }

    // ── Shared helper: create Stripe session and redirect ─────────────────────
    private function launchStripe(
        Reservation $reservation, $evenement,
        StripeService $stripe, EntityManagerInterface $em
    ): Response {
        try {
            $successUrl = $this->generateUrl('user_payment_success', [], 0)
                .'?session_id={CHECKOUT_SESSION_ID}&res_id='.$reservation->getIdReservation();
            $cancelUrl  = $this->generateUrl('user_payment_cancel',
                ['res_id' => $reservation->getIdReservation()], 0);

            $session = $stripe->createCheckoutSession($evenement, $reservation->getIdReservation(), $successUrl, $cancelUrl);
            $reservation->setStripePaymentId($session->id);
            $em->flush();
            return $this->redirect($session->url);
        } catch (\Throwable $t) {
            $this->addFlash('error', 'Erreur Stripe : '.$t->getMessage());
            return $this->redirectToRoute('user_mes_reservations');
        }
    }

    // ── Stripe success ────────────────────────────────────────────────────────
    #[Route('/payment/success', name: 'user_payment_success', methods: ['GET'])]
    public function paymentSuccess(
        Request $request, ReservationRepository $repo,
        StripeService $stripe, ReservationMailerService $mailer, EntityManagerInterface $em
    ): Response {
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
                try { $mailer->sendConfirmation($reservation); } catch (\Throwable $t) {}
                $this->addFlash('success', 'Paiement confirmé ! Votre billet PDF a été envoyé par email.');
            } else {
                $this->addFlash('warning', 'Paiement en attente de confirmation Stripe.');
            }
        } catch (\Throwable $t) {
            $this->addFlash('warning', 'Impossible de vérifier le paiement. Contactez le support.');
        }
        return $this->redirectToRoute('user_mes_reservations');
    }

    // ── Stripe cancel → delete pending, restore capacity ─────────────────────
    #[Route('/payment/cancel', name: 'user_payment_cancel', methods: ['GET'])]
    public function paymentCancel(
        Request $request, ReservationRepository $repo, EntityManagerInterface $em
    ): Response {
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

    // ── Cancel confirmed reservation ──────────────────────────────────────────
    #[Route('/annuler/{id}', name: 'user_annuler_reservation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function annuler(
        int $id, ReservationRepository $reservationRepo, EntityManagerInterface $em, Request $request
    ): Response {
        if (!$this->isCsrfTokenValid('annuler'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('user_mes_reservations');
        }
        /** @var Utilisateur $user */
        $user        = $this->getUser();
        $reservation = $reservationRepo->find($id);
        if (!$reservation || $reservation->getUtilisateur()?->getIdUtilisateur() !== $user->getIdUtilisateur()) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        $wasPaid   = $reservation->getStatutPaiement() === 'paid';
        $evenement = $reservation->getEvenement();
        if ($evenement) $evenement->setCapacite($evenement->getCapacite() + 1);
        $em->remove($reservation);
        $em->flush();

        if ($wasPaid) {
            $this->addFlash('success', 'Réservation annulée. Notre équipe vous contactera dans les 48h pour procéder au remboursement.');
        } else {
            $this->addFlash('success', 'Réservation annulée avec succès.');
        }
        return $this->redirectToRoute('user_mes_reservations');
    }
}

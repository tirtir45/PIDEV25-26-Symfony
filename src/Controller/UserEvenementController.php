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
    public function index(EvenementRepository $repo, Request $request): Response
    {
        $search     = $request->query->get('search', '');
        $sort       = $request->query->get('sort', 'date_asc');
        $prixMinRaw = $request->query->get('prix_min');
        $prixMaxRaw = $request->query->get('prix_max');
        $prixMin    = ($prixMinRaw !== null && $prixMinRaw !== '') ? (float) $prixMinRaw : null;
        $prixMax    = ($prixMaxRaw !== null && $prixMaxRaw !== '') ? (float) $prixMaxRaw : null;
        $evenements = $repo->findWithFilters($search ?: null, $sort, $prixMin, $prixMax);

        return $this->render('user/evenements.html.twig', [
            'evenements' => $evenements,
            'search'     => $search,
            'sort'       => $sort,
            'prix_min'   => $prixMin,
            'prix_max'   => $prixMax,
            'active'     => 'events',
        ]);
    }

    // ── My reservations ───────────────────────────────────────────────────────
    #[Route('/mes-reservations', name: 'user_mes_reservations', methods: ['GET'])]
    public function mesReservations(ReservationRepository $repo): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        return $this->render('user/mes_reservations.html.twig', [
            'reservations' => $repo->findByUtilisateur($user->getIdUtilisateur()),
            'utilisateur'  => $user,
            'active'       => 'reservations',
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

    // ── QR verification (public) ──────────────────────────────────────────────
    #[Route('/verify/{token}', name: 'user_reservation_verify', methods: ['GET'])]
    public function verify(string $token, ReservationRepository $repo): Response
    {
        $r = $repo->findOneBy(['tokenVerification' => $token]);
        return $this->render('user/verify.html.twig', [
            'valid'       => $r !== null,
            'reservation' => $r,
            'evenement'   => $r?->getEvenement(),
            'utilisateur' => $r?->getUtilisateur(),
        ]);
    }

    // ── Checkout: free → confirm, paid → Stripe ───────────────────────────────
    #[Route('/{id}/checkout', name: 'user_checkout', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function checkout(
        int $id,
        EvenementRepository   $evenementRepo,
        ReservationRepository $reservationRepo,
        EntityManagerInterface $em,
        StripeService          $stripe,
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

        // Pre-create reservation
        $reservation = new Reservation();
        $reservation->setEvenement($evenement);
        $reservation->setUtilisateur($user);
        $reservation->setStatutPaiement($evenement->getPrix() > 0 ? 'pending' : 'free');
        $evenement->setCapacite($evenement->getCapacite() - 1);
        $em->persist($reservation);
        $em->flush();

        // FREE — confirm immediately + send email with PDF
        if ($evenement->getPrix() <= 0) {
            try { $mailer->sendConfirmation($reservation); } catch (\Throwable $t) {}
            $this->addFlash('success', 'Réservation confirmée ! Votre billet PDF a été envoyé par email.');
            return $this->redirectToRoute('user_mes_reservations');
        }

        // PAID — redirect to Stripe Checkout
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
            $evenement->setCapacite($evenement->getCapacite() + 1);
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('error', 'Erreur Stripe : '.$t->getMessage());
            return $this->redirectToRoute('user_evenements');
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

    // ── Stripe cancel ─────────────────────────────────────────────────────────
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
        $this->addFlash('warning', 'Paiement annulé. Réservation libérée.');
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
        $evenement = $reservation->getEvenement();
        if ($evenement) $evenement->setCapacite($evenement->getCapacite() + 1);
        $em->remove($reservation);
        $em->flush();
        $this->addFlash('success', 'Réservation annulée.');
        return $this->redirectToRoute('user_mes_reservations');
    }
}

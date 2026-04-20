<?php

namespace App\Controller;

use App\Entity\Reservations;
use App\Form\ReservationType;
use App\Repository\ReservationsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reservation')]
class ReservationController extends AbstractController
{
    private function requireAdmin(Request $request): ?Response
    {
        if (!$request->getSession()->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }
        if ($request->getSession()->get('user_role') !== 'Administrateur') {
            throw $this->createAccessDeniedException();
        }
        return null;
    }

    #[Route('/', name: 'admin_reservation_index', methods: ['GET'])]
    public function index(ReservationsRepository $repo, Request $request): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $statut  = $request->query->get('statut', '');
        $periode = $request->query->get('periode', '');
        $search  = $request->query->get('search', '');

        $all = $repo->findAll();
        $now = new \DateTime();

        $reservations = array_filter($all, function (Reservations $r) use ($statut, $periode, $search, $now) {
            if ($statut !== '' && $r->getStatutPaiement() !== $statut) return false;
            if ($periode === 'futur' && !($r->getEvenement()?->getDate_evenement() > $now)) return false;
            if ($periode === 'passe' && !($r->getEvenement()?->getDate_evenement() <= $now)) return false;
            if ($search !== '') {
                $haystack = strtolower(
                    ($r->getEvenement()?->getTitre() ?? '') . ' ' .
                    ($r->getUtilisateur()?->getNom() ?? '') . ' ' .
                    ($r->getUtilisateur()?->getEmail() ?? '')
                );
                if (!str_contains($haystack, strtolower($search))) return false;
            }
            return true;
        });

        return $this->render('reservation/index.html.twig', [
            'reservations' => array_values($reservations),
            'total'        => count($all),
            'total_revenu' => $repo->getTotalRevenu(),
            'statut'       => $statut,
            'periode'      => $periode,
            'search'       => $search,
        ]);
    }

    #[Route('/new', name: 'admin_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ReservationsRepository $reservationRepo): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $reservation = new Reservations();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement   = $reservation->getEvenement();
            $utilisateur = $reservation->getUtilisateur();

            if ($evenement && $evenement->getCapacite() <= 0) {
                $this->addFlash('error', 'Cet événement est complet. Impossible de réserver.');
                return $this->render('reservation/new.html.twig', ['form' => $form->createView()]);
            }

            if ($evenement && $utilisateur && $reservationRepo->existeDeja($evenement->getId_evenement(), $utilisateur->getId())) {
                $this->addFlash('error', 'Cet utilisateur a déjà une réservation pour cet événement.');
                return $this->render('reservation/new.html.twig', ['form' => $form->createView()]);
            }

            $reservation->setDate_reservation(new \DateTime());
            $em->persist($reservation);

            if ($evenement) {
                $evenement->setCapacite($evenement->getCapacite() - 1);
            }

            $em->flush();
            $this->addFlash('success', 'Réservation ajoutée !');
            return $this->redirectToRoute('admin_reservation_index');
        }

        return $this->render('reservation/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'admin_reservation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, ReservationsRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $reservation = $repo->find($id);
        if (!$reservation) throw $this->createNotFoundException();

        $oldEvenement = $reservation->getEvenement();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEvenement = $reservation->getEvenement();

            if ($oldEvenement && $newEvenement && $oldEvenement->getId_evenement() !== $newEvenement->getId_evenement()) {
                $oldEvenement->setCapacite($oldEvenement->getCapacite() + 1);
                if ($newEvenement->getCapacite() > 0) {
                    $newEvenement->setCapacite($newEvenement->getCapacite() - 1);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Réservation modifiée avec succès !');
            return $this->redirectToRoute('admin_reservation_index');
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form'        => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_reservation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, ReservationsRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $reservation = $repo->find($id);
        if (!$reservation) throw $this->createNotFoundException();

        if ($this->isCsrfTokenValid('delete' . $reservation->getId_reservation(), $request->request->get('_token'))) {
            $evenement = $reservation->getEvenement();
            if ($evenement) {
                $evenement->setCapacite($evenement->getCapacite() + 1);
            }
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée.');
        }
        return $this->redirectToRoute('admin_reservation_index');
    }
}

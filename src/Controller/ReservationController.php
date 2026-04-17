<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservation')]
#[IsGranted('ROLE_ADMIN')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'admin_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $repo): Response
    {
        return $this->render('reservation/index.html.twig', [
            'reservations' => $repo->findAll(),
            'total_revenu' => $repo->getTotalRevenu(),
        ]);
    }

    #[Route('/new', name: 'admin_reservation_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo
    ): Response {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement   = $reservation->getEvenement();
            $utilisateur = $reservation->getUtilisateur();

            // Check 1: event has capacity (same as Java valider())
            if ($evenement && $evenement->getCapacite() <= 0) {
                $this->addFlash('error', 'Cet événement est complet (aucune place disponible). Impossible de réserver.');
                return $this->render('reservation/new.html.twig', ['form' => $form->createView()]);
            }

            // Check 2: no duplicate reservation (same as Java sr.reservationExisteDeja())
            if ($evenement && $utilisateur && $reservationRepo->existeDeja($evenement->getIdEvenement(), $utilisateur->getIdUtilisateur())) {
                $this->addFlash('error', 'Cet utilisateur a déjà une réservation pour cet événement.');
                return $this->render('reservation/new.html.twig', ['form' => $form->createView()]);
            }

            // Always set date to current timestamp (like Java: LocalDateTime.now())
            $reservation->setDateReservation(new \DateTime());

            $em->persist($reservation);

            // Decrement capacity
            if ($evenement) {
                $evenement->setCapacite($evenement->getCapacite() - 1);
            }

            $em->flush();
            $this->addFlash('success', 'Réservation ajoutée ! Une place a été déduite de la capacité de l\'événement.');
            return $this->redirectToRoute('admin_reservation_index');
        }

        return $this->render('reservation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_reservation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        $oldEvenement = $reservation->getEvenement();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEvenement = $reservation->getEvenement();

            // If event changed: restore old capacity, decrement new
            if ($oldEvenement && $newEvenement && $oldEvenement->getIdEvenement() !== $newEvenement->getIdEvenement()) {
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
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getIdReservation(), $request->request->get('_token'))) {
            // Restore capacity
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

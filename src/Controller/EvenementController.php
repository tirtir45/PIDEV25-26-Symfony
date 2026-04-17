<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/evenement')]
#[IsGranted('ROLE_ADMIN')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(
        EvenementRepository  $evenementRepo,
        ReservationRepository $reservationRepo,
        Request $request
    ): Response {
        $search      = $request->query->get('search', '');
        $sort        = $request->query->get('sort', 'date_asc');
        $prixMinRaw  = $request->query->get('prix_min');
        $prixMaxRaw  = $request->query->get('prix_max');
        $prixMin     = ($prixMinRaw !== null && $prixMinRaw !== '') ? (float) $prixMinRaw : null;
        $prixMax     = ($prixMaxRaw !== null && $prixMaxRaw !== '') ? (float) $prixMaxRaw : null;
        $statut      = $request->query->get('statut', '');

        $evenements = $evenementRepo->findWithFilters($search ?: null, $sort, $prixMin, $prixMax, $statut ?: null);

        $statTotal  = count($evenementRepo->findAll());
        $statFutur  = $evenementRepo->countFuturs();
        $statRes    = count($reservationRepo->findAll());
        $statRevenu = $reservationRepo->getTotalRevenu();

        return $this->render('evenement/index.html.twig', [
            'evenements'  => $evenements,
            'search'      => $search,
            'sort'        => $sort,
            'prix_min'    => $prixMin,
            'prix_max'    => $prixMax,
            'statut'      => $statut,
            'stat_total'  => $statTotal,
            'stat_futur'  => $statFutur,
            'stat_res'    => $statRes,
            'stat_revenu' => $statRevenu,
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evenement);
            $em->flush();
            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('evenement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Evenement $evenement, ReservationRepository $reservationRepo): Response
    {
        $reservations = $reservationRepo->findByEvenement($evenement->getIdEvenement());

        return $this->render('evenement/show.html.twig', [
            'evenement'    => $evenement,
            'reservations' => $reservations,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_evenement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form'      => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_evenement_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $evenement->getIdEvenement(), $request->request->get('_token'))) {
            $em->remove($evenement);
            $em->flush();
            $this->addFlash('success', 'Événement supprimé.');
        }
        return $this->redirectToRoute('admin_evenement_index');
    }

    // ── Statistics dashboard ──────────────────────────────────────────────────
    #[Route('/statistiques', name: 'admin_evenement_stats', methods: ['GET'])]
    public function statistiques(
        EvenementRepository   $evenementRepo,
        ReservationRepository $reservationRepo
    ): Response {
        return $this->render('evenement/statistiques.html.twig', [
            'active'           => 'admin_stats',
            'countByMonth'     => $reservationRepo->countByMonth(),
            'topEvenements'    => $reservationRepo->topEvenements(5),
            'revenuParEvement' => $reservationRepo->revenuParEvenement(),
            'countFuturs'      => $evenementRepo->countFuturs(),
            'countPasses'      => $evenementRepo->countPasses(),
            'totalEvenements'  => count($evenementRepo->findAll()),
            'totalReservations'=> count($reservationRepo->findAll()),
            'totalRevenu'      => $reservationRepo->getTotalRevenu(),
        ]);
    }
}

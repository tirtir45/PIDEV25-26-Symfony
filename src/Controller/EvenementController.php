<?php

namespace App\Controller;

use App\Entity\Evenements;
use App\Form\EvenementType;
use App\Repository\EvenementsRepository;
use App\Repository\ReservationsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/evenement')]
class EvenementController extends AbstractController
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

    #[Route('/', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(EvenementsRepository $evenementRepo, ReservationsRepository $reservationRepo, Request $request): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $search     = $request->query->get('search', '');
        $sort       = $request->query->get('sort', 'date_asc');
        $prixMinRaw = $request->query->get('prix_min');
        $prixMaxRaw = $request->query->get('prix_max');
        $prixMin    = ($prixMinRaw !== null && $prixMinRaw !== '') ? (float) $prixMinRaw : null;
        $prixMax    = ($prixMaxRaw !== null && $prixMaxRaw !== '') ? (float) $prixMaxRaw : null;
        $statut     = $request->query->get('statut', '');

        $evenements = $evenementRepo->findWithFilters($search ?: null, $sort, $prixMin, $prixMax, $statut ?: null);

        return $this->render('evenement/index.html.twig', [
            'evenements'     => $evenements,
            'search'         => $search,
            'sort'           => $sort,
            'prix_min'       => $prixMin,
            'prix_max'       => $prixMax,
            'statut'         => $statut,
            'stat_total'     => count($evenementRepo->findAll()),
            'stat_futur'     => $evenementRepo->countFuturs(),
            'stat_res'       => count($reservationRepo->findAll()),
            'stat_revenu'    => $reservationRepo->getTotalRevenu(),
            'count_by_month' => $reservationRepo->countByMonth(),
            'top_evenements' => $reservationRepo->topEvenements(5),
            'revenu_par_ev'  => $reservationRepo->revenuParEvenement(),
            'count_passes'   => $evenementRepo->countPasses(),
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $evenement = new Evenements();
        $form = $this->createForm(EvenementType::class, $evenement, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evenement);
            $em->flush();
            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('evenement/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, EvenementsRepository $evenementRepo, ReservationsRepository $reservationRepo, Request $request): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $evenement = $evenementRepo->find($id);
        if (!$evenement) throw $this->createNotFoundException();

        return $this->render('evenement/show.html.twig', [
            'evenement'    => $evenement,
            'reservations' => $reservationRepo->findByEvenement($evenement->getId_evenement()),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_evenement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, EvenementsRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $evenement = $repo->find($id);
        if (!$evenement) throw $this->createNotFoundException();

        $form = $this->createForm(EvenementType::class, $evenement, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('evenement/edit.html.twig', ['evenement' => $evenement, 'form' => $form->createView()]);
    }

    #[Route('/{id}/delete', name: 'admin_evenement_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, EvenementsRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $evenement = $repo->find($id);
        if (!$evenement) throw $this->createNotFoundException();

        if ($this->isCsrfTokenValid('delete' . $evenement->getId_evenement(), $request->request->get('_token'))) {
            $em->remove($evenement);
            $em->flush();
            $this->addFlash('success', 'Événement supprimé.');
        }
        return $this->redirectToRoute('admin_evenement_index');
    }

    #[Route('/statistiques', name: 'admin_evenement_stats', methods: ['GET'])]
    public function statistiques(EvenementsRepository $evenementRepo, ReservationsRepository $reservationRepo, Request $request): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        return $this->render('evenement/statistiques.html.twig', [
            'countByMonth'      => $reservationRepo->countByMonth(),
            'topEvenements'     => $reservationRepo->topEvenements(5),
            'revenuParEvement'  => $reservationRepo->revenuParEvenement(),
            'countFuturs'       => $evenementRepo->countFuturs(),
            'countPasses'       => $evenementRepo->countPasses(),
            'totalEvenements'   => count($evenementRepo->findAll()),
            'totalReservations' => count($reservationRepo->findAll()),
            'totalRevenu'       => $reservationRepo->getTotalRevenu(),
        ]);
    }
}

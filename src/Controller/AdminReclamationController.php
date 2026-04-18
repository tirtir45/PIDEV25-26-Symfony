<?php

namespace App\Controller;

use App\Entity\Reclamations;
use App\Entity\Reclamation_commentaires;
use App\Repository\ReclamationsRepository;
use App\Repository\UtilisateursRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminReclamationController extends AbstractController
{
    private function checkAdmin(Request $request): bool
    {
        return $request->getSession()->get('user_id')
            && $request->getSession()->get('user_role') === 'Administrateur';
    }

    #[Route('/admin/reclamations', name: 'admin_reclamation_index', methods: ['GET'])]
    public function index(Request $request, ReclamationsRepository $repo): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_dashboard');
        }

        $search  = $request->query->get('search', '');
        $statut  = $request->query->get('statut', '');
        $sort    = $request->query->get('sort', 'dateCreation');
        $order   = $request->query->get('order', 'DESC');

        $allowed = ['dateCreation', 'sujet', 'statut'];
        $sort    = in_array($sort, $allowed) ? $sort : 'dateCreation';
        $order   = $order === 'ASC' ? 'ASC' : 'DESC';

        $qb = $repo->createQueryBuilder('r')
            ->leftJoin('r.utilisateur', 'u')
            ->addSelect('u');

        if ($search !== '') {
            $qb->andWhere('r.sujet LIKE :q OR u.nom LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($statut !== '') {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $statut);
        }

        $qb->orderBy('r.' . $sort, $order);
        $reclamations = $qb->getQuery()->getResult();

        // KPI stats
        $all = $repo->findAll();
        $stats = [
            'total'     => count($all),
            'attente'   => count(array_filter($all, fn($r) => $r->getStatut() === 'EN_ATTENTE')),
            'en_cours'  => count(array_filter($all, fn($r) => $r->getStatut() === 'EN_COURS')),
            'resolu'    => count(array_filter($all, fn($r) => $r->getStatut() === 'RESOLU')),
            'rejete'    => count(array_filter($all, fn($r) => $r->getStatut() === 'REJETE')),
            'high'      => count(array_filter($all, fn($r) => $r->getPriorite() === 'HIGH')),
            'negatif'   => count(array_filter($all, fn($r) => $r->getSentiment() === 'négatif')),
        ];

        // Catégories
        $cats = [];
        foreach ($all as $r) {
            $c = $r->getCategorie() ?? 'autre';
            $cats[$c] = ($cats[$c] ?? 0) + 1;
        }
        arsort($cats);

        return $this->render('admin/reclamations.html.twig', [
            'reclamations' => $reclamations,
            'search'       => $search,
            'statut'       => $statut,
            'sort'         => $sort,
            'order'        => $order,
            'total'        => count($reclamations),
            'stats'        => $stats,
            'cats'         => $cats,
        ]);
    }

    #[Route('/admin/reclamations/{id}', name: 'admin_reclamation_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        Request $request,
        ReclamationsRepository $repo,
        UtilisateursRepository $userRepo,
        EntityManagerInterface $em,
        NotificationService $notifier
    ): Response {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_dashboard');
        }

        $reclamation = $repo->find($id);
        if (!$reclamation) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $contenu = trim((string) $request->request->get('message', ''));
            $statut  = $request->request->get('statut');

            // Changement de statut
            if ($statut) {
                $oldStatut = $reclamation->getStatut();
                $reclamation->setStatut($statut);
                $em->flush();
                if ($oldStatut !== $statut) {
                    $notifier->onStatusChange($reclamation, $oldStatut);
                }
                $this->addFlash('success', 'Statut mis à jour.');
            }

            // Envoi message admin
            if (!empty($contenu)) {
                if ($reclamation->getStatut() === 'RESOLU' || $reclamation->getStatut() === 'REJETE') {
                    $this->addFlash('warning', 'Réclamation résolue, communication fermée.');
                    return $this->redirectToRoute('admin_reclamation_show', ['id' => $id]);
                }

                $adminId = $request->getSession()->get('user_id');
                $admin   = $userRepo->find($adminId);
                $msg = new Reclamation_commentaires();
                $msg->setId_reclamation($reclamation);
                $msg->setId_auteur($admin);
                $msg->setCommentaire($contenu);
                $msg->setDate_commentaire(new \DateTime());
                $em->persist($msg);
                $em->flush();

                $notifier->onAdminReply($reclamation, $contenu);
            }

            return $this->redirectToRoute('admin_reclamation_show', ['id' => $id]);
        }

        return $this->render('admin/reclamation_show.html.twig', [
            'reclamation' => $reclamation,
            'messages'    => $reclamation->getReclamationCommentairess(),
            'adminId'     => $request->getSession()->get('user_id'),
        ]);
    }

    #[Route('/admin/reclamations/{id}/statut', name: 'admin_reclamation_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatut(int $id, Request $request, ReclamationsRepository $repo, EntityManagerInterface $em, NotificationService $notifier): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_dashboard');
        }

        $r = $repo->find($id);
        if ($r) {
            $oldStatut = $r->getStatut();
            $r->setStatut($request->request->get('statut', 'EN_ATTENTE'));
            $em->flush();
            if ($oldStatut !== $r->getStatut()) {
                $notifier->onStatusChange($r, $oldStatut);
            }
            $this->addFlash('success', 'Statut mis à jour.');
        }

        return $this->redirectToRoute('admin_reclamation_index');
    }

    #[Route('/admin/reclamations/{id}/supprimer', name: 'admin_reclamation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, ReclamationsRepository $repo, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_dashboard');
        }

        $r = $repo->find($id);
        if ($r) {
            $em->remove($r);
            $em->flush();
            $this->addFlash('success', 'Réclamation supprimée.');
        }

        return $this->redirectToRoute('admin_reclamation_index');
    }
}
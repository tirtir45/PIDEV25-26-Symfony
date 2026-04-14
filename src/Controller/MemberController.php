<?php
// src/Controller/MemberController.php

namespace App\Controller;

use App\Entity\Membres_equipe;
use App\Entity\Taches;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MemberController extends AbstractController
{
    #[Route('/member-demo', name: 'member_dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $membre = $em->getRepository(Utilisateurs::class)->find($userId);
        if (!$membre) {
            return $this->redirectToRoute('app_login');
        }
        
        $queryBuilder = $em->getRepository(Taches::class)->createQueryBuilder('t');
        $queryBuilder->join('t.id_projet', 'p')
            ->join('p.membres_equipes', 'me')
            ->where('me.id_utilisateur = :membre')
            ->setParameter('membre', $membre);
        
        // Filtre par statut
        $statusFilter = $request->query->get('status');
        if ($statusFilter && $statusFilter !== 'all') {
            $queryBuilder->andWhere('t.statut = :status')
            ->setParameter('status', $statusFilter);
        }
        
        // Tri
        $sort = $request->query->get('sort', 'date_limite');
        $order = $request->query->get('order', 'ASC');
        $queryBuilder->orderBy('t.' . $sort, $order);
        
        $taches = $queryBuilder->getQuery()->getResult();
        
        $stats = [
            'total_taches' => count($taches),
            'a_faire' => count(array_filter($taches, fn($t) => $t->getStatut() === Taches::STATUT_A_FAIRE)),
            'en_cours' => count(array_filter($taches, fn($t) => $t->getStatut() === Taches::STATUT_EN_COURS)),
            'terminees' => count(array_filter($taches, fn($t) => $t->getStatut() === Taches::STATUT_TERMINEE)),
        ];
        
        return $this->render('member/dashboard.html.twig', [
            'taches' => $taches,
            'stats' => $stats,
            'currentStatus' => $statusFilter,
            'sort' => $sort,
            'order' => $order
        ]);
    }

    #[Route('/member/tache/{id}/modifier-statut', name: 'member_modifier_statut_tache')]
    public function modifierStatutTache(Taches $tache, Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $em->getRepository(Utilisateurs::class)->find($userId);
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que l'utilisateur est le responsable de la tâche
        if ($tache->getIdResponsable() !== $user) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cette tâche.');
            return $this->redirectToRoute('member_mes_taches');
        }

        if ($request->isMethod('POST')) {
            $nouveauStatut = $request->request->get('statut');
            $commentaire = $request->request->get('commentaire');
            
            if (in_array($nouveauStatut, [Taches::STATUT_A_FAIRE, Taches::STATUT_EN_COURS, Taches::STATUT_TERMINEE])) {
                $ancienStatut = $tache->getStatut();
                $tache->setStatut($nouveauStatut);
                
                if ($commentaire) {
                    // Vous pouvez ajouter un champ commentaire à l'entité Tache si nécessaire
                    $this->addFlash('info', 'Commentaire ajouté à la tâche.');
                }
                
                $em->flush();
                $this->addFlash('success', sprintf('Statut de la tâche "%s" mis à jour de "%s" vers "%s" !', 
                    $tache->getTitre(), $ancienStatut, $nouveauStatut));
            } else {
                $this->addFlash('error', 'Statut invalide.');
            }
        }
        
        return $this->redirectToRoute('member_mes_taches');
    }

    #[Route('/member/mes-taches', name: 'member_mes_taches')]
    public function mesTaches(Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $membre = $em->getRepository(Utilisateurs::class)->find($userId);
        if (!$membre) {
            return $this->redirectToRoute('app_login');
        }
        
        $queryBuilder = $em->getRepository(Taches::class)->createQueryBuilder('t');
        $queryBuilder->where('t.id_responsable = :membre')
            ->setParameter('membre', $membre);
        
        // Filtre par statut
        $statusFilter = $request->query->get('status');
        if ($statusFilter && $statusFilter !== 'all') {
            $queryBuilder->andWhere('t.statut = :status')
            ->setParameter('status', $statusFilter);
        }
        
        // Tri
        $sort = $request->query->get('sort', 'date_limite');
        $order = $request->query->get('order', 'ASC');
        $queryBuilder->orderBy('t.' . $sort, $order);
        
        $taches = $queryBuilder->getQuery()->getResult();
        
        $stats = [
            'total_taches' => count($taches),
            'a_faire' => count(array_filter($taches, fn($t) => $t->getStatut() === Taches::STATUT_A_FAIRE)),
            'en_cours' => count(array_filter($taches, fn($t) => $t->getStatut() === Taches::STATUT_EN_COURS)),
            'terminees' => count(array_filter($taches, fn($t) => $t->getStatut() === Taches::STATUT_TERMINEE)),
        ];
        
        return $this->render('member/mes_taches.html.twig', [
            'taches' => $taches,
            'stats' => $stats,
            'currentStatus' => $statusFilter,
            'sort' => $sort,
            'order' => $order
        ]);
    }
}
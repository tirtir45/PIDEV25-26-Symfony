<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Projets;
use App\Entity\Taches;
use App\Entity\Membres_equipe;
use App\Entity\Utilisateurs;
use App\Form\ProjetValidationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    #[Route('/admin-demo', name: 'admin_dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'date_soumission');
        $order = $request->query->get('order', 'DESC');
        
        $qb = $em->getRepository(Projets::class)->createQueryBuilder('p');
        
        if ($status) {
            $qb->andWhere('p.etat = :status')->setParameter('status', $status);
        }
        
        if ($search) {
            $qb->andWhere('p.titre LIKE :search OR p.description LIKE :search')
            ->setParameter('search', '%' . $search . '%');
        }
        
        $qb->orderBy('p.' . $sort, $order);
        $projets = $qb->getQuery()->getResult();
        
        $stats = [
            'en_attente' => $em->getRepository(Projets::class)->count(['etat' => Projets::ETAT_EN_ATTENTE]),
            'acceptes' => $em->getRepository(Projets::class)->count(['etat' => Projets::ETAT_ACCEPTE]),
            'refuses' => $em->getRepository(Projets::class)->count(['etat' => Projets::ETAT_REFUSE]),
            'en_cours' => $em->getRepository(Projets::class)->count(['etat' => Projets::ETAT_EN_COURS]),
            'termines' => $em->getRepository(Projets::class)->count(['etat' => Projets::ETAT_TERMINE]),
            'total' => $em->getRepository(Projets::class)->count([])
        ];
        
        return $this->render('admin/dashboard.html.twig', [
            'projets' => $projets,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'order' => $order
        ]);
    }

    #[Route('/admin/projet/{id}/valider', name: 'admin_valider_projet')]
    public function validerProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier que l'utilisateur est admin
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier si c'est une soumission POST
        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $commentaire = $request->request->get('commentaire_admin');
            
            if ($action === 'accepter') {
                $projet->setEtat(Projets::ETAT_ACCEPTE);
                if ($commentaire) {
                    $projet->setCommentaire_admin($commentaire);
                }
                $this->addFlash('success', 'Projet accepté avec succès !');
                $em->flush();
                return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
                
            } elseif ($action === 'refuser') {
                $projet->setEtat(Projets::ETAT_REFUSE);
                if ($commentaire) {
                    $projet->setCommentaire_admin($commentaire);
                }
                $this->addFlash('warning', 'Projet refusé.');
                $em->flush();
                return $this->redirectToRoute('admin_dashboard');
            }
        }
        
        return $this->render('admin/valider_projet.html.twig', [
            'projet' => $projet
        ]);
    }

    // Route pour voir les détails du projet par l'admin (lecture seule)
    #[Route('/admin/projet/{id}/gestion', name: 'admin_gestion_projet')]
    public function gestionProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }
        
        // Récupérer les membres et tâches existants (lecture seule)
        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $taches = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        
        return $this->render('admin/gestion_projet.html.twig', [
            'projet' => $projet,
            'membres' => $membres,
            'taches' => $taches
        ]);
    }

    // Route pour changer le statut d'un projet
    #[Route('/admin/projet/{id}/statut', name: 'admin_changer_statut_projet')]
    public function changerStatutProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }
        
        if ($request->isMethod('POST')) {
            $nouveauStatut = $request->request->get('statut');
            if (in_array($nouveauStatut, [Projets::ETAT_EN_COURS, Projets::ETAT_TERMINE])) {
                $projet->setEtat($nouveauStatut);
                $em->flush();
                $this->addFlash('success', 'Statut du projet mis à jour !');
            }
        }
        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
    }
}
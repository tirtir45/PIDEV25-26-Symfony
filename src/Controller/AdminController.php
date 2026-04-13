<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Projets;
use App\Entity\Taches;
use App\Entity\Membres_equipe;
use App\Entity\Utilisateurs;
use App\Form\ProjetValidationType;
use App\Form\MembreEquipeType;
use App\Form\TacheType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        if (!$request->getSession()->get('user_id') || $request->getSession()->get('user_role') !== 'Administrateur') {
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
            'total_users' => $em->getRepository(Utilisateurs::class)->count([]),
            'total_reclam' => $em->getRepository(\App\Entity\Reclamations::class)->count(['statut' => 'EN_ATTENTE']),
            'total_projets' => $em->getRepository(Projets::class)->count([])
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
        $form = $this->createForm(ProjetValidationType::class, $projet);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $action = $form->get('action')->getData();
            
            if ($action === 'accepter') {
                $projet->setEtat(Projets::ETAT_ACCEPTE);
                $this->addFlash('success', 'Projet accepté avec succès !');
            } elseif ($action === 'refuser') {
                $projet->setEtat(Projets::ETAT_REFUSE);
                $this->addFlash('warning', 'Projet refusé.');
            }
            
            $em->flush();
            return $this->redirectToRoute('admin_dashboard');
        }
        
        return $this->render('admin/valider_projet.html.twig', [
            'projet' => $projet,
            'form' => $form->createView()
        ]);
    }

    // Route AJOUTÉE pour la gestion des projets par l'admin
    #[Route('/admin/projet/{id}/gestion', name: 'admin_gestion_projet')]
    public function gestionProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        if ($projet->getEtat() !== Projets::ETAT_ACCEPTE && $projet->getEtat() !== Projets::ETAT_EN_COURS) {
            $this->addFlash('error', 'Ce projet doit être accepté avant de pouvoir ajouter des membres et des tâches.');
            return $this->redirectToRoute('admin_dashboard');
        }
        
        $membreForm = $this->createForm(MembreEquipeType::class);
        $membreForm->handleRequest($request);
        
        if ($membreForm->isSubmitted() && $membreForm->isValid()) {
            $membreEquipe = $membreForm->getData();
            $membreEquipe->setIdProjet($projet);
            
            if (!$membreEquipe->getIdUtilisateur()) {
                $this->addFlash('error', 'Veuillez sélectionner un membre.');
            } else {
                $em->persist($membreEquipe);
                $em->flush();
                $this->addFlash('success', 'Membre ajouté avec succès !');
                return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
            }
        }
        
        $tacheForm = $this->createForm(TacheType::class);
        $tacheForm->handleRequest($request);
        
        if ($tacheForm->isSubmitted() && $tacheForm->isValid()) {
            $tache = $tacheForm->getData();
            $tache->setIdProjet($projet);
            
            if (empty($tache->getTitre())) {
                $this->addFlash('error', 'Le titre de la tâche est obligatoire.');
            } else {
                $em->persist($tache);
                $em->flush();
                $this->addFlash('success', 'Tâche ajoutée avec succès !');
                return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
            }
        }
        
        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $taches = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        
        return $this->render('admin/gestion_projet.html.twig', [
            'projet' => $projet,
            'membres' => $membres,
            'taches' => $taches,
            'membreForm' => $membreForm->createView(),
            'tacheForm' => $tacheForm->createView()
        ]);
    }

    // Route AJOUTÉE pour supprimer une tâche
    #[Route('/admin/tache/{id}/supprimer', name: 'admin_supprimer_tache')]
    public function supprimerTache(Taches $tache, EntityManagerInterface $em): Response
    {
        $projetId = $tache->getIdProjet()->getIdProjet();
        $em->remove($tache);
        $em->flush();
        $this->addFlash('success', 'Tâche supprimée avec succès !');
        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projetId]);
    }

    // Route AJOUTÉE pour supprimer un membre
    #[Route('/admin/membre/{id}/supprimer', name: 'admin_supprimer_membre')]
    public function supprimerMembre(Membres_equipe $membre, EntityManagerInterface $em): Response
    {
        $projetId = $membre->getIdProjet()->getIdProjet();
        $em->remove($membre);
        $em->flush();
        $this->addFlash('success', 'Membre supprimé avec succès !');
        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projetId]);
    }

    // Route AJOUTÉE pour changer le statut d'un projet
    #[Route('/admin/projet/{id}/statut', name: 'admin_changer_statut_projet')]
    public function changerStatutProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
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
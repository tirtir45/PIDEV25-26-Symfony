<?php
// src/Controller/EntrepreneurController.php

namespace App\Controller;

use App\Entity\Projets;
use App\Entity\Taches;
use App\Entity\Membres_equipe;
use App\Entity\Utilisateurs;
use App\Form\ProjetType;
use App\Form\TacheType;
use App\Form\MembreEquipeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntrepreneurController extends AbstractController
{
    #[Route('/entrepreneur-demo', name: 'entrepreneur_dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        if (!$entrepreneur) {
            return $this->redirectToRoute('app_login');
        }
        
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'date_soumission');
        
        $queryBuilder = $em->getRepository(Projets::class)->createQueryBuilder('p');
        $queryBuilder->where('p.id_entrepreneur = :entrepreneur')
            ->setParameter('entrepreneur', $entrepreneur);
        
        if ($search) {
            $queryBuilder->andWhere('p.titre LIKE :search OR p.description LIKE :search')
            ->setParameter('search', '%' . $search . '%');
        }
        
        if ($status) {
            $queryBuilder->andWhere('p.etat = :status')
            ->setParameter('status', $status);
        }
        
        $queryBuilder->orderBy('p.' . $sort, 'DESC');
        
        $projets = $queryBuilder->getQuery()->getResult();
        
        $stats = [
            'total' => count($projets),
            'en_attente' => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_EN_ATTENTE)),
            'acceptes' => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_ACCEPTE)),
            'en_cours' => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_EN_COURS)),
            'termines' => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_TERMINE))
        ];
        
        return $this->render('entrepreneur/dashboard.html.twig', [
            'projets' => $projets,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'sort' => $sort
        ]);
    }

    #[Route('/entrepreneur/projet/nouveau', name: 'entrepreneur_nouveau_projet')]
    public function nouveauProjet(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        $projet = new Projets();
        $projet->setId_entrepreneur($entrepreneur);
        
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($projet);
            $em->flush();
            $this->addFlash('success', 'Votre projet a été soumis avec succès !');
            return $this->redirectToRoute('entrepreneur_dashboard');
        }
        
        return $this->render('entrepreneur/nouveau_projet.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/entrepreneur/projet/{id}/gestion', name: 'entrepreneur_gestion_projet')]
    public function gestionProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier que le projet appartient à l'entrepreneur
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce projet.');
            return $this->redirectToRoute('entrepreneur_dashboard');
        }
        
        if ($projet->getEtat() !== Projets::ETAT_ACCEPTE && $projet->getEtat() !== Projets::ETAT_EN_COURS) {
            $this->addFlash('error', 'Ce projet doit être accepté avant de pouvoir ajouter des membres et des tâches.');
            return $this->redirectToRoute('entrepreneur_dashboard');
        }
        
        // Gestion des membres
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
                return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
            }
        }
        
        // Gestion des tâches
        $tacheForm = $this->createForm(TacheType::class);
        $tacheForm->handleRequest($request);
        
        if ($tacheForm->isSubmitted() && $tacheForm->isValid()) {
            $tache = $tacheForm->getData();
            $tache->setId_projet($projet);
            
            if (empty($tache->getTitre())) {
                $this->addFlash('error', 'Le titre de la tâche est obligatoire.');
            } else {
                $em->persist($tache);
                $em->flush();
                $this->addFlash('success', 'Tâche ajoutée avec succès !');
                return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
            }
        }
        
        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $taches = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        
        return $this->render('entrepreneur/gestion_projet.html.twig', [
            'projet' => $projet,
            'membres' => $membres,
            'taches' => $taches,
            'membreForm' => $membreForm->createView(),
            'tacheForm' => $tacheForm->createView()
        ]);
    }

    #[Route('/entrepreneur/tache/{id}/supprimer', name: 'entrepreneur_supprimer_tache')]
    public function supprimerTache(Taches $tache, EntityManagerInterface $em): Response
    {
        $projetId = $tache->getProjet()->getIdProjet();
        $em->remove($tache);
        $em->flush();
        $this->addFlash('success', 'Tâche supprimée avec succès !');
        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projetId]);
    }

    #[Route('/entrepreneur/membre/{id}/supprimer', name: 'entrepreneur_supprimer_membre')]
    public function supprimerMembre(Membres_equipe $membre, EntityManagerInterface $em): Response
    {
        $projetId = $membre->getProjet()->getIdProjet();
        $em->remove($membre);
        $em->flush();
        $this->addFlash('success', 'Membre supprimé avec succès !');
        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projetId]);
    }

    #[Route('/entrepreneur/projet/{id}', name: 'entrepreneur_projet_detail')]
    public function projetDetail(Projets $projet, EntityManagerInterface $em): Response
    {
        $taches = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        
        return $this->render('entrepreneur/projet_detail.html.twig', [
            'projet' => $projet,
            'taches' => $taches,
            'membres' => $membres,
            'progression' => $projet->getProgression()
        ]);
    }

    #[Route('/entrepreneur/projet/{id}/statut', name: 'entrepreneur_changer_statut_projet')]
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
        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
    }
}
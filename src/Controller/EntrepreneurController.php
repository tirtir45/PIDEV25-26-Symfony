<?php
// src/Controller/EntrepreneurController.php (version complète avec CRUD)

namespace App\Controller;

use App\Entity\Projets;
use App\Entity\Taches;
use App\Entity\Membres_equipe;
use App\Entity\Utilisateurs;
use App\Form\ProjetType;
use App\Form\ProjetEditType;
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
    // ==================== CRUD PROJETS ====================
    
    /**
     * Liste des projets (READ)
     */
    #[Route('/entrepreneur/projets', name: 'entrepreneur_projets_liste')]
    public function listeProjets(Request $request, EntityManagerInterface $em): Response
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
        
        return $this->render('entrepreneur/liste_projets.html.twig', [
            'projets' => $projets,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'sort' => $sort
        ]);
    }

    /**
     * Créer un nouveau projet (CREATE)
     */
    #[Route('/entrepreneur/projet/nouveau', name: 'entrepreneur_nouveau_projet')]
    public function nouveauProjet(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        
        if (!$entrepreneur) {
            return $this->redirectToRoute('app_login');
        }
        
        $projet = new Projets();
        $projet->setId_entrepreneur($entrepreneur);
        
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($projet);
            $em->flush();
            $this->addFlash('success', 'Votre projet a été soumis avec succès !');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
        }
        
        return $this->render('entrepreneur/nouveau_projet.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Afficher les détails d'un projet (READ)
     */
    #[Route('/entrepreneur/projet/{id}', name: 'entrepreneur_projet_detail')]
    public function projetDetail(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier l'accès
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce projet.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }
        
        $taches = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        
        return $this->render('entrepreneur/projet_detail.html.twig', [
            'projet' => $projet,
            'taches' => $taches,
            'membres' => $membres,
            'progression' => $projet->getProgression()
        ]);
    }

    /**
     * Modifier un projet (UPDATE)
     */
    #[Route('/entrepreneur/projet/{id}/modifier', name: 'entrepreneur_projet_modifier')]
    public function modifierProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier l'accès
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce projet.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }
        
        // Vérifier si le projet peut être modifié
        if ($projet->getEtat() !== Projets::ETAT_EN_ATTENTE) {
            $this->addFlash('warning', 'Ce projet ne peut plus être modifié car il a déjà été traité.');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
        }
        
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Le projet a été modifié avec succès !');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
        }
        
        return $this->render('entrepreneur/modifier_projet.html.twig', [
            'form' => $form->createView(),
            'projet' => $projet
        ]);
    }

    /**
     * Supprimer un projet (DELETE)
     */
    #[Route('/entrepreneur/projet/{id}/supprimer', name: 'entrepreneur_projet_supprimer', methods: ['POST'])]
    public function supprimerProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier l'accès
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce projet.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }
        
        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete' . $projet->getIdProjet(), $request->request->get('_token'))) {
            // Vérifier si le projet peut être supprimé
            if ($projet->getEtat() !== Projets::ETAT_EN_ATTENTE) {
                $this->addFlash('warning', 'Ce projet ne peut pas être supprimé car il a déjà été traité.');
                return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
            }
            
            $em->remove($projet);
            $em->flush();
            $this->addFlash('success', 'Le projet a été supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }
        
        return $this->redirectToRoute('entrepreneur_projets_liste');
    }

    // ==================== GESTION DU PROJET (TÂCHES ET MEMBRES) ====================
    
    /**
     * Gestion du projet (tâches et membres)
     */
    #[Route('/entrepreneur/projet/{id}/gestion', name: 'entrepreneur_gestion_projet')]
    public function gestionProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier que le projet appartient à l'entrepreneur
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce projet.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }
        
        if ($projet->getEtat() !== Projets::ETAT_ACCEPTE && $projet->getEtat() !== Projets::ETAT_EN_COURS) {
            $this->addFlash('error', 'Ce projet doit être accepté avant de pouvoir ajouter des membres et des tâches.');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
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

    /**
     * Changer le statut du projet
     */
    #[Route('/entrepreneur/projet/{id}/statut', name: 'entrepreneur_changer_statut_projet', methods: ['POST'])]
    public function changerStatutProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier l'accès
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce projet.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }
        
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

    // ==================== GESTION DES TÂCHES ====================
    
    /**
     * Modifier une tâche
     */
    #[Route('/entrepreneur/tache/{id}/modifier', name: 'entrepreneur_modifier_tache')]
    public function modifierTache(Taches $tache, Request $request, EntityManagerInterface $em): Response
    {
        $projet = $tache->getProjet();
        
        // Vérifier l'accès
        $userId = $request->getSession()->get('user_id');
        $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
        
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cette tâche.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }
        
        $form = $this->createForm(TacheType::class, $tache);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Tâche modifiée avec succès !');
            return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
        }
        
        return $this->render('entrepreneur/modifier_tache.html.twig', [
            'form' => $form->createView(),
            'tache' => $tache,
            'projet' => $projet
        ]);
    }

    /**
     * Supprimer une tâche
     */
    #[Route('/entrepreneur/tache/{id}/supprimer', name: 'entrepreneur_supprimer_tache', methods: ['POST'])]
    public function supprimerTache(Taches $tache, Request $request, EntityManagerInterface $em): Response
    {
        $projetId = $tache->getProjet()->getIdProjet();
        
        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete_tache_' . $tache->getIdTache(), $request->request->get('_token'))) {
            $em->remove($tache);
            $em->flush();
            $this->addFlash('success', 'Tâche supprimée avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }
        
        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projetId]);
    }

    // ==================== GESTION DES MEMBRES ====================
    
    /**
     * Supprimer un membre
     */
    #[Route('/entrepreneur/membre/{id}/supprimer', name: 'entrepreneur_supprimer_membre', methods: ['POST'])]
    public function supprimerMembre(Membres_equipe $membre, Request $request, EntityManagerInterface $em): Response
    {
        $projetId = $membre->getProjet()->getIdProjet();
        
        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete_membre_' . $membre->getIdMembre(), $request->request->get('_token'))) {
            $em->remove($membre);
            $em->flush();
            $this->addFlash('success', 'Membre supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }
        
        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projetId]);
    }

    // ==================== DASHBOARD ====================
    
    /**
     * Dashboard (page d'accueil)
     */
    #[Route('/entrepreneur-demo', name: 'entrepreneur_dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        return $this->redirectToRoute('entrepreneur_projets_liste');
    }
}
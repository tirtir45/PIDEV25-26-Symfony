<?php
// src/Controller/EntrepreneurController.php

namespace App\Controller;

use App\Entity\Commentaires;
use App\Entity\FichierProjet;
use App\Entity\Membres_equipe;
use App\Entity\Notifications;
use App\Entity\Projets;
use App\Entity\RessourceProjet;
use App\Entity\Sprints;
use App\Entity\Taches;
use App\Entity\Utilisateurs;
use App\Form\CommentaireType;
use App\Form\FichierProjetType;
use App\Form\MembreEquipeType;
use App\Form\ProjetType;
use App\Form\RessourceProjetType;
use App\Form\SprintType;
use App\Form\TacheType;
use App\Repository\CommentairesRepository;
use App\Service\CalendarService;
use App\Service\FileUploader;
use App\Service\GroqTaskGenerator;
use App\Service\KanbanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EntrepreneurController extends AbstractController
{
    // ==================== HELPERS ====================

    private function getAuthenticatedEntrepreneur(Request $request, EntityManagerInterface $em): ?Utilisateurs
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) return null;

        $user = $em->getRepository(Utilisateurs::class)->find($userId);
        if (!$user || !$user->getRole() || $user->getRole()->getNomRole() !== 'Entrepreneur') {
            return null;
        }
        return $user;
    }

    private function assertProjectOwnership(Projets $projet, ?Utilisateurs $entrepreneur): bool
    {
        if (!$entrepreneur) return false;
        $owner = $projet->getId_entrepreneur();
        if (!$owner) return false;
        return $owner->getId() === $entrepreneur->getId();
    }

    // ==================== CRUD PROJETS ====================

    #[Route('/entrepreneur/projets', name: 'entrepreneur_projets_liste')]
    public function listeProjets(Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$entrepreneur) return $this->redirectToRoute('app_login');

        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort   = $request->query->get('sort', 'date_soumission');

        $qb = $em->getRepository(Projets::class)->createQueryBuilder('p')
            ->where('p.id_entrepreneur = :entrepreneur')
            ->setParameter('entrepreneur', $entrepreneur);

        if ($search) $qb->andWhere('p.titre LIKE :search OR p.description LIKE :search')->setParameter('search', '%' . $search . '%');
        if ($status) $qb->andWhere('p.etat = :status')->setParameter('status', $status);

        $allowedSorts = ['date_soumission', 'titre', 'etat', 'budget_estime'];
        if (!in_array($sort, $allowedSorts)) $sort = 'date_soumission';
        $qb->orderBy('p.' . $sort, 'DESC');

        $projets = $qb->getQuery()->getResult();

        $stats = [
            'total'      => count($projets),
            'en_attente' => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_EN_ATTENTE)),
            'acceptes'   => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_ACCEPTE)),
            'en_cours'   => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_EN_COURS)),
            'termines'   => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_TERMINE)),
            'refuses'    => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_REFUSE)),
        ];

        return $this->render('entrepreneur/liste_projets.html.twig', [
            'projets' => $projets, 'stats' => $stats, 'search' => $search, 'status' => $status, 'sort' => $sort,
        ]);
    }

    #[Route('/entrepreneur/projet/nouveau', name: 'entrepreneur_nouveau_projet')]
    public function nouveauProjet(Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$entrepreneur) return $this->redirectToRoute('app_login');

        $projet = new Projets();
        $projet->setId_entrepreneur($entrepreneur);

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($projet);
            $em->flush();
            $this->addFlash('success', 'Votre projet a été soumis avec succès !');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('entrepreneur/nouveau_projet.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/entrepreneur/projet/{id}', name: 'entrepreneur_projet_detail')]
    public function projetDetail(Projets $projet, Request $request, EntityManagerInterface $em, \App\Service\StartHubAIService $aiService): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé à ce projet.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $request->getSession()->set('projet_actif_id', $projet->getIdProjet());

        $existingData = $projet->getAiAnalysisData();
        $needsAnalysis = !$projet->getAiAnalyzedAt()
            || ($existingData !== null && !isset($existingData['site_web_html'], $existingData['kpis'], $existingData['personas']));

        if ($needsAnalysis) {
            try {
                $aiAnalysis = $aiService->analyzeProject($projet);
                $projet->setAiScoreGlobal($aiAnalysis['score_global']);
                $projet->setAiAnalysisData($aiAnalysis);
                $projet->setAiAnalyzedAt(new \DateTime());
                $em->flush();
                $this->addFlash('success', 'Analyse IA effectuée avec succès !');
            } catch (\Exception $e) {
                $this->addFlash('warning', 'L\'analyse IA n\'est pas disponible pour le moment.');
                error_log('AI Analysis error: ' . $e->getMessage());
            }
        }

        $taches  = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);

        return $this->render('entrepreneur/projet_detail.html.twig', [
            'projet'      => $projet,
            'taches'      => $taches,
            'membres'     => $membres,
            'progression' => $projet->getProgression(),
            'aiAnalysis'  => $this->normalizeAiAnalysis($projet->getAiAnalysisData()),
        ]);
    }

    #[Route('/entrepreneur/projet/{id}/modifier', name: 'entrepreneur_projet_modifier')]
    public function modifierProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        if ($projet->getEtat() !== Projets::ETAT_EN_ATTENTE) {
            $this->addFlash('warning', 'Seuls les projets en attente peuvent être modifiés.');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
        }

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Projet modifié avec succès !');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
        }

        return $this->render('entrepreneur/modifier_projet.html.twig', ['form' => $form->createView(), 'projet' => $projet]);
    }

    #[Route('/entrepreneur/projet/{id}/supprimer', name: 'entrepreneur_projet_supprimer', methods: ['POST'])]
    public function supprimerProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        if (!$this->isCsrfTokenValid('delete' . $projet->getIdProjet(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        if ($projet->getEtat() !== Projets::ETAT_EN_ATTENTE) {
            $this->addFlash('warning', 'Seuls les projets en attente peuvent être supprimés.');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
        }

        $em->remove($projet);
        $em->flush();
        $this->addFlash('success', 'Projet supprimé avec succès.');
        return $this->redirectToRoute('entrepreneur_projets_liste');
    }

    // ==================== GESTION DU PROJET ====================

    #[Route('/entrepreneur/projet/{id}/gestion', name: 'entrepreneur_gestion_projet')]
    public function gestionProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        if (!in_array($projet->getEtat(), [Projets::ETAT_ACCEPTE, Projets::ETAT_EN_COURS])) {
            $this->addFlash('error', 'Le projet doit être accepté avant de gérer l\'équipe et les tâches.');
            return $this->redirectToRoute('entrepreneur_projet_detail', ['id' => $projet->getIdProjet()]);
        }

        $request->getSession()->set('projet_actif_id', $projet->getIdProjet());

        $membreForm = $this->createForm(MembreEquipeType::class, null, ['projet' => $projet, 'em' => $em]);
        $membreForm->handleRequest($request);

        if ($membreForm->isSubmitted() && $membreForm->isValid() && $request->request->has($membreForm->getName())) {
            $utilisateurId = $membreForm->get('id_utilisateur')->getData();
            $roleEquipe    = $membreForm->get('role_equipe')->getData();
            $utilisateur   = $em->getRepository(Utilisateurs::class)->find($utilisateurId);

            if (!$utilisateur) {
                $this->addFlash('error', 'Utilisateur introuvable.');
            } elseif (!in_array($utilisateur->getRole()->getNomRole(), ['Entrepreneur', 'Candidat'])) {
                $this->addFlash('error', 'Seuls les entrepreneurs et candidats peuvent rejoindre une équipe.');
            } else {
                $existing = $em->getRepository(Membres_equipe::class)->findOneBy(['id_projet' => $projet, 'id_utilisateur' => $utilisateur]);
                if ($existing) {
                    $this->addFlash('warning', 'Cet utilisateur est déjà membre de l\'équipe.');
                } else {
                    $membre = new Membres_equipe();
                    $membre->setIdProjet($projet);
                    $membre->setIdUtilisateur($utilisateur);
                    $membre->setRoleEquipe($roleEquipe);
                    $em->persist($membre);
                    $em->flush();
                    $this->addFlash('success', 'Membre ajouté avec succès !');
                }
            }
            return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
        }

        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $tache = new Taches();
        $tacheForm = $this->createForm(TacheType::class, $tache, ['membres' => $membres]);
        $tacheForm->handleRequest($request);

        if ($tacheForm->isSubmitted() && $tacheForm->isValid() && $request->request->has($tacheForm->getName())) {
            $tache->setId_projet($projet);

            $responsableId = $tacheForm->get('id_responsable')->getData();
            if ($responsableId) {
                $responsable = $em->getRepository(Utilisateurs::class)->find($responsableId);
                if ($responsable) {
                    $tache->setId_responsable($responsable);
                    $membreEquipe = $em->getRepository(Membres_equipe::class)->findOneBy(['id_projet' => $projet, 'id_utilisateur' => $responsable]);
                    if (!$membreEquipe) {
                        $membreEquipe = new Membres_equipe();
                        $membreEquipe->setId_projet($projet);
                        $membreEquipe->setId_utilisateur($responsable);
                        $membreEquipe->setRole_equipe('Membre');
                        $em->persist($membreEquipe);
                    }
                }
            }

            if ($tache->getDateLimite() && $tache->getDateLimite() < new \DateTime('today')) {
                $this->addFlash('error', 'La date limite ne peut pas être dans le passé.');
            } else {
                $em->persist($tache);
                $em->flush();
                $this->addFlash('success', 'Tâche ajoutée avec succès !');
            }

            return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
        }

        $taches = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);

        return $this->render('entrepreneur/gestion_projet.html.twig', [
            'projet'     => $projet,
            'membres'    => $membres,
            'taches'     => $taches,
            'membreForm' => $membreForm->createView(),
            'tacheForm'  => $tacheForm->createView(),
        ]);
    }

    // ==================== COMMENTAIRES TÂCHES ====================

    #[Route('/entrepreneur/tache/{id}/commentaires', name: 'entrepreneur_tache_commentaires', methods: ['POST'])]
    public function ajouterCommentaire(Taches $tache, Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($tache->getId_projet(), $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $commentaire = new Commentaires();
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commentaire->setTache($tache);
            $commentaire->setAuteur($entrepreneur);
            $em->persist($commentaire);
            $em->flush();
            $this->addFlash('success', 'Commentaire ajouté.');
        }

        return $this->redirectToRoute('entrepreneur_modifier_tache', ['id' => $tache->getIdTache()]);
    }

    #[Route('/entrepreneur/commentaire/{id}/supprimer', name: 'entrepreneur_supprimer_commentaire', methods: ['POST'])]
    public function supprimerCommentaire(Commentaires $commentaire, Request $request, EntityManagerInterface $em): Response
    {
        $tache        = $commentaire->getTache();
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);

        if (!$this->assertProjectOwnership($tache->getId_projet(), $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        if ($commentaire->getAuteur()->getId() !== $entrepreneur->getId()) {
            $this->addFlash('error', 'Vous ne pouvez supprimer que vos propres commentaires.');
            return $this->redirectToRoute('entrepreneur_modifier_tache', ['id' => $tache->getIdTache()]);
        }

        if ($this->isCsrfTokenValid('delete_commentaire_' . $commentaire->getId(), $request->request->get('_token'))) {
            $em->remove($commentaire);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('entrepreneur_modifier_tache', ['id' => $tache->getIdTache()]);
    }

    #[Route('/entrepreneur/projet/{id}/statut', name: 'entrepreneur_changer_statut_projet', methods: ['POST'])]
    public function changerStatutProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $nouveauStatut = $request->request->get('statut');
        if (in_array($nouveauStatut, [Projets::ETAT_EN_COURS, Projets::ETAT_TERMINE])) {
            $allowedTransitions = [Projets::ETAT_ACCEPTE => Projets::ETAT_EN_COURS, Projets::ETAT_EN_COURS => Projets::ETAT_TERMINE];
            if (isset($allowedTransitions[$projet->getEtat()]) && $allowedTransitions[$projet->getEtat()] === $nouveauStatut) {
                $projet->setEtat($nouveauStatut);
                $em->flush();
                $this->addFlash('success', 'Statut mis à jour avec succès !');
            } else {
                $this->addFlash('error', 'Transition de statut non autorisée.');
            }
        }

        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
    }

    // ==================== CRUD TÂCHES ====================

    #[Route('/entrepreneur/tache/{id}/modifier', name: 'entrepreneur_modifier_tache')]
    public function modifierTache(Taches $tache, Request $request, EntityManagerInterface $em): Response
    {
        $projet       = $tache->getId_projet();
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);

        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $form    = $this->createForm(TacheType::class, $tache, ['membres' => $membres]);

        if ($tache->getId_responsable()) {
            $form->get('id_responsable')->setData($tache->getId_responsable()->getId());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $responsableId = $form->get('id_responsable')->getData();
            if ($responsableId) {
                $responsable = $em->getRepository(Utilisateurs::class)->find($responsableId);
                if ($responsable) $tache->setId_responsable($responsable);
            } else {
                $tache->setId_responsable(null);
            }

            if ($tache->getDateLimite() && $tache->getDateLimite() < new \DateTime('today')) {
                $this->addFlash('error', 'La date limite ne peut pas être dans le passé.');
            } else {
                $em->flush();
                $this->addFlash('success', 'Tâche modifiée avec succès !');
                return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
            }
        }

        $commentaires = $em->getRepository(Commentaires::class)->findByTache($tache);

        return $this->render('entrepreneur/modifier_tache.html.twig', [
            'form'         => $form->createView(),
            'tache'        => $tache,
            'projet'       => $projet,
            'commentaires' => $commentaires,
        ]);
    }

    #[Route('/entrepreneur/tache/{id}/supprimer', name: 'entrepreneur_supprimer_tache', methods: ['POST'])]
    public function supprimerTache(Taches $tache, Request $request, EntityManagerInterface $em): Response
    {
        $projetId = $tache->getId_projet()->getIdProjet();

        if ($this->isCsrfTokenValid('delete_tache_' . $tache->getIdTache(), $request->request->get('_token'))) {
            $em->remove($tache);
            $em->flush();
            $this->addFlash('success', 'Tâche supprimée.');
        }

        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projetId]);
    }

    // ==================== CRUD MEMBRES ====================

    #[Route('/entrepreneur/membre/{id}/modifier', name: 'entrepreneur_modifier_membre')]
    public function modifierMembre(Membres_equipe $membre, Request $request, EntityManagerInterface $em): Response
    {
        $projet       = $membre->getIdProjet();
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);

        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $form = $this->createForm(MembreEquipeType::class, null, ['projet' => $projet, 'em' => $em, 'membre' => $membre]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleEquipe = $form->get('role_equipe')->getData();
            $membre->setRoleEquipe($roleEquipe);
            $em->flush();
            $this->addFlash('success', 'Membre modifié avec succès !');
            return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projet->getIdProjet()]);
        }

        return $this->render('entrepreneur/modifier_membre.html.twig', ['form' => $form->createView(), 'membre' => $membre, 'projet' => $projet]);
    }

    #[Route('/entrepreneur/membre/{id}/supprimer', name: 'entrepreneur_supprimer_membre', methods: ['POST'])]
    public function supprimerMembre(Membres_equipe $membre, Request $request, EntityManagerInterface $em): Response
    {
        $projetId = $membre->getIdProjet()->getIdProjet();

        if ($this->isCsrfTokenValid('delete_membre_' . $membre->getIdMembre(), $request->request->get('_token'))) {
            $em->remove($membre);
            $em->flush();
            $this->addFlash('success', 'Membre retiré de l\'équipe.');
        }

        return $this->redirectToRoute('entrepreneur_gestion_projet', ['id' => $projetId]);
    }

    #[Route('/entrepreneur/dashboard', name: 'entrepreneur_dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$entrepreneur) return $this->redirectToRoute('app_login');

        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort   = $request->query->get('sort', 'date_soumission');

        $qb = $em->getRepository(Projets::class)->createQueryBuilder('p')
            ->where('p.id_entrepreneur = :entrepreneur')
            ->setParameter('entrepreneur', $entrepreneur);

        if ($search) $qb->andWhere('p.titre LIKE :search OR p.description LIKE :search')->setParameter('search', '%' . $search . '%');
        if ($status) $qb->andWhere('p.etat = :status')->setParameter('status', $status);

        $allowedSorts = ['date_soumission', 'titre', 'etat', 'budget_estime'];
        if (!in_array($sort, $allowedSorts)) $sort = 'date_soumission';
        $qb->orderBy('p.' . $sort, 'DESC');

        $projets = $qb->getQuery()->getResult();

        $stats = [
            'total'      => count($projets),
            'en_attente' => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_EN_ATTENTE)),
            'acceptes'   => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_ACCEPTE)),
            'en_cours'   => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_EN_COURS)),
            'termines'   => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_TERMINE)),
            'refuses'    => count(array_filter($projets, fn($p) => $p->getEtat() === Projets::ETAT_REFUSE)),
        ];

        return $this->render('entrepreneur/dashboard.html.twig', [
            'projets' => $projets,
            'stats'   => $stats,
            'search'  => $search,
            'status'  => $status,
            'sort'    => $sort,
        ]);
    }

    // ==================== KANBAN ====================

    #[Route('/entrepreneur/projet/{id}/kanban', name: 'entrepreneur_kanban')]
    #[Route('/project/{id}/kanban', name: 'project_kanban')]
    public function kanban(Projets $projet, Request $request, EntityManagerInterface $em, KanbanService $kanban): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $request->getSession()->set('projet_actif_id', $projet->getIdProjet());

        $membres   = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $tache     = new Taches();
        $tacheForm = $this->createForm(TacheType::class, $tache, ['membres' => $membres]);
        $tacheForm->handleRequest($request);

        if ($tacheForm->isSubmitted() && $tacheForm->isValid()) {
            $tache->setId_projet($projet);
            $responsableId = $tacheForm->get('id_responsable')->getData();
            if ($responsableId) {
                $responsable = $em->getRepository(Utilisateurs::class)->find($responsableId);
                $tache->setId_responsable($responsable);
            }
            $em->persist($tache);
            $em->flush();
            $this->addFlash('success', 'Tâche créée avec succès !');
            return $this->redirectToRoute('entrepreneur_kanban', ['id' => $projet->getIdProjet()]);
        }

        return $this->render('project/kanban.html.twig', [
            'projet'    => $projet,
            'columns'   => $kanban->getColumns($projet),
            'tacheForm' => $tacheForm->createView(),
            'membres'   => $membres,
        ]);
    }

    #[Route('/entrepreneur/tache/{id}/statut', name: 'entrepreneur_tache_statut', methods: ['POST'])]
    public function changerStatutTache(Taches $tache, Request $request, EntityManagerInterface $em, KanbanService $kanban): JsonResponse
    {
        $projet       = $tache->getId_projet();
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        try {
            $kanban->deplacerTache($tache, $data['statut'] ?? '');
            return new JsonResponse(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    // ==================== SPRINTS ====================

    #[Route('/entrepreneur/projet/{id}/sprints', name: 'entrepreneur_sprints')]
    #[Route('/project/{id}/sprint', name: 'project_sprint')]
    public function sprints(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $request->getSession()->set('projet_actif_id', $projet->getIdProjet());

        $sprint     = new Sprints();
        $sprintForm = $this->createForm(SprintType::class, $sprint);
        $sprintForm->handleRequest($request);

        if ($sprintForm->isSubmitted() && $sprintForm->isValid()) {
            $sprint->setId_projet($projet);
            $em->persist($sprint);
            $em->flush();
            $this->addFlash('success', 'Sprint créé avec succès !');
            return $this->redirectToRoute('entrepreneur_sprints', ['id' => $projet->getIdProjet()]);
        }

        $sprints     = $em->getRepository(Sprints::class)->findBy(['id_projet' => $projet], ['date_debut' => 'DESC']);
        $sprintActif = null;
        foreach ($sprints as $s) {
            if ($s->getStatut() === 'actif') { $sprintActif = $s; break; }
        }

        return $this->render('project/sprint.html.twig', [
            'projet'      => $projet,
            'sprints'     => $sprints,
            'sprintActif' => $sprintActif,
            'sprintForm'  => $sprintForm->createView(),
        ]);
    }

    // ==================== CALENDRIER ====================

    #[Route('/entrepreneur/projet/{id}/calendrier', name: 'entrepreneur_calendrier')]
    #[Route('/project/{id}/calendar', name: 'project_calendar')]
    public function calendrier(Projets $projet, Request $request, EntityManagerInterface $em, CalendarService $calendar): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $request->getSession()->set('projet_actif_id', $projet->getIdProjet());

        return $this->render('project/calendar.html.twig', [
            'projet'     => $projet,
            'eventsJson' => json_encode($calendar->getEvents($projet)),
        ]);
    }

    // ==================== MATRICE EISENHOWER ====================

    #[Route('/entrepreneur/projet/{id}/matrice', name: 'entrepreneur_matrice')]
    #[Route('/project/{id}/matrix', name: 'project_matrix')]
    public function matrice(Projets $projet, Request $request, EntityManagerInterface $em): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $request->getSession()->set('projet_actif_id', $projet->getIdProjet());

        $taches  = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        $matrice = ['urgent_important' => [], 'non_urgent_important' => [], 'urgent_non_important' => [], 'non_urgent_non_important' => []];

        foreach ($taches as $tache) {
            $daysLeft     = $tache->getDateLimite() ? (new \DateTime())->diff($tache->getDateLimite())->days : 999;
            $estUrgent    = $daysLeft <= 3;
            $estImportant = $tache->getStatut() === Taches::STATUT_EN_COURS;

            if ($estUrgent && $estImportant)          $matrice['urgent_important'][] = $tache;
            elseif (!$estUrgent && $estImportant)     $matrice['non_urgent_important'][] = $tache;
            elseif ($estUrgent && !$estImportant)     $matrice['urgent_non_important'][] = $tache;
            else                                      $matrice['non_urgent_non_important'][] = $tache;
        }

        return $this->render('project/matrix.html.twig', ['projet' => $projet, 'matrice' => $matrice]);
    }

    // ==================== RESSOURCES PROJET ====================

    #[Route('/entrepreneur/projet/{id}/ressources-projet', name: 'entrepreneur_ressources_projet')]
    #[Route('/project/{id}/resources', name: 'project_resources')]
    public function ressourcesProjet(Projets $projet, Request $request, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('entrepreneur_projets_liste');
        }

        $request->getSession()->set('projet_actif_id', $projet->getIdProjet());

        $ressource     = new RessourceProjet();
        $ressourceForm = $this->createForm(RessourceProjetType::class, $ressource);
        $ressourceForm->handleRequest($request);

        if ($ressourceForm->isSubmitted() && $ressourceForm->isValid()) {
            $ressource->setProjet($projet);
            $em->persist($ressource);
            $em->flush();
            $this->addFlash('success', 'Ressource ajoutée.');
            return $this->redirectToRoute('entrepreneur_ressources_projet', ['id' => $projet->getIdProjet()]);
        }

        $fichierForm = $this->createForm(FichierProjetType::class);
        $fichierForm->handleRequest($request);

        if ($fichierForm->isSubmitted() && $fichierForm->isValid()) {
            $uploadedFile = $fichierForm->get('fichier')->getData();
            if ($uploadedFile) {
                $fileUploader->upload($uploadedFile, $projet, $entrepreneur);
                $this->addFlash('success', 'Fichier uploadé avec succès.');
            }
            return $this->redirectToRoute('entrepreneur_ressources_projet', ['id' => $projet->getIdProjet()]);
        }

        $ressources = $em->getRepository(RessourceProjet::class)->findByProjet($projet);
        $fichiers   = $em->getRepository(FichierProjet::class)->findByProjet($projet);

        return $this->render('project/resources.html.twig', [
            'projet'        => $projet,
            'ressources'    => $ressources,
            'fichiers'      => $fichiers,
            'ressourceForm' => $ressourceForm->createView(),
            'fichierForm'   => $fichierForm->createView(),
        ]);
    }

    // ==================== SAUVEGARDE TÂCHES IA ====================

    #[Route('/entrepreneur/projet/{id}/save-ai-tasks', name: 'entrepreneur_save_ai_tasks', methods: ['POST'])]
    public function saveAiTasks(Projets $projet, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data  = json_decode($request->getContent(), true);
        $tasks = $data['tasks'] ?? [];

        if (empty($tasks)) return new JsonResponse(['error' => 'Aucune tâche reçue'], 400);

        $membres = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $roleMap = [];
        foreach ($membres as $m) {
            $role = strtolower($m->getRoleEquipe() ?? '');
            $roleMap[$role] = $m->getIdUtilisateur();
        }

        $saved = 0;
        foreach ($tasks as $taskData) {
            if (empty($taskData['titre'])) continue;

            $tache = new Taches();
            $tache->setId_projet($projet);
            $tache->setTitre($taskData['titre']);
            $tache->setDescription($taskData['description'] ?? '');
            $tache->setStatut(Taches::STATUT_A_FAIRE);

            $categorie   = strtolower($taskData['categorie'] ?? '');
            $responsable = null;

            foreach ($roleMap as $role => $user) {
                if ($role && (str_contains($role, $categorie) || str_contains($categorie, $role))) {
                    $responsable = $user;
                    break;
                }
            }

            if (!$responsable && !empty($membres)) {
                $responsable = $membres[$saved % count($membres)]->getIdUtilisateur();
            }

            if ($responsable) $tache->setId_responsable($responsable);

            $em->persist($tache);
            $saved++;
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'saved' => $saved, 'message' => "$saved tâche(s) créée(s) avec succès"]);
    }

    // ==================== SUGGESTION DE TÂCHES (Groq) ====================

    #[Route('/entrepreneur/projet/{id}/suggest-tasks', name: 'entrepreneur_suggest_tasks', methods: ['POST'])]
    public function suggestTasks(Projets $projet, Request $request, EntityManagerInterface $em, GroqTaskGenerator $groq): JsonResponse
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data  = json_decode($request->getContent(), true);
        $count = min(max((int)($data['count'] ?? 8), 1), 15);

        try {
            $tasks = $groq->suggestTasks($projet->getDescription(), $count);
            return new JsonResponse(['success' => true, 'tasks' => $tasks]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ==================== SUGGESTION DE TÂCHES AVEC GROQ ====================

    #[Route('/entrepreneur/projet/{id}/suggest-tasks-groq', name: 'entrepreneur_suggest_tasks_groq', methods: ['POST'])]
    public function suggestTasksGroq(Projets $projet, Request $request, EntityManagerInterface $em, GroqTaskGenerator $groq): JsonResponse
    {
        $entrepreneur = $this->getAuthenticatedEntrepreneur($request, $em);
        if (!$this->assertProjectOwnership($projet, $entrepreneur)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data  = json_decode($request->getContent(), true);
        $count = min(max((int)($data['count'] ?? 8), 1), 15);

        try {
            $tasks = $groq->suggestTasks($projet->getDescription(), $count);
            return new JsonResponse(['success' => true, 'tasks' => $tasks]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ==================== NOTIFICATIONS API ====================

    #[Route('/api/notifications/unread/count', name: 'api_notifications_count')]
    public function notificationsCount(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) return new JsonResponse(['count' => 0]);

        $user  = $em->getRepository(Utilisateurs::class)->find($userId);
        $count = $user ? $em->getRepository(Notifications::class)->countNonLues($user) : 0;

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/api/notifications/mark-read', name: 'api_notifications_mark_read', methods: ['POST'])]
    public function markNotificationsRead(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) return new JsonResponse(['success' => false]);

        $user = $em->getRepository(Utilisateurs::class)->find($userId);
        if ($user) $em->getRepository(Notifications::class)->marquerToutesLues($user);

        return new JsonResponse(['success' => true]);
    }

    private function normalizeAiAnalysis(?array $data): ?array
    {
        if ($data === null) return null;

        return array_merge([
            'score_global'       => 0,
            'score_innovation'   => 0,
            'score_faisabilite'  => 0,
            'score_marche'       => 0,
            'score_equipe'       => 0,
            'forces'             => [],
            'faiblesses'         => [],
            'opportunites'       => [],
            'menaces'            => [],
            'recommandations'    => [],
            'ameliorations'      => [],
            'kpis'               => [],
            'personas'           => [],
            'projets_similaires' => [],
            'pitch'              => '',
            'site_web_html'      => '',
            'market_size'        => [],
            'analyzed_at'        => null,
        ], $data);
    }
}

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
use App\Service\EvaluationAutomatiqueService;
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

        if ($status) $qb->andWhere('p.etat = :status')->setParameter('status', $status);
        if ($search) $qb->andWhere('p.titre LIKE :search OR p.description LIKE :search')->setParameter('search', '%' . $search . '%');

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
    public function validerProjet(
        Projets $projet,
        Request $request,
        EntityManagerInterface $em,
        EvaluationAutomatiqueService $evaluationService,
        \App\Service\MarketstackService $marketstack,
        \App\Service\GooglePatentsService $patentsService
    ): Response {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $evaluation = $evaluationService->evaluerProjet($projet);

        $projet->setNoteMoyenne($evaluation['pourcentage']);
        $projet->setDateEvaluation(new \DateTime());
        $projet->setEvaluationAutomatique(true);
        $em->flush();

        $marketAnalysis = null;
        $patentAnalysis = null;

        try {
            if (!$projet->getMarketAnalyzedAt()) {
                $marketAnalysis = $marketstack->analyzeSectorTrend($projet);
                $projet->setMarketScore($marketAnalysis['score']);
                $projet->setMarketTrend($marketAnalysis['trend']);
                $projet->setMarketData($marketAnalysis);
                $projet->setMarketAnalyzedAt($marketAnalysis['analyzed_at']);
                $em->flush();
            } else {
                $marketAnalysis = $projet->getMarketData();
                $marketAnalysis['score'] = $projet->getMarketScore();
                $marketAnalysis['trend'] = $projet->getMarketTrend();
            }
        } catch (\Exception $e) {
            error_log("Erreur analyse marché: " . $e->getMessage());
            $this->addFlash('warning', 'L\'analyse de marché a échoué. Vous pouvez continuer la validation.');
        }

        try {
            if (!$projet->getPatentAnalyzedAt()) {
                $patentAnalysis = $patentsService->analyzePatents($projet);
                $projet->setPatentNoveltyScore($patentAnalysis['novelty_score']);
                $projet->setPatentTotalCount($patentAnalysis['total_results'] ?? $patentAnalysis['total_patents'] ?? 0);
                $projet->setPatentData($patentAnalysis);
                $projet->setPatentAnalyzedAt($patentAnalysis['analyzed_at']);
                $em->flush();
            } else {
                $patentAnalysis = $projet->getPatentData();
                $patentAnalysis['novelty_score'] = $projet->getPatentNoveltyScore();
                $patentAnalysis['total_results'] = $projet->getPatentTotalCount();
            }
        } catch (\Exception $e) {
            error_log("Erreur analyse brevets: " . $e->getMessage());
            $this->addFlash('warning', 'L\'analyse de brevets a échoué. Vous pouvez continuer la validation.');
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $commentaire = $request->request->get('commentaire_admin');

            if ($action === 'accepter') {
                $projet->setEtat(Projets::ETAT_ACCEPTE);
                if ($commentaire) $projet->setCommentaireAdmin($commentaire);
                $this->addFlash('success', 'Projet accepté avec succès !');
                $em->flush();
                return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
            } elseif ($action === 'refuser') {
                $projet->setEtat(Projets::ETAT_REFUSE);
                if ($commentaire) $projet->setCommentaireAdmin($commentaire);
                $this->addFlash('warning', 'Projet refusé.');
                $em->flush();
                return $this->redirectToRoute('admin_dashboard');
            }
        }

        return $this->render('admin/valider_projet.html.twig', [
            'projet' => $projet,
            'evaluation' => $evaluation,
            'marketAnalysis' => $marketAnalysis,
            'patentAnalysis' => $patentAnalysis
        ]);
    }

    #[Route('/admin/evaluation-automatique', name: 'admin_evaluation_auto')]
    public function evaluationAutomatique(Request $request, EntityManagerInterface $em, EvaluationAutomatiqueService $evaluationService): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $projetsEnAttente = $em->getRepository(Projets::class)->findBy(['etat' => Projets::ETAT_EN_ATTENTE]);

        foreach ($projetsEnAttente as $projet) {
            $evaluation = $evaluationService->evaluerProjet($projet);
            $projet->setNoteMoyenne($evaluation['pourcentage']);
            $projet->setDateEvaluation(new \DateTime());
            $projet->setEvaluationAutomatique(true);

            if ($evaluation['accepte']) {
                $projet->setEtat(Projets::ETAT_ACCEPTE);
                $projet->setCommentaireAdmin('Accepté automatiquement (score: ' . $evaluation['pourcentage'] . '%)');
            }
        }

        $em->flush();
        $this->addFlash('success', count($projetsEnAttente) . ' projet(s) évalués automatiquement');

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/projet/{id}/gestion', name: 'admin_gestion_projet')]
    public function gestionProjet(Projets $projet, Request $request, EntityManagerInterface $em, EvaluationAutomatiqueService $evaluationService): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $action      = $request->request->get('action');
            $commentaire = $request->request->get('commentaire_admin', '');

            if ($action === 'accepter') {
                $projet->setEtat(Projets::ETAT_ACCEPTE);
                if ($commentaire) $projet->setCommentaireAdmin($commentaire);
                $em->flush();
                $this->addFlash('success', 'Projet accepté avec succès !');
                return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
            } elseif ($action === 'refuser') {
                if (!$commentaire) {
                    $this->addFlash('error', 'Un commentaire est obligatoire pour refuser un projet.');
                    return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
                }
                $projet->setEtat(Projets::ETAT_REFUSE);
                $projet->setCommentaireAdmin($commentaire);
                $em->flush();
                $this->addFlash('warning', 'Projet refusé.');
                return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
            }
        }

        $membres    = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
        $taches     = $em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        $evaluation = $em->getRepository(\App\Entity\Evaluations_projet::class)->findOneBy(['id_projet' => $projet]);

        $tachesParMembre = [];
        foreach ($taches as $tache) {
            $resp = $tache->getIdResponsable();
            $key  = $resp ? $resp->getId() : 0;
            $tachesParMembre[$key][] = $tache;
        }

        $evalData = !$evaluation ? $evaluationService->evaluerProjet($projet) : null;

        return $this->render('admin/gestion_projet.html.twig', [
            'projet'          => $projet,
            'membres'         => $membres,
            'taches'          => $taches,
            'tachesParMembre' => $tachesParMembre,
            'evaluation'      => $evaluation,
            'evalData'        => $evalData,
        ]);
    }

    #[Route('/admin/tache/{id}/supprimer', name: 'admin_supprimer_tache')]
    public function supprimerTache(Taches $tache, EntityManagerInterface $em): Response
    {
        $projetId = $tache->getIdProjet()->getIdProjet();
        $em->remove($tache);
        $em->flush();
        $this->addFlash('success', 'Tâche supprimée avec succès !');
        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projetId]);
    }

    #[Route('/admin/membre/{id}/supprimer', name: 'admin_supprimer_membre')]
    public function supprimerMembre(Membres_equipe $membre, EntityManagerInterface $em): Response
    {
        $projetId = $membre->getIdProjet()->getIdProjet();
        $em->remove($membre);
        $em->flush();
        $this->addFlash('success', 'Membre supprimé avec succès !');
        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projetId]);
    }

    #[Route('/admin/projet/{id}/marche', name: 'admin_analyser_marche')]
    public function analyserMarche(Projets $projet, Request $request, EntityManagerInterface $em, \App\Service\MarketstackService $marketstack): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $result = $marketstack->analyzeSectorTrend($projet);
        $projet->setMarketScore($result['composite_score'] ?? $result['score']);
        $projet->setMarketTrend($result['trend']);
        $projet->setMarketData($result);
        $projet->setMarketAnalyzedAt($result['analyzed_at']);
        $em->flush();

        $this->addFlash('success', sprintf('Analyse marché terminée — Score : %d/100 | Tendance : %s', $result['score'], $result['trend']));

        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
    }

    #[Route('/admin/projet/{id}/patents', name: 'admin_analyser_patents')]
    public function analyserPatents(Projets $projet, Request $request, EntityManagerInterface $em, \App\Service\GooglePatentsService $patentsService): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $result = $patentsService->analyzePatents($projet);
        $projet->setPatentNoveltyScore($result['novelty_score']);
        $projet->setPatentTotalCount($result['total_results'] ?? $result['total_patents'] ?? 0);
        $projet->setPatentData($result);
        $projet->setPatentAnalyzedAt($result['analyzed_at']);
        $em->flush();

        $totalPatents = $result['total_results'] ?? $result['total_patents'] ?? 0;
        $this->addFlash('success', sprintf('Analyse brevets terminée — Score nouveauté : %d/100 | %d brevet(s) trouvé(s)', $result['novelty_score'], $totalPatents));

        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
    }

    #[Route('/admin/projet/{id}/evaluer', name: 'admin_evaluer_projet')]
    public function evaluerProjet(Projets $projet, Request $request, EntityManagerInterface $em, EvaluationAutomatiqueService $evaluationService): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $evaluation = $evaluationService->evaluerProjet($projet);
        $projet->setNoteMoyenne($evaluation['pourcentage']);
        $projet->setDateEvaluation(new \DateTime());
        $projet->setEvaluationAutomatique(true);
        $em->flush();

        $this->addFlash('success', 'Évaluation terminée ! Score : ' . $evaluation['pourcentage'] . '%');

        return $this->redirectToRoute('admin_gestion_projet', ['id' => $projet->getIdProjet()]);
    }

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

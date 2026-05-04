<?php
// src/Controller/StatisticsController.php

namespace App\Controller;

use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatisticsController extends AbstractController
{
    #[Route('/admin/statistiques', name: 'admin_statistiques')]
    public function index(Request $request, StatisticsService $stats): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('admin/statistiques.html.twig', [
            'globalStats'      => $stats->getGlobalStats(),
            'bySecteur'        => $stats->getProjetsBySecteur(),
            'byStatus'         => $stats->getProjetsByStatus(),
            'evolution'        => $stats->getProjetsEvolution(),
            'topEntrepreneurs' => $stats->getTopEntrepreneurs(),
            'avgScores'        => $stats->getAverageScoreBySecteur(),
            'marketScores'     => $stats->getMarketScoresBySecteur(),
            'recentActivity'   => $stats->getRecentActivity(),
            'funnel'           => $stats->getFunnel(),
        ]);
    }

    #[Route('/admin/statistiques/export', name: 'admin_statistiques_export')]
    public function export(Request $request, StatisticsService $stats): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId || $request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $global           = $stats->getGlobalStats();
        $secteur          = $stats->getProjetsBySecteur();
        $evolution        = $stats->getProjetsEvolution();
        $status           = $stats->getProjetsByStatus();
        $topEntrepreneurs = $stats->getTopEntrepreneurs();
        $avgScores        = $stats->getAverageScoreBySecteur();
        $marketScores     = $stats->getMarketScoresBySecteur();
        $funnel           = $stats->getFunnel();
        $recentActivity   = $stats->getRecentActivity(20);

        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($handle, ['RAPPORT STATISTIQUES STARTHUB'], ';');
        fputcsv($handle, ['Date de génération', date('d/m/Y H:i:s')], ';');
        fputcsv($handle, [''], ';');

        fputcsv($handle, ['=== INDICATEURS CLÉS ==='], ';');
        fputcsv($handle, ['Indicateur', 'Valeur'], ';');
        fputcsv($handle, ['Total projets', $global['total_projets']], ';');
        fputcsv($handle, ['Entrepreneurs actifs', $global['total_entrepreneurs']], ';');
        fputcsv($handle, ['Projets acceptés', $global['total_projets_acceptes']], ';');
        fputcsv($handle, ['Projets en attente', $global['total_projets_attente']], ';');
        fputcsv($handle, ['Score moyen global', $global['score_moyen_global'] ? $global['score_moyen_global'] . '%' : 'N/A'], ';');
        fputcsv($handle, [''], ';');

        fputcsv($handle, ['=== RÉPARTITION PAR STATUT ==='], ';');
        fputcsv($handle, ['Statut', 'Nombre'], ';');
        $totalStatus = array_sum($status['data']);
        foreach ($status['labels'] as $i => $label) {
            fputcsv($handle, [$label, $status['data'][$i]], ';');
        }
        fputcsv($handle, [''], ';');

        fputcsv($handle, ['=== ACTIVITÉ RÉCENTE ==='], ';');
        fputcsv($handle, ['Titre', 'Entrepreneur', 'Secteur', 'Statut', 'Date soumission', 'Score (%)'], ';');
        foreach ($recentActivity as $p) {
            fputcsv($handle, [
                $p['titre'], $p['entrepreneur'], $p['secteur'] ?? 'N/A', $p['etat'],
                $p['date_soumission'] ? $p['date_soumission']->format('d/m/Y') : 'N/A',
                $p['note_moyenne'] ?? 'N/A'
            ], ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="starthub_statistiques_' . date('Y-m-d_His') . '.csv"',
        ]);
    }
}

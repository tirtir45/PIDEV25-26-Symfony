<?php
namespace App\Service;

use App\Entity\Projets;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function getGlobalStats(): array
    {
        $conn  = $this->em->getConnection();
        $total = (int)$this->em->getRepository(Projets::class)->count([]);

        $byEtat = $conn->executeQuery(
            'SELECT etat, COUNT(*) as n FROM projets GROUP BY etat'
        )->fetchAllKeyValue();

        $acceptes = (int)($byEtat[Projets::ETAT_ACCEPTE]    ?? 0);
        $attente  = (int)($byEtat[Projets::ETAT_EN_ATTENTE] ?? 0);
        $refuses  = (int)($byEtat[Projets::ETAT_REFUSE]     ?? 0);
        $enCours  = (int)($byEtat[Projets::ETAT_EN_COURS]   ?? 0);
        $termines = (int)($byEtat[Projets::ETAT_TERMINE]    ?? 0);

        $entrepreneurs = (int)$conn->executeQuery(
            'SELECT COUNT(u.id_utilisateur)
             FROM utilisateurs u
             INNER JOIN roles r ON u.id_role = r.id_role
             WHERE r.nom_role = :role',
            ['role' => 'Entrepreneur']
        )->fetchOne();

        $avgScore = $conn->executeQuery(
            'SELECT AVG(p.note_moyenne) FROM projets p WHERE p.note_moyenne IS NOT NULL'
        )->fetchOne();

        $thisMonth = (int)$conn->executeQuery(
            'SELECT COUNT(*) FROM projets WHERE YEAR(date_soumission)=YEAR(NOW()) AND MONTH(date_soumission)=MONTH(NOW())'
        )->fetchOne();

        $lastMonth = (int)$conn->executeQuery(
            'SELECT COUNT(*) FROM projets WHERE YEAR(date_soumission)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND MONTH(date_soumission)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH))'
        )->fetchOne();

        $growth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100) : null;

        return [
            'total_projets'          => $total,
            'total_entrepreneurs'    => $entrepreneurs,
            'total_projets_acceptes' => $acceptes,
            'total_projets_attente'  => $attente,
            'total_projets_refuses'  => $refuses,
            'total_en_cours'         => $enCours,
            'total_termines'         => $termines,
            'taux_acceptation'       => $total > 0 ? round(($acceptes / $total) * 100) : 0,
            'taux_refus'             => $total > 0 ? round(($refuses  / $total) * 100) : 0,
            'score_moyen_global'     => $avgScore ? round($avgScore, 1) : null,
            'projets_ce_mois'        => $thisMonth,
            'projets_mois_precedent' => $lastMonth,
            'croissance_mensuelle'   => $growth,
        ];
    }

    public function getProjetsBySecteur(): array
    {
        $conn = $this->em->getConnection();

        $results = $conn->executeQuery(
            'SELECT p.secteur,
                    COUNT(p.id_projet) as total,
                    AVG(p.note_moyenne) as avg_score,
                    SUM(CASE WHEN p.etat = :acc THEN 1 ELSE 0 END) as acceptes
             FROM projets p
             GROUP BY p.secteur
             ORDER BY total DESC',
            ['acc' => Projets::ETAT_ACCEPTE]
        )->fetchAllAssociative();

        $palette = ['#a855f7','#7c3aed','#ec4899','#10b981','#f59e0b','#3b82f6','#ef4444','#84cc16'];
        $labels = $data = $colors = $scores = $taux = [];
        foreach ($results as $i => $r) {
            $labels[] = $r['secteur'];
            $data[]   = (int)$r['total'];
            $colors[] = $palette[$i % count($palette)];
            $scores[] = $r['avg_score'] ? round((float)$r['avg_score'], 1) : null;
            $taux[]   = $r['total'] > 0 ? round(((int)$r['acceptes'] / (int)$r['total']) * 100) : 0;
        }
        return compact('labels', 'data', 'colors', 'scores', 'taux');
    }

    public function getProjetsByStatus(): array
    {
        $conn = $this->em->getConnection();

        $results = $conn->executeQuery(
            'SELECT p.etat, COUNT(p.id_projet) as total FROM projets p GROUP BY p.etat'
        )->fetchAllAssociative();

        $colorMap = [
            Projets::ETAT_EN_ATTENTE => '#f59e0b',
            Projets::ETAT_ACCEPTE    => '#10b981',
            Projets::ETAT_REFUSE     => '#ef4444',
            Projets::ETAT_EN_COURS   => '#a855f7',
            Projets::ETAT_TERMINE    => '#6b7280',
        ];
        $labels = $data = $colors = [];
        foreach ($results as $r) {
            $labels[] = $r['etat'];
            $data[]   = (int)$r['total'];
            $colors[] = $colorMap[$r['etat']] ?? '#9ca3af';
        }
        return compact('labels', 'data', 'colors');
    }

    public function getProjetsEvolution(int $year = null): array
    {
        $year = $year ?? (int)date('Y');
        $conn = $this->em->getConnection();

        $rows = $conn->executeQuery(
            'SELECT MONTH(date_soumission) as mois,
                    COUNT(*) as total,
                    SUM(etat = :acc) as acceptes,
                    SUM(etat = :ref) as refuses
             FROM projets WHERE YEAR(date_soumission) = :year
             GROUP BY mois ORDER BY mois',
            ['year' => $year, 'acc' => Projets::ETAT_ACCEPTE, 'ref' => Projets::ETAT_REFUSE]
        )->fetchAllAssociative();

        $total = $acc = $ref = array_fill(1, 12, 0);
        foreach ($rows as $r) {
            $m = (int)$r['mois'];
            $total[$m] = (int)$r['total'];
            $acc[$m]   = (int)$r['acceptes'];
            $ref[$m]   = (int)$r['refuses'];
        }

        $prevRows = $conn->executeQuery(
            'SELECT MONTH(date_soumission) as mois, COUNT(*) as total
             FROM projets WHERE YEAR(date_soumission) = :year GROUP BY mois',
            ['year' => $year - 1]
        )->fetchAllAssociative();
        $prev = array_fill(1, 12, 0);
        foreach ($prevRows as $r) $prev[(int)$r['mois']] = (int)$r['total'];

        return [
            'labels'   => ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'],
            'data'     => array_values($total),
            'acceptes' => array_values($acc),
            'refuses'  => array_values($ref),
            'prev'     => array_values($prev),
            'year'     => $year,
        ];
    }

    public function getTopEntrepreneurs(int $limit = 8): array
    {
        $conn = $this->em->getConnection();

        $sql = sprintf(
            'SELECT u.nom,
                    COUNT(p.id_projet) as total,
                    SUM(CASE WHEN p.etat = ? THEN 1 ELSE 0 END) as acceptes,
                    AVG(p.note_moyenne) as avg_score
             FROM utilisateurs u
             INNER JOIN projets p ON u.id_utilisateur = p.id_entrepreneur
             GROUP BY u.id_utilisateur, u.nom
             ORDER BY total DESC
             LIMIT %d',
            $limit
        );

        $results = $conn->executeQuery($sql, [Projets::ETAT_ACCEPTE])->fetchAllAssociative();

        $labels = $data = $scores = $taux = [];
        foreach ($results as $r) {
            $labels[] = $r['nom'];
            $data[]   = (int)$r['total'];
            $scores[] = $r['avg_score'] ? round((float)$r['avg_score'], 1) : null;
            $taux[]   = $r['total'] > 0 ? round(((int)$r['acceptes'] / (int)$r['total']) * 100) : 0;
        }
        return compact('labels', 'data', 'scores', 'taux');
    }

    public function getAverageScoreBySecteur(): array
    {
        $conn = $this->em->getConnection();

        $results = $conn->executeQuery(
            'SELECT p.secteur, AVG(p.note_moyenne) as moyenne, COUNT(p.id_projet) as total
             FROM projets p
             WHERE p.note_moyenne IS NOT NULL
             GROUP BY p.secteur
             ORDER BY moyenne DESC'
        )->fetchAllAssociative();

        $labels = $data = $counts = [];
        foreach ($results as $r) {
            $labels[] = $r['secteur'];
            $data[]   = round((float)$r['moyenne'], 1);
            $counts[] = (int)$r['total'];
        }
        return compact('labels', 'data', 'counts');
    }

    public function getMarketScoresBySecteur(): array
    {
        $conn = $this->em->getConnection();

        $results = $conn->executeQuery(
            'SELECT p.secteur, AVG(p.market_score) as avg_market, COUNT(p.id_projet) as total
             FROM projets p
             WHERE p.market_score IS NOT NULL
             GROUP BY p.secteur
             ORDER BY avg_market DESC'
        )->fetchAllAssociative();

        $labels = $data = [];
        foreach ($results as $r) {
            $labels[] = $r['secteur'];
            $data[]   = round((float)$r['avg_market'], 1);
        }
        return compact('labels', 'data');
    }

    public function getRecentActivity(int $limit = 12): array
    {
        $conn = $this->em->getConnection();

        $sql = sprintf(
            'SELECT p.titre, p.etat, p.date_soumission, p.note_moyenne, p.secteur, u.nom as entrepreneur
             FROM projets p
             INNER JOIN utilisateurs u ON p.id_entrepreneur = u.id_utilisateur
             ORDER BY p.date_soumission DESC
             LIMIT %d',
            $limit
        );

        $results = $conn->executeQuery($sql)->fetchAllAssociative();

        foreach ($results as &$result) {
            if ($result['date_soumission']) {
                $result['date_soumission'] = new \DateTime($result['date_soumission']);
            }
        }

        return $results;
    }

    public function getFunnel(): array
    {
        $conn = $this->em->getConnection();

        $total    = (int)$conn->executeQuery('SELECT COUNT(*) FROM projets')->fetchOne();
        $evalues  = (int)$conn->executeQuery('SELECT COUNT(*) FROM projets WHERE note_moyenne IS NOT NULL')->fetchOne();
        $acceptes = (int)$conn->executeQuery('SELECT COUNT(*) FROM projets WHERE etat = :etat', ['etat' => Projets::ETAT_ACCEPTE])->fetchOne();
        $enCours  = (int)$conn->executeQuery('SELECT COUNT(*) FROM projets WHERE etat = :etat', ['etat' => Projets::ETAT_EN_COURS])->fetchOne();
        $termines = (int)$conn->executeQuery('SELECT COUNT(*) FROM projets WHERE etat = :etat', ['etat' => Projets::ETAT_TERMINE])->fetchOne();

        return compact('total', 'evalues', 'acceptes', 'enCours', 'termines');
    }
}

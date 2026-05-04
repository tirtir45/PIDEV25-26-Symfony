<?php

namespace App\Service;

use App\Entity\Projets;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

class GooglePatentsService
{
    private string $apiKey;
    private string $apiUrl = 'https://serpapi.com/search.json';

    private const SECTOR_KEYWORDS = [
        'Technologie' => ['technology', 'software', 'digital', 'AI', 'machine learning', 'IoT'],
        'Sante'       => ['health', 'medical', 'healthcare', 'therapy', 'diagnostic', 'drug'],
        'Finance'     => ['finance', 'payment', 'fintech', 'blockchain', 'banking', 'transaction'],
        'Ecologie'    => ['renewable energy', 'solar', 'wind power', 'sustainable', 'green tech', 'carbon'],
        'Education'   => ['education', 'e-learning', 'training', 'pedagogy', 'learning platform'],
        'Commerce'    => ['ecommerce', 'retail', 'logistics', 'supply chain', 'marketplace'],
        'Artisanat'   => ['manufacturing', 'craft', 'artisan', '3D printing', 'fabrication'],
        'Agriculture' => ['agriculture', 'precision farming', 'crop', 'irrigation', 'agritech'],
        'Santé'       => ['health', 'medical', 'healthcare', 'therapy', 'diagnostic', 'drug'],
        'Écologie'    => ['renewable energy', 'solar', 'wind power', 'sustainable', 'green tech', 'carbon'],
        'Éducation'   => ['education', 'e-learning', 'training', 'pedagogy', 'learning platform'],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        string $serpapiApiKey,
    ) {
        $this->apiKey = $serpapiApiKey;
    }

    public function analyzePatents(Projets $projet): array
    {
        $query = $this->buildQuery($projet);
        $raw   = $this->fetchPatents($query);

        if (!$raw) {
            return $this->defaultResponse($projet->getSecteur(), $query);
        }

        $info    = $raw['search_information'] ?? [];
        $results = $raw['organic_results']    ?? [];
        $summary = $raw['summary']            ?? [];

        $totalResults  = (int)($info['total_results'] ?? 0);
        $recentCount   = $this->countRecent($results, 5);
        $activeCount   = $this->countByStatus($results, 'ACTIVE');

        $topAssignees  = $this->parseSummaryItems($summary['assignee'] ?? []);
        $topInventors  = $this->parseSummaryItems($summary['inventor'] ?? []);
        $topCpc        = $this->parseSummaryItems($summary['cpc']      ?? []);
        $timeline      = $this->buildTimeline($summary['assignee']     ?? []);

        $competitors   = $this->detectCompetitors($results);
        $countries     = $this->aggregateCountries($results);
        $samplePatents = $this->formatSamplePatents($results, 8);

        $noveltyScore  = $this->calcNoveltyScore($totalResults, $recentCount, $competitors);
        $alerts        = $this->buildAlerts($totalResults, $recentCount, $competitors, $noveltyScore);
        $recommendation= $this->buildRecommendation($noveltyScore, $competitors, $totalResults);

        return [
            'sector'             => $projet->getSecteur(),
            'query'              => $query,
            'total_results'      => $totalResults,
            'total_pages'        => (int)($info['total_pages'] ?? 0),
            'recent_5y'          => $recentCount,
            'active_count'       => $activeCount,
            'top_assignees'      => $topAssignees,
            'top_inventors'      => $topInventors,
            'top_cpc'            => $topCpc,
            'timeline'           => $timeline,
            'competitors'        => $competitors,
            'countries'          => $countries,
            'sample_patents'     => $samplePatents,
            'novelty_score'      => $noveltyScore,
            'alerts'             => $alerts,
            'recommendation'     => $recommendation,
            'google_patents_url' => $raw['search_metadata']['google_patents_url'] ?? null,
            'analyzed_at'        => new \DateTime(),
        ];
    }

    private function fetchPatents(string $query): ?array
    {
        $cacheKey = 'patents_v2_' . md5($query);
        return $this->cache->get($cacheKey, function () use ($query) {
            try {
                $after = 'filing:' . (new \DateTime('-10 years'))->format('Ymd');
                $resp  = $this->httpClient->request('GET', $this->apiUrl, [
                    'query'   => ['engine' => 'google_patents', 'q' => $query, 'api_key' => $this->apiKey,
                                  'num' => 50, 'sort' => 'new', 'after' => $after, 'status' => 'GRANT', 'language' => 'ENGLISH'],
                    'timeout' => 20,
                ]);
                if ($resp->getStatusCode() !== 200) { $this->logger->warning('SerpAPI status ' . $resp->getStatusCode()); return null; }
                $data = $resp->toArray(false);
                if (isset($data['error'])) { $this->logger->error('SerpAPI error: ' . $data['error']); return null; }
                return $data;
            } catch (\Exception $e) { $this->logger->error('SerpAPI exception: ' . $e->getMessage()); return null; }
        }, 86400);
    }

    private function buildQuery(Projets $projet): string
    {
        $parts = [];
        $titre = trim($projet->getTitre() ?? '');
        if ($titre) {
            $words = array_filter(explode(' ', $titre), fn($w) => strlen($w) > 3);
            if ($words) $parts[] = '(' . implode(' OR ', array_slice($words, 0, 4)) . ')';
        }
        $sector = $projet->getSecteur() ?? '';
        $kws    = self::SECTOR_KEYWORDS[$sector] ?? [$sector];
        if ($kws) $parts[] = '(' . implode(' OR ', array_slice($kws, 0, 3)) . ')';
        $desc = $projet->getDescription() ?? '';
        if ($desc) {
            $important = $this->extractKeywords($desc, 3);
            if ($important) $parts[] = '(' . implode(' OR ', $important) . ')';
        }
        return implode(' AND ', $parts) ?: $sector;
    }

    private function extractKeywords(string $text, int $limit): array
    {
        $stopWords = ['le','la','les','un','une','des','du','de','et','ou','pour','avec','dans','par','sur','the','a','an','and','of','to','in','for','on','with','by'];
        $text  = strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $text));
        $words = array_filter(explode(' ', $text), fn($w) => strlen($w) > 4 && !in_array($w, $stopWords));
        $freq  = array_count_values($words);
        arsort($freq);
        return array_slice(array_keys($freq), 0, $limit);
    }

    private function countRecent(array $results, int $years): int
    {
        $cutoff = (new \DateTime("-{$years} years"))->format('Y');
        return count(array_filter($results, fn($p) => isset($p['filing_date']) && substr($p['filing_date'], 0, 4) >= $cutoff));
    }

    private function countByStatus(array $results, string $status): int
    {
        $count = 0;
        foreach ($results as $p) {
            foreach ($p['country_status'] ?? [] as $s) { if ($s === $status) { $count++; break; } }
        }
        return $count;
    }

    private function parseSummaryItems(array $items, int $limit = 10): array
    {
        $result = [];
        foreach ($items as $item) {
            if (($item['key'] ?? '') === 'Total') continue;
            $result[] = ['name' => $item['key'], 'percentage' => round((float)($item['percentage'] ?? 0), 1), 'frequency' => $item['frequency'] ?? []];
            if (count($result) >= $limit) break;
        }
        return $result;
    }

    private function buildTimeline(array $assigneeItems): array
    {
        foreach ($assigneeItems as $item) {
            if (($item['key'] ?? '') === 'Total') {
                return array_map(fn($f) => ['range' => $f['year_range'], 'pct' => round((float)$f['percentage'], 1)], $item['frequency'] ?? []);
            }
        }
        return [];
    }

    private function detectCompetitors(array $results): array
    {
        $known = ['Tesla','Apple','Microsoft','Google','Amazon','Samsung','IBM','Intel','Siemens','Bosch','Philips','Sony','LG','Huawei','Qualcomm','BASF','Bayer','Sanofi','Pfizer','Roche','Novartis','TotalEnergies','EDF','Schneider','Veolia','Alibaba','Tencent','Baidu'];
        $found = [];
        foreach ($results as $p) {
            $assignee = $p['assignee'] ?? '';
            foreach ($known as $c) {
                if (stripos($assignee, $c) !== false) { $found[$c] = ($found[$c] ?? 0) + 1; break; }
            }
        }
        arsort($found);
        return array_map(fn($n, $cnt) => ['name' => $n, 'count' => $cnt], array_keys($found), $found);
    }

    private function aggregateCountries(array $results): array
    {
        $countries = [];
        foreach ($results as $p) {
            foreach ($p['country_status'] ?? [] as $code => $status) {
                if (!isset($countries[$code])) $countries[$code] = ['active' => 0, 'inactive' => 0, 'unknown' => 0];
                match($status) { 'ACTIVE' => $countries[$code]['active']++, 'NOT_ACTIVE' => $countries[$code]['inactive']++, default => $countries[$code]['unknown']++ };
            }
        }
        arsort($countries);
        return array_slice($countries, 0, 10, true);
    }

    private function formatSamplePatents(array $results, int $limit): array
    {
        $out = [];
        foreach (array_slice($results, 0, $limit) as $p) {
            $out[] = ['title' => $p['title'] ?? 'Sans titre', 'snippet' => isset($p['snippet']) ? substr($p['snippet'], 0, 200) . '…' : null,
                'inventor' => $p['inventor'] ?? null, 'assignee' => $p['assignee'] ?? null,
                'filing_date' => $p['filing_date'] ?? null, 'grant_date' => $p['grant_date'] ?? null,
                'publication_number' => $p['publication_number'] ?? null, 'patent_link' => $p['patent_link'] ?? null,
                'pdf' => $p['pdf'] ?? null, 'thumbnail' => $p['thumbnail'] ?? null,
                'figures' => array_slice($p['figures'] ?? [], 0, 3), 'country_status' => $p['country_status'] ?? [], 'language' => $p['language'] ?? null];
        }
        return $out;
    }

    private function calcNoveltyScore(int $total, int $recent, array $competitors): int
    {
        $score = 100;
        if ($total > 50000) $score -= 35; elseif ($total > 10000) $score -= 25; elseif ($total > 1000) $score -= 15; elseif ($total > 100) $score -= 5;
        if ($recent < 10) $score += 20; elseif ($recent < 30) $score += 10; elseif ($recent < 50) $score += 5;
        foreach ($competitors as $c) {
            if ($c['count'] > 5) $score -= 15; elseif ($c['count'] > 2) $score -= 8; else $score -= 3;
        }
        return max(0, min(100, $score));
    }

    private function buildAlerts(int $total, int $recent, array $competitors, int $score): array
    {
        $alerts = [];
        if ($total > 50000)      $alerts[] = ['type' => 'danger',  'msg' => "Plus de {$total} brevets. Marché très saturé."];
        elseif ($total > 10000)  $alerts[] = ['type' => 'warning', 'msg' => "{$total} brevets trouvés. Secteur concurrentiel."];
        elseif ($total < 100)    $alerts[] = ['type' => 'success', 'msg' => "Seulement {$total} brevets — fort potentiel de brevetabilité."];
        if ($recent > 30)        $alerts[] = ['type' => 'warning', 'msg' => "{$recent} brevets déposés ces 5 dernières années."];
        elseif ($recent < 5)     $alerts[] = ['type' => 'success', 'msg' => "Très peu de brevets récents ({$recent})."];
        foreach (array_slice($competitors, 0, 3) as $c) $alerts[] = ['type' => 'danger', 'msg' => "{$c['name']} détecté ({$c['count']} brevet(s))."];
        if ($score < 40)         $alerts[] = ['type' => 'danger',  'msg' => "Score de nouveauté très faible ({$score}/100)."];
        elseif ($score >= 75)    $alerts[] = ['type' => 'success', 'msg' => "Score de nouveauté élevé ({$score}/100)."];
        return $alerts;
    }

    private function buildRecommendation(int $score, array $competitors, int $total): string
    {
        $parts = [];
        if ($score >= 75)      $parts[] = "Secteur peu encombré — fort potentiel de brevetabilité.";
        elseif ($score >= 55)  $parts[] = "Marché modérément concurrentiel. Étude de liberté d'exploitation recommandée.";
        elseif ($score >= 35)  $parts[] = "Secteur très concurrentiel. Consultez un conseil en propriété intellectuelle.";
        else                   $parts[] = "Domaine très saturé ({$total} brevets). Différenciez significativement votre concept.";
        if (!empty($competitors)) {
            $names = implode(', ', array_column(array_slice($competitors, 0, 3), 'name'));
            $parts[] = "Acteurs majeurs détectés : {$names}.";
        }
        return implode(' ', $parts);
    }

    private function defaultResponse(string $sector, string $query): array
    {
        return ['sector' => $sector, 'query' => $query, 'total_results' => 0, 'total_pages' => 0,
            'recent_5y' => 0, 'active_count' => 0, 'top_assignees' => [], 'top_inventors' => [], 'top_cpc' => [],
            'timeline' => [], 'competitors' => [], 'countries' => [], 'sample_patents' => [], 'novelty_score' => 50,
            'alerts' => [['type' => 'warning', 'msg' => 'Analyse brevets indisponible.']],
            'recommendation' => 'Analyse indisponible. Effectuez une recherche manuelle sur Google Patents.',
            'google_patents_url' => null, 'analyzed_at' => new \DateTime()];
    }
}

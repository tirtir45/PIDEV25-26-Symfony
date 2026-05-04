<?php

namespace App\Service;

use App\Entity\Projets;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class StartHubAIService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $startHubApiUrl,
        string $startHubApiKey
    ) {
        $this->apiUrl = $startHubApiUrl;
        $this->apiKey = $startHubApiKey;
    }

    public function analyzeProject(Projets $projet): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/api/analyser', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'titre'       => $projet->getTitre(),
                    'description' => $projet->getDescription(),
                    'objectifs'   => $projet->getObjectifs(),
                    'secteur'     => $projet->getSecteur(),
                    'budget'      => $projet->getBudgetEstime(),
                    'duree'       => $projet->getDureeEstimee(),
                    'equipe'      => $projet->getNbMembresEquipe(),
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException("API returned status " . $response->getStatusCode());
            }

            $analyseData = $response->toArray();
            $metadata    = $analyseData['metadata'] ?? [];
            $swot        = $metadata['swot'] ?? [];

            $pitchData = $this->httpClient->request('POST', $this->apiUrl . '/api/pitch', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['titre' => $projet->getTitre(), 'description' => $projet->getDescription(),
                              'objectifs' => $projet->getObjectifs(), 'secteur' => $projet->getSecteur()],
                'timeout' => 30,
            ])->toArray();
            $pitch = $pitchData['result'] ?? '';

            $siteWebData = $this->httpClient->request('POST', $this->apiUrl . '/api/site_web', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['titre' => $projet->getTitre(), 'description' => $projet->getDescription(),
                              'objectifs' => $projet->getObjectifs(), 'secteur' => $projet->getSecteur()],
                'timeout' => 30,
            ])->toArray();
            $siteWeb = $siteWebData['result']['html'] ?? '';

            $ameliorationsData = $this->httpClient->request('POST', $this->apiUrl . '/api/ameliorations', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['titre' => $projet->getTitre(), 'description' => $projet->getDescription(),
                              'secteur' => $projet->getSecteur()],
                'timeout' => 30,
            ])->toArray();
            $ameliorations = $ameliorationsData['suggestions'] ?? [];

            $kpiData = $this->httpClient->request('POST', $this->apiUrl . '/api/kpi', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['titre' => $projet->getTitre(), 'secteur' => $projet->getSecteur()],
                'timeout' => 30,
            ])->toArray();
            $kpis = $kpiData['kpis'] ?? [];

            $personaData = $this->httpClient->request('POST', $this->apiUrl . '/api/persona', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['titre' => $projet->getTitre(), 'secteur' => $projet->getSecteur()],
                'timeout' => 30,
            ])->toArray();
            $personas = $personaData['personas'] ?? [];

            $comparerData = $this->httpClient->request('POST', $this->apiUrl . '/api/comparer', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['titre' => $projet->getTitre(), 'description' => $projet->getDescription(),
                              'secteur' => $projet->getSecteur()],
                'timeout' => 30,
            ])->toArray();
            $projetsSimilaires = $comparerData['result']['similaires'] ?? [];

            $marketData = $this->httpClient->request('POST', $this->apiUrl . '/api/market', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['titre' => $projet->getTitre(), 'secteur' => $projet->getSecteur()],
                'timeout' => 30,
            ])->toArray();
            $marketSize = $marketData['metadata']['market_size'] ?? [];

            $scoreCompletude = $metadata['score_completude'] ?? 50;
            $scoreGlobal     = min(100, $scoreCompletude + 10);

            $nbForces      = count($swot['forces']      ?? []);
            $nbFaiblesses  = count($swot['faiblesses']  ?? []);
            $nbOpportunites= count($swot['opportunites']?? []);

            $scoreInnovation  = min(100, 50 + ($nbForces * 10));
            $scoreFaisabilite = max(30,  100 - ($nbFaiblesses * 15));
            $scoreMarche      = min(100, 50 + ($nbOpportunites * 10));
            $scoreEquipe      = $projet->getNbMembresEquipe() ? min(100, $projet->getNbMembresEquipe() * 15) : 50;

            return [
                'score_global'       => $scoreGlobal,
                'score_innovation'   => $scoreInnovation,
                'score_faisabilite'  => $scoreFaisabilite,
                'score_marche'       => $scoreMarche,
                'score_equipe'       => $scoreEquipe,
                'forces'             => $swot['forces']       ?? [],
                'faiblesses'         => $swot['faiblesses']   ?? [],
                'opportunites'       => $swot['opportunites'] ?? [],
                'menaces'            => $swot['menaces']      ?? [],
                'recommandations'    => array_slice(array_map(fn($a) => $a['suggestion'] ?? '', $ameliorations), 0, 5),
                'ameliorations'      => $ameliorations,
                'kpis'               => $kpis,
                'personas'           => $personas,
                'projets_similaires' => $projetsSimilaires,
                'pitch'              => $pitch,
                'site_web_html'      => $siteWeb,
                'market_size'        => $marketSize,
                'secteur_detecte'    => $metadata['secteur_detecte'] ?? $projet->getSecteur(),
                'mots_cles'          => $metadata['mots_cles'] ?? [],
                'maturite'           => $metadata['maturite'] ?? 'Idée préliminaire',
                'analyzed_at'        => (new \DateTime())->format(\DateTime::ATOM),
            ];

        } catch (\Exception $e) {
            $this->logger->error('StartHub AI API error: ' . $e->getMessage());
            throw new \RuntimeException('Analyse IA indisponible: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/api/health', ['timeout' => 5]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}

<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReclamationAnalyzerService
{
    private const HF_API_URL = 'https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.3';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $huggingFaceToken
    ) {}

    /**
     * Analyse le texte d'une réclamation et retourne catégorie, sentiment et priorité.
     *
     * @return array{categorie: string, sentiment: string, priorite: string}
     */
    public function analyze(string $text): array
    {
        $prompt = <<<PROMPT
Analyse cette réclamation et réponds UNIQUEMENT en JSON valide avec exactement ces 3 clés :
- "categorie" : une parmi [paiement, technique, service, livraison, autre]
- "sentiment" : un parmi [positif, négatif, neutre]
- "priorite" : une parmi [LOW, MEDIUM, HIGH]

Réclamation : "{$text}"

Réponds uniquement avec le JSON, sans explication.
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::HF_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingFaceToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'inputs'     => $prompt,
                    'parameters' => [
                        'max_new_tokens'  => 100,
                        'temperature'     => 0.1,
                        'return_full_text' => false,
                    ],
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            $generated = $data[0]['generated_text'] ?? '';

            return $this->parseResponse($generated);

        } catch (\Throwable $e) {
            // Fallback si l'API est indisponible
            return $this->fallbackAnalysis($text);
        }
    }

    /**
     * Parse la réponse JSON du modèle.
     */
    private function parseResponse(string $raw): array
    {
        // Extraire le JSON de la réponse (le modèle peut ajouter du texte autour)
        if (preg_match('/\{[^}]+\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return [
                    'categorie' => $this->normalizeCategorie($decoded['categorie'] ?? 'autre'),
                    'sentiment' => $this->normalizeSentiment($decoded['sentiment'] ?? 'neutre'),
                    'priorite'  => $this->normalizePriorite($decoded['priorite'] ?? 'MEDIUM'),
                ];
            }
        }

        return $this->fallbackAnalysis($raw);
    }

    /**
     * Analyse locale basique si l'API échoue.
     */
    private function fallbackAnalysis(string $text): array
    {
        $text = mb_strtolower($text);

        // Catégorie
        $categorie = 'autre';
        if (str_contains($text, 'paiement') || str_contains($text, 'facture') || str_contains($text, 'remboursement')) {
            $categorie = 'paiement';
        } elseif (str_contains($text, 'lent') || str_contains($text, 'bug') || str_contains($text, 'erreur') || str_contains($text, 'technique')) {
            $categorie = 'technique';
        } elseif (str_contains($text, 'service') || str_contains($text, 'accueil') || str_contains($text, 'support')) {
            $categorie = 'service';
        } elseif (str_contains($text, 'livraison') || str_contains($text, 'colis') || str_contains($text, 'retard')) {
            $categorie = 'livraison';
        }

        // Sentiment
        $negativeWords = ['problème', 'mauvais', 'lent', 'horrible', 'nul', 'insatisfait', 'déçu', 'urgent'];
        $positiveWords = ['bien', 'excellent', 'parfait', 'satisfait', 'merci', 'super'];
        $sentiment = 'neutre';
        foreach ($negativeWords as $w) {
            if (str_contains($text, $w)) { $sentiment = 'négatif'; break; }
        }
        if ($sentiment === 'neutre') {
            foreach ($positiveWords as $w) {
                if (str_contains($text, $w)) { $sentiment = 'positif'; break; }
            }
        }

        // Priorité
        $priorite = 'MEDIUM';
        if ($sentiment === 'négatif' && ($categorie === 'paiement' || str_contains($text, 'urgent'))) {
            $priorite = 'HIGH';
        } elseif ($sentiment === 'positif') {
            $priorite = 'LOW';
        }

        return compact('categorie', 'sentiment', 'priorite');
    }

    private function normalizeCategorie(string $v): string
    {
        $v = mb_strtolower(trim($v));
        return in_array($v, ['paiement', 'technique', 'service', 'livraison']) ? $v : 'autre';
    }

    private function normalizeSentiment(string $v): string
    {
        $v = mb_strtolower(trim($v));
        $map = ['negatif' => 'négatif', 'négatif' => 'négatif', 'positif' => 'positif', 'neutre' => 'neutre'];
        return $map[$v] ?? 'neutre';
    }

    private function normalizePriorite(string $v): string
    {
        $v = mb_strtoupper(trim($v));
        return in_array($v, ['LOW', 'MEDIUM', 'HIGH']) ? $v : 'MEDIUM';
    }
}

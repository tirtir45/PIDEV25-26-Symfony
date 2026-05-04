<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class HuggingFaceService
{
    private $client;
    private $apiKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        // Utiliser $_ENV est possible, mais assurez-vous que la clé existe
        $this->apiKey = $_ENV['HUGGINGFACE_API_KEY1'] ?? null;
    }

   public function getSimilarityScore($text1, $text2)
{
    // 1. Nettoyage strict des textes
    $cleanText1 = $this->prepareText($text1);
    $cleanText2 = $this->prepareText($text2);

    if (empty($cleanText1) || empty($cleanText2)) {
        return 0;
    }

    $modelId = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2";
    
    $response = $this->client->request(
        'POST',
        'https://api-inference.huggingface.co/models/' . $modelId,
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'inputs' => [
                    'source_sentence' => $cleanText1,
                    'sentences' => [$cleanText2]
                ],
                'options' => ['wait_for_model' => true]
            ]
        ]
    );

    if ($response->getStatusCode() !== 200) {
        // Si ça échoue encore, on retourne 0 au lieu de faire planter
        return 0;
    }

    $data = $response->toArray();
    return $data[0] ?? 0;
}

// Fonction pour nettoyer le texte (enlever les sauts de ligne, les balises, etc.)
private function prepareText(string $text): string
{
    $text = strip_tags($text); // Enlever HTML
    $text = str_replace(["\r", "\n"], ' ', $text); // Enlever sauts de ligne
    $text = preg_replace('/\s+/', ' ', $text); // Enlever espaces multiples
    return mb_substr(trim($text), 0, 1000); // Limiter à 1000 caractères pour l'API
}
}
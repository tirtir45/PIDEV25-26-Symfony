<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqTaskGenerator
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');
        if (!$this->apiKey) {
            throw new \Exception('GROQ_API_KEY not configured in .env');
        }
    }

    /**
     * Génère des tâches à partir d'une description de projet
     *
     * @param string $projectDescription
     * @param int $count
     * @return array
     * @throws \Exception
     */
    public function suggestTasks(string $projectDescription, int $count = 8): array
    {
        $prompt = sprintf(
            'Tu es un expert en gestion de projets agiles.
            Génère %d tâches concrètes pour ce projet : %s

            Réponds UNIQUEMENT avec un JSON valide (sans texte avant ou après) au format suivant :
            [{"titre": "...", "description": "...", "priorite": "haute|moyenne|basse", "categorie": "frontend|backend|design|test|devops", "duree_estimee": "..."}]
            ',
            $count,
            $projectDescription
        );

        $response = $this->httpClient->request('POST', $this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un assistant qui génère des tâches projet au format JSON uniquement.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ],
        ]);

        $data = $response->toArray();
        $raw = $data['choices'][0]['message']['content'] ?? '';

        // Nettoyage du JSON (supprime les balises markdown)
        $cleaned = preg_replace('/^```json\s*|\s*```$/', '', $raw);
        $tasks = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erreur de parsing JSON : ' . json_last_error_msg());
        }

        return $this->validateTasks($tasks);
    }

    private function validateTasks(array $tasks): array
    {
        $validated = [];
        foreach ($tasks as $task) {
            if (!isset($task['titre'])) {
                continue;
            }
            $validated[] = [
                'titre' => trim($task['titre']),
                'description' => $task['description'] ?? '',
                'priorite' => $task['priorite'] ?? 'moyenne',
                'categorie' => $task['categorie'] ?? 'general',
                'duree_estimee' => $task['duree_estimee'] ?? 'Non spécifié',
            ];
        }
        return $validated;
    }
}
<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class VisionService
{
    private const HF_MODEL_URL = 'https://api-inference.huggingface.co/models/openai/clip-vit-large-patch14';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $hfApiKey = null
    ) {
    }

    /**
     * Analyzes an image and returns probability scores for various categories.
     */
    public function analyzeImage(string $imagePath, string $expectedCategory): array
    {
        if (empty($this->hfApiKey) || !file_exists($imagePath)) {
            return $this->simulateAnalysis($imagePath, $expectedCategory);
        }

        try {
            $imageContent = file_get_contents($imagePath);
            $candidateLabels = $this->getLabelsForCategory($expectedCategory);
            $forbiddenLabels = ['weapon', 'drug', 'explicit content', 'toxic'];
            
            $allLabels = array_merge($candidateLabels, $forbiddenLabels);

            $response = $this->httpClient->request('POST', self::HF_MODEL_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->hfApiKey,
                ],
                'json' => [
                    'inputs' => base64_encode($imageContent),
                    'parameters' => [
                        'candidate_labels' => $allLabels
                    ]
                ]
            ]);

            $result = $response->toArray();
            
            // Transform result for easier logic
            $scores = array_combine($result['labels'], $result['scores']);
            
            return $this->processResults($scores, $candidateLabels, $forbiddenLabels);
            
        } catch (\Exception $e) {
            $this->logger->error("HuggingFace Vision Error: " . $e->getMessage());
            return $this->simulateAnalysis($imagePath, $expectedCategory);
        }
    }

    private function getLabelsForCategory(string $category): array
    {
        return match (mb_strtolower($category)) {
            'local'      => ['office', 'room', 'building interior', 'real estate', 'desk'],
            'matériel', 'equipement' => ['equipment', 'computer', 'electronic device', 'hardware', 'tool'],
            'service'    => ['professional service', 'business meeting', 'handshake', 'consulting', 'icon'],
            default      => ['item', 'object']
        };
    }

    private function processResults(array $scores, array $expectedLabels, array $forbiddenLabels): array
    {
        $maxExpected = 0;
        foreach ($expectedLabels as $label) {
            $maxExpected = max($maxExpected, $scores[$label] ?? 0);
        }

        $maxForbidden = 0;
        foreach ($forbiddenLabels as $label) {
            $maxForbidden = max($maxForbidden, $scores[$label] ?? 0);
        }

        return [
            'match_score' => $maxExpected * 100,
            'forbidden_score' => $maxForbidden * 100,
            'is_mismatch' => $maxExpected < 0.15, // Threshold for mismatch
            'is_dangerous' => $maxForbidden > 0.60,
            'detected_labels' => array_keys(array_slice($scores, 0, 3, true))
        ];
    }

    /**
     * Mocks analysis for local testing or when no API key is present.
     */
    private function simulateAnalysis(string $imagePath, string $expectedCategory): array
    {
        $filename = mb_strtolower(basename($imagePath));
        
        // Very basic simulation based on filename for demo purposes
        $isMatch = str_contains($filename, mb_strtolower($expectedCategory)) || str_contains($filename, 'test');
        $isDanger = str_contains($filename, 'drug') || str_contains($filename, 'weapon');

        return [
            'match_score' => $isMatch ? 95 : 5,
            'forbidden_score' => $isDanger ? 90 : 2,
            'is_mismatch' => !$isMatch,
            'is_dangerous' => $isDanger,
            'detected_labels' => $isMatch ? [$expectedCategory, 'professional'] : ['unknown object'],
            'simulation' => true
        ];
    }
}

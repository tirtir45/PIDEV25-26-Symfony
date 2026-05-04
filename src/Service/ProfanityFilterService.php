<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\TranslationService;

class ProfanityFilterService
{
    // Modèle de classification de toxicité sur HuggingFace
    private const HF_URL = 'https://api-inference.huggingface.co/models/unitary/toxic-bert';
    private const TOXICITY_THRESHOLD = 0.7;

    // Liste de mots offensants multilingue (fallback si API indisponible)
    private const BAD_WORDS = [
        // Français
        'idiot', 'idiote', 'imbécile', 'connard', 'connasse', 'con', 'conne',
        'merde', 'putain', 'salaud', 'salope', 'abruti', 'abrutie', 'crétin',
        'crétine', 'nul', 'nulle', 'stupide', 'débile', 'enculé', 'bâtard',
        'bâtarde', 'ordure', 'porc', 'cochon', 'dégueulasse', 'salopard',
        'va te faire', 'ferme ta gueule', 'ta gueule',

        // Anglais
        'fuck', 'fucking', 'shit', 'asshole', 'bastard', 'bitch', 'damn',
        'crap', 'stupid', 'idiot', 'moron', 'dumbass', 'dickhead', 'cunt',
        'prick', 'wanker', 'twat', 'retard', 'loser', 'scum', 'trash',

        // Arabe (translittération et arabe)
        'كلب', 'حمار', 'غبي', 'احمق', 'منيوك', 'عاهرة', 'شرموطة',
        'يلعن', 'ابن الكلب', 'ابن الحرام', 'كس', 'زبالة', 'حقير',
        'kalb', 'hmar', 'ghabi', 'ahmar', 'sharmouta', 'ibn el kalb',

        // Espagnol
        'idiota', 'estupido', 'mierda', 'puta', 'cabron', 'pendejo',
        'imbecil', 'gilipollas', 'coño', 'joder',

        // Italien
        'cazzo', 'stronzo', 'vaffanculo', 'idiota', 'coglione',

        // Allemand
        'scheiße', 'idiot', 'dummkopf', 'arschloch', 'wichser',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $huggingFaceToken,
        private readonly TranslationService $translator
    ) {}

    /**
     * Vérifie si le texte contient du contenu toxique.
     * Retourne null si OK, ou un message d'erreur si toxique.
     */
    public function check(string $text): ?string
    {
        // 1. Vérification rapide par liste de mots (multilingue)
        if ($this->containsBadWords($text)) {
            return 'Votre message contient des mots inappropriés, veuillez le corriger.';
        }

        // 2. Traduire en anglais pour l'analyse API (meilleure détection)
        try {
            $translated = $this->translator->translate($text, 'en');
            $textToAnalyze = $translated['translated'] ?? $text;
        } catch (\Throwable) {
            $textToAnalyze = $text;
        }

        // 3. Vérification via API HuggingFace sur le texte traduit
        $score = $this->getToxicityScore($textToAnalyze);
        if ($score !== null && $score >= self::TOXICITY_THRESHOLD) {
            return 'Votre message contient des mots inappropriés, veuillez le corriger.';
        }

        return null;
    }

    /**
     * Retourne le score de toxicité (0.0 à 1.0) ou null si API indisponible.
     */
    public function getToxicityScore(string $text): ?float
    {
        if (empty($this->huggingFaceToken)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::HF_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingFaceToken,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => ['inputs' => $text],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            // Le modèle retourne [[{label: 'toxic', score: 0.x}, ...]]
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data[0] as $result) {
                    if (isset($result['label']) && strtolower($result['label']) === 'toxic') {
                        return (float) $result['score'];
                    }
                }
            }
        } catch (\Throwable) {
            // API indisponible — on se fie uniquement à la liste de mots
        }

        return null;
    }

    private function containsBadWords(string $text): bool
    {
        $lower = mb_strtolower($text);
        foreach (self::BAD_WORDS as $word) {
            if (str_contains($lower, mb_strtolower($word))) {
                return true;
            }
        }
        return false;
    }
}

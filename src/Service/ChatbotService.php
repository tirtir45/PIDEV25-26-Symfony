<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatbotService
{
    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
    private const HF_API_URL = 'https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.3';

    private const RESPONSES = [
        'connexion' => "Nous avons bien reçu votre réclamation concernant un problème de connexion. Veuillez vérifier vos identifiants ou utiliser la fonction \"Mot de passe oublié\". Si le problème persiste, notre équipe vous contactera dans les plus brefs délais.",
        'paiement'  => "Votre réclamation concernant un problème de paiement a été enregistrée. Nous vérifions votre transaction et vous tiendrons informé sous 24h. En cas d'urgence, contactez directement notre support.",
        'technique' => "Nous avons bien reçu votre signalement technique. Notre équipe analyse le problème et travaille à le résoudre rapidement. Vous recevrez une mise à jour dès que possible.",
        'livraison' => "Votre réclamation concernant la livraison a été prise en compte. Nous contactons le service logistique et vous informerons du statut de votre commande dans les 24h.",
        'service'   => "Nous sommes désolés pour cette expérience insatisfaisante. Votre réclamation a été transmise à notre équipe qualité qui prendra contact avec vous rapidement.",
        'autre'     => "Votre réclamation a bien été enregistrée et sera traitée par notre équipe dans les meilleurs délais. Nous vous remercions de votre patience et vous tiendrons informé de l'avancement.",
    ];

    private const KEYWORDS = [
        'connexion' => ['connexion', 'connecter', 'login', 'mot de passe', 'password', 'compte', 'accès', 'identifiant'],
        'paiement'  => ['paiement', 'payer', 'facture', 'remboursement', 'transaction', 'carte', 'virement', 'argent'],
        'technique' => ['bug', 'erreur', 'lent', 'plantage', 'crash', 'technique', 'fonctionne pas', 'page blanche'],
        'livraison' => ['livraison', 'colis', 'retard', 'commande', 'expédition', 'reçu', 'livré'],
        'service'   => ['service', 'accueil', 'support', 'réponse', 'attente', 'insatisfait', 'déçu', 'mauvais'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $huggingFaceToken,
        private readonly string $openAiApiKey = ''
    ) {}

    /**
     * Génère une réponse automatique professionnelle.
     * Priorité : OpenAI → HuggingFace → Règles
     */
    public function generateResponse(string $text): string
    {
        // HuggingFace Mistral (gratuit)
        $hfResponse = $this->callHuggingFace($text);
        if ($hfResponse) {
            return $hfResponse;
        }

        // Fallback règles
        return $this->ruleBasedResponse($text);
    }

    /**
     * Appel à l'API OpenAI GPT-4o-mini.
     */
    private function callOpenAI(string $text): ?string
    {
        if (empty($this->openAiApiKey)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::OPENAI_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'Tu es un assistant support client professionnel pour StartHub. Génère une réponse courte (2-3 phrases maximum), empathique et professionnelle en français. Propose une solution concrète et rassure le client.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => "Réclamation client : \"{$text}\"",
                        ],
                    ],
                    'max_tokens'  => 150,
                    'temperature' => 0.5,
                ],
                'timeout' => 20,
            ]);

            $data = $response->toArray();
            $generated = trim($data['choices'][0]['message']['content'] ?? '');

            if (strlen($generated) > 20) {
                return $generated;
            }
        } catch (\Throwable) {
            // Fallback vers HuggingFace
        }

        return null;
    }

    /**
     * Appel à l'API Hugging Face.
     */
    private function callHuggingFace(string $text): ?string
    {
        if (empty($this->huggingFaceToken)) {
            return null;
        }

        $prompt = "Tu es un assistant support client professionnel. Génère une réponse automatique courte (2-3 phrases maximum) et professionnelle en français pour cette réclamation client.\n\nRéclamation : \"{$text}\"\n\nRéponse :";

        try {
            $response = $this->httpClient->request('POST', self::HF_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingFaceToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'inputs'     => $prompt,
                    'parameters' => ['max_new_tokens' => 120, 'temperature' => 0.4, 'return_full_text' => false],
                ],
                'timeout' => 5, // réduit à 5s
            ]);

            $data = $response->toArray();
            $generated = trim($data[0]['generated_text'] ?? '');

            if (strlen($generated) > 20 && strlen($generated) < 600) {
                $sentences = preg_split('/(?<=[.!?])\s+/', $generated);
                return implode(' ', array_slice($sentences, 0, 3));
            }
        } catch (\Throwable) {}

        return null;
    }

    private function ruleBasedResponse(string $text): string
    {
        $text = mb_strtolower($text);

        foreach (self::KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return self::RESPONSES[$category];
                }
            }
        }

        // Réponse personnalisée basée sur le sentiment du texte
        $negativeWords = ['problème', 'mauvais', 'lent', 'bug', 'erreur', 'déçu', 'insatisfait', 'urgent'];
        foreach ($negativeWords as $word) {
            if (str_contains($text, $word)) {
                return "Nous sommes sincèrement désolés pour les désagréments rencontrés. Votre réclamation a été transmise en priorité à notre équipe qui vous contactera dans les plus brefs délais pour résoudre ce problème.";
            }
        }

        return self::RESPONSES['autre'];
    }
}

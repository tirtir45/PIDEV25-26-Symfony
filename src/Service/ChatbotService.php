<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatbotService
{
    private const HF_API_URL = 'https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.3';

    // Réponses prédéfinies par catégorie
    private const RESPONSES = [
        'connexion' => "Nous avons bien reçu votre réclamation concernant un problème de connexion. Veuillez vérifier vos identifiants ou utiliser la fonction \"Mot de passe oublié\". Si le problème persiste, notre équipe vous contactera dans les plus brefs délais.",
        'paiement'  => "Votre réclamation concernant un problème de paiement a été enregistrée. Nous vérifions votre transaction et vous tiendrons informé sous 24h. En cas d'urgence, contactez directement notre support.",
        'technique' => "Nous avons bien reçu votre signalement technique. Notre équipe analyse le problème et travaille à le résoudre rapidement. Vous recevrez une mise à jour dès que possible.",
        'livraison' => "Votre réclamation concernant la livraison a été prise en compte. Nous contactons le service logistique et vous informerons du statut de votre commande dans les 24h.",
        'service'   => "Nous sommes désolés pour cette expérience insatisfaisante. Votre réclamation a été transmise à notre équipe qualité qui prendra contact avec vous rapidement.",
        'autre'     => "Votre réclamation a bien été enregistrée et sera traitée par notre équipe dans les meilleurs délais. Nous vous remercions de votre patience et vous tiendrons informé de l'avancement.",
    ];

    // Mots-clés pour détecter la catégorie
    private const KEYWORDS = [
        'connexion' => ['connexion', 'connecter', 'login', 'mot de passe', 'password', 'compte', 'accès', 'identifiant', 'authentification'],
        'paiement'  => ['paiement', 'payer', 'facture', 'remboursement', 'transaction', 'carte', 'virement', 'argent', 'prix'],
        'technique' => ['bug', 'erreur', 'lent', 'plantage', 'crash', 'technique', 'fonctionne pas', 'problème technique', 'page blanche'],
        'livraison' => ['livraison', 'colis', 'retard', 'commande', 'expédition', 'reçu', 'livré'],
        'service'   => ['service', 'accueil', 'support', 'réponse', 'attente', 'insatisfait', 'déçu', 'mauvais'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $huggingFaceToken
    ) {}

    /**
     * Génère une réponse automatique pour une réclamation.
     */
    public function generateResponse(string $text): string
    {
        // Essayer d'abord l'API HF
        $aiResponse = $this->callHuggingFace($text);
        if ($aiResponse) {
            return $aiResponse;
        }

        // Fallback : règles basées sur mots-clés
        return $this->ruleBasedResponse($text);
    }

    /**
     * Appel à l'API Hugging Face.
     */
    private function callHuggingFace(string $text): ?string
    {
        if (empty($this->huggingFaceToken) || $this->huggingFaceToken === 'your_hugging_face_token_here') {
            return null;
        }

        $prompt = "Tu es un assistant support client professionnel. Génère une réponse automatique courte (2-3 phrases maximum) et professionnelle en français pour cette réclamation client. Sois empathique et propose une solution concrète.\n\nRéclamation : \"{$text}\"\n\nRéponse :";

        try {
            $response = $this->httpClient->request('POST', self::HF_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingFaceToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'inputs'     => $prompt,
                    'parameters' => [
                        'max_new_tokens'   => 120,
                        'temperature'      => 0.4,
                        'return_full_text' => false,
                    ],
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray();
            $generated = trim($data[0]['generated_text'] ?? '');

            // Valider que la réponse est utilisable
            if (strlen($generated) > 20 && strlen($generated) < 600) {
                // Couper à la 3ème phrase max
                $sentences = preg_split('/(?<=[.!?])\s+/', $generated);
                return implode(' ', array_slice($sentences, 0, 3));
            }
        } catch (\Throwable) {
            // Silently fall through to rule-based
        }

        return null;
    }

    /**
     * Réponse basée sur des règles (mots-clés).
     */
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

        return self::RESPONSES['autre'];
    }
}

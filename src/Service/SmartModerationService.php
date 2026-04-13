<?php

namespace App\Service;

use App\Entity\Ressources;

class SmartModerationService
{
    /**
     * Categories with forbidden patterns and their associated weight (0-100).
     */
    private array $rules = [
        'scam' => [
            'weight' => 40,
            'patterns' => [
                'argent facile', 'gagner vite', 'richissime', 'investissement miracle', 
                'telegram @', 'whatsapp @', 'crypto-monnaie illégal', 'arnaque', 
                'get rich fast', 'easy money', 'no risk', 'guaranteed profit'
            ]
        ],
        'illegal' => [
            'weight' => 60,
            'patterns' => [
                'drogue', 'cannabis', 'arme', 'pistolet', 'munitions', 'faux papiers', 
                'carte d\'identité volée', 'piratage', 'hacked account', 'stolen', 
                'fake identity', 'illegal weapon', 'cocaine', 'crack'
            ]
        ],
        'adult' => [
            'weight' => 50,
            'patterns' => [
                'porno', 'sexuel', 'escorte', 'nude', 'adult content', 'sexy service'
            ]
        ],
        'spam' => [
            'weight' => 20,
            'patterns' => [
                'cliquez ici', 'offre exclusive 99%', 'promo incroyable', 'spam', 'adware'
            ]
        ]
    ];

    /**
     * Analyzes a resource and returns a status array.
     */
    public function analyze(Ressources $ressource): array
    {
        $text = mb_strtolower($ressource->getNom() . ' ' . $ressource->getDescription());
        $score = 0;
        $flags = [];

        foreach ($this->rules as $category => $data) {
            foreach ($data['patterns'] as $pattern) {
                if (str_contains($text, mb_strtolower($pattern))) {
                    $score += $data['weight'];
                    $flags[] = ucfirst($category) . " (" . $pattern . ")";
                    // Limit score to 100
                    if ($score > 100) $score = 100;
                    break; // Move to next category once one match is found
                }
            }
        }

        return [
            'score' => $score,
            'flags' => array_unique($flags),
            'is_risky' => $score >= 50,
            'is_dangerous' => $score >= 80
        ];
    }

    /**
     * Auto-moderate a resource.
     */
    public function moderate(Ressources $ressource): void
    {
        $result = $this->analyze($ressource);
        $ressource->setModerationScore($result['score']);
        
        if (!empty($result['flags'])) {
            $ressource->setModerationReason(implode(', ', $result['flags']));
        }

        // Auto-ban if dangerous
        if ($result['is_dangerous']) {
            $ressource->setIsBanned(true);
        }
    }
}

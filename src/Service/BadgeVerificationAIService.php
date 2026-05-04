<?php

namespace App\Service;

use App\Entity\Utilisateurs;
use App\Repository\ReclamationsRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BadgeVerificationAIService
{
    private const HF_URL = 'https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.3';

    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly ReclamationsRepository $reclamRepo,
        private readonly string                 $huggingFaceToken
    ) {}

    /**
     * Analyse la demande de badge et retourne un score + décision.
     *
     * @return array{score: int, decision: string, badge: string, explication: string}
     */
    public function analyze(Utilisateurs $user, string $raisonDemande): array
    {
        $data = $this->collectUserData($user);

        // 1. Essayer l'IA HuggingFace
        $aiResult = $this->callAI($data, $raisonDemande);
        if ($aiResult) {
            return $aiResult;
        }

        // 2. Fallback : calcul local du score
        return $this->localScoring($data);
    }

    /**
     * Collecte les données de l'utilisateur.
     */
    private function collectUserData(Utilisateurs $user): array
    {
        $daysSinceRegistration = $user->getDateInscription()
            ? (new \DateTime())->diff($user->getDateInscription())->days
            : 0;

        $resolvedCount = (int) $this->reclamRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.utilisateur = :u')->andWhere('r.statut = :s')
            ->setParameter('u', $user)->setParameter('s', 'RESOLU')
            ->getQuery()->getSingleScalarResult();

        $rejectedCount = (int) $this->reclamRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.utilisateur = :u')->andWhere('r.statut = :s')
            ->setParameter('u', $user)->setParameter('s', 'REJETE')
            ->getQuery()->getSingleScalarResult();

        $totalCount = (int) $this->reclamRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.utilisateur = :u')
            ->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();

        return [
            'email_verifie'    => !empty($user->getEmail()),
            'telephone_verifie'=> !empty($user->getTelephone()),
            'photo'            => !empty($user->getPhoto()),
            'bio'              => !empty($user->getBio()),
            'anciennete_jours' => $daysSinceRegistration,
            'reclamations_resolues' => $resolvedCount,
            'reclamations_rejetees' => $rejectedCount,
            'reclamations_total'    => $totalCount,
            'actif'            => $user->isActif(),
            'role'             => $user->getRole()?->getNomRole() ?? 'Utilisateur',
        ];
    }

    /**
     * Appel à l'IA HuggingFace.
     */
    private function callAI(array $data, string $raison): ?array
    {
        if (empty($this->huggingFaceToken)) return null;

        $prompt = <<<PROMPT
Tu es un système d'analyse de fiabilité pour une plateforme startup. Analyse ces données utilisateur et retourne UNIQUEMENT un JSON valide.

Données utilisateur :
- Email vérifié : {$this->bool($data['email_verifie'])}
- Téléphone vérifié : {$this->bool($data['telephone_verifie'])}
- Photo de profil : {$this->bool($data['photo'])}
- Bio renseignée : {$this->bool($data['bio'])}
- Ancienneté : {$data['anciennete_jours']} jours
- Réclamations résolues : {$data['reclamations_resolues']}
- Réclamations rejetées : {$data['reclamations_rejetees']}
- Total réclamations : {$data['reclamations_total']}
- Compte actif : {$this->bool($data['actif'])}
- Rôle : {$data['role']}
- Message de demande : "{$raison}"

Règles de décision :
- Score 80-100 → ACCEPT (badge VERIFIED accordé)
- Score 50-79 → REVIEW (validation admin requise)
- Score < 50 → REJECT (badge refusé)
- Un comportement abusif (beaucoup de rejets) → REJECT automatique

Réponds UNIQUEMENT avec ce JSON :
{"score": 75, "decision": "REVIEW", "badge": "NONE", "explication": "..."}
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::HF_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingFaceToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'inputs'     => $prompt,
                    'parameters' => ['max_new_tokens' => 200, 'temperature' => 0.1, 'return_full_text' => false],
                ],
                'timeout' => 15,
            ]);

            $raw = trim($response->toArray()[0]['generated_text'] ?? '');

            if (preg_match('/\{[^}]+\}/s', $raw, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (is_array($decoded) && isset($decoded['score'], $decoded['decision'])) {
                    return $this->normalizeResult($decoded);
                }
            }
        } catch (\Throwable) {}

        return null;
    }

    /**
     * Calcul local du score (fallback).
     */
    private function localScoring(array $data): array
    {
        $score = 0;

        // Profil complet
        if ($data['email_verifie'])     $score += 15;
        if ($data['telephone_verifie']) $score += 15;
        if ($data['photo'])             $score += 10;
        if ($data['bio'])               $score += 10;

        // Ancienneté
        if ($data['anciennete_jours'] >= 30)  $score += 10;
        if ($data['anciennete_jours'] >= 90)  $score += 10;
        if ($data['anciennete_jours'] >= 180) $score += 5;

        // Réclamations résolues
        $score += min($data['reclamations_resolues'] * 5, 20);

        // Pénalité pour rejets abusifs
        if ($data['reclamations_rejetees'] > 3) $score -= 20;
        elseif ($data['reclamations_rejetees'] > 1) $score -= 10;

        // Compte inactif
        if (!$data['actif']) $score -= 30;

        $score = max(0, min(100, $score));

        return $this->makeDecision($score, $data);
    }

    private function makeDecision(int $score, array $data): array
    {
        // Comportement abusif → rejet automatique
        if ($data['reclamations_rejetees'] > 5 || !$data['actif']) {
            return [
                'score'      => $score,
                'decision'   => 'REJECT',
                'badge'      => 'NONE',
                'explication'=> 'Comportement abusif détecté ou compte inactif. Badge refusé.',
            ];
        }

        if ($score >= 80) {
            return [
                'score'      => $score,
                'decision'   => 'ACCEPT',
                'badge'      => 'VERIFIED',
                'explication'=> "Score de fiabilité élevé ({$score}/100). Badge accordé automatiquement.",
            ];
        }

        if ($score >= 50) {
            return [
                'score'      => $score,
                'decision'   => 'ACCEPT',
                'badge'      => 'VERIFIED',
                'explication'=> "Score de fiabilité suffisant ({$score}/100). Badge accordé automatiquement par l'IA.",
            ];
        }

        return [
            'score'      => $score,
            'decision'   => 'REJECT',
            'badge'      => 'NONE',
            'explication'=> "Score insuffisant ({$score}/100). Complétez votre profil et augmentez votre activité.",
        ];
    }

    private function normalizeResult(array $d): array
    {
        $score    = max(0, min(100, (int) ($d['score'] ?? 0)));
        $decision = in_array($d['decision'] ?? '', ['ACCEPT','REVIEW','REJECT']) ? $d['decision'] : 'REVIEW';
        $badge    = $decision === 'ACCEPT' ? 'VERIFIED' : 'NONE';
        return [
            'score'      => $score,
            'decision'   => $decision,
            'badge'      => $badge,
            'explication'=> $d['explication'] ?? $d['explanation'] ?? 'Analyse IA complétée.',
        ];
    }

    private function bool(bool $v): string { return $v ? 'Oui' : 'Non'; }
}

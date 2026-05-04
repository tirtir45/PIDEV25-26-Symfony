<?php

namespace App\Service;

use App\Entity\Historique_connexions;
use App\Entity\Utilisateurs;
use App\Repository\Historique_connexionsRepository;

/**
 * Analyse le comportement utilisateur via le microservice Python Behavior AI.
 */
class BehaviorAIService
{
    private const ENDPOINT = '/analyze-behavior';
    private const TIMEOUT  = 10;
    private const API_URL  = 'http://127.0.0.1:8002';

    public function __construct(
        private readonly Historique_connexionsRepository $historiqueRepo,
    ) {}

    /**
     * Analyse le comportement d'un utilisateur lors d'une connexion.
     *
     * @return array{anomaly: bool, score: float, risk_level: string, message: string, details: array}
     */
    public function analyze(Utilisateurs $user, \DateTimeInterface $loginTime, int $sessionDuration = 0): array
    {
        try {
            $logins7d   = $this->countLoginsLastDays($user, 7);
            $usualHour  = $this->getUsualLoginHour($user);

            $payload = [
                'user_id'            => $user->getId(),
                'login_time'         => $loginTime->format('Y-m-d\TH:i:s'),
                'session_duration'   => $sessionDuration,
                'logins_last_7_days' => $logins7d,
                'usual_login_hour'   => $usualHour,
            ];

            $ch = curl_init(self::API_URL . self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false || $httpCode !== 200) {
                return $this->defaultResult();
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                return $this->defaultResult();
            }

            return [
                'anomaly'    => (bool)   ($data['anomaly']    ?? false),
                'score'      => (float)  ($data['score']      ?? 0.0),
                'risk_level' => (string) ($data['risk_level'] ?? 'low'),
                'message'    => (string) ($data['message']    ?? ''),
                'details'    => (array)  ($data['details']    ?? []),
            ];

        } catch (\Throwable) {
            return $this->defaultResult();
        }
    }

    /**
     * Compte les connexions d'un utilisateur sur les N derniers jours.
     */
    private function countLoginsLastDays(Utilisateurs $user, int $days): int
    {
        $since = new \DateTime("-{$days} days");

        return (int) $this->historiqueRepo
            ->createQueryBuilder('h')
            ->select('COUNT(h.id_historique)')
            ->where('h.id_utilisateur = :user')
            ->andWhere('h.date_connexion >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule l'heure moyenne de connexion habituelle de l'utilisateur.
     */
    private function getUsualLoginHour(Utilisateurs $user): ?float
    {
        $history = $this->historiqueRepo->findBy(
            ['id_utilisateur' => $user],
            ['date_connexion' => 'DESC'],
            30
        );

        if (empty($history)) {
            return null;
        }

        $hours = array_map(
            fn(Historique_connexions $h) => (float) $h->getDate_connexion()->format('H') + (float) $h->getDate_connexion()->format('i') / 60,
            $history
        );

        return round(array_sum($hours) / count($hours), 2);
    }

    private function defaultResult(): array
    {
        return [
            'anomaly'    => false,
            'score'      => 0.0,
            'risk_level' => 'low',
            'message'    => 'Analyse indisponible.',
            'details'    => [],
        ];
    }
}

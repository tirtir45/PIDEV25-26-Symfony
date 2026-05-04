<?php

namespace App\Service;

use App\Entity\Utilisateurs;
use App\Repository\ReclamationsRepository;

class SpamDetectionService
{
    private const MAX_PER_MINUTE  = 2;
    private const SIMILARITY_THRESHOLD = 90; // % de similarité pour bloquer

    public function __construct(
        private readonly ReclamationsRepository $repo
    ) {}

    /**
     * Vérifie si l'utilisateur est en train de spammer.
     * Retourne null si OK, ou un message d'erreur si spam détecté.
     */
    public function check(Utilisateurs $user, string $sujet, string $description): ?string
    {
        $recentReclamations = $this->getRecentReclamations($user);

        // 1. Limite de fréquence : max 2 par minute
        if (count($recentReclamations) >= self::MAX_PER_MINUTE) {
            return 'Vous avez dépassé le nombre autorisé de réclamations, veuillez réessayer dans une minute.';
        }

        // 2. Contenu dupliqué : comparer avec la dernière réclamation
        $last = $this->getLastReclamation($user);
        if ($last) {
            $newText  = mb_strtolower(trim($sujet . ' ' . $description));

            // Comparer avec le texte original ET le texte traduit
            $lastOriginal   = mb_strtolower(trim(
                ($last->getDescriptionOriginale() ?? $last->getDescription())
            ));
            $lastTranslated = mb_strtolower(trim($last->getSujet() . ' ' . $last->getDescription()));

            if ($this->isTooSimilar($newText, $lastOriginal) || $this->isTooSimilar($newText, $lastTranslated)) {
                return 'Cette réclamation a déjà été envoyée, veuillez modifier le contenu.';
            }
        }

        return null;
    }

    /**
     * Réclamations soumises dans la dernière minute.
     */
    private function getRecentReclamations(Utilisateurs $user): array
    {
        $since = new \DateTime('-2 minutes');

        return $this->repo->createQueryBuilder('r')
            ->where('r.utilisateur = :user')
            ->andWhere('r.dateCreation >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /**
     * Dernière réclamation de l'utilisateur.
     */
    private function getLastReclamation(Utilisateurs $user): ?object
    {
        return $this->repo->findOneBy(
            ['utilisateur' => $user],
            ['dateCreation' => 'DESC']
        );
    }

    /**
     * Vérifie si deux textes sont trop similaires.
     */
    private function isTooSimilar(string $a, string $b): bool
    {
        // Correspondance exacte
        if ($a === $b) {
            return true;
        }

        // Similarité via similar_text
        similar_text($a, $b, $percent);
        return $percent >= self::SIMILARITY_THRESHOLD;
    }
}

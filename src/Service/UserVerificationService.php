<?php

namespace App\Service;

use App\Entity\Utilisateurs;
use App\Repository\ReclamationsRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserVerificationService
{
    // Nombre minimum de réclamations résolues pour obtenir le badge
    private const MIN_RESOLVED = 2;

    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly ReclamationsRepository  $reclamRepo
    ) {}

    /**
     * Vérifie si l'utilisateur est éligible au badge et le met à jour.
     */
    public function checkAndUpdate(Utilisateurs $user): bool
    {
        $eligible = $this->isEligible($user);

        if ($eligible && !$user->isBadgeVerifie()) {
            $user->setBadgeVerifie(true);
            $user->setDateVerification(new \DateTime());
            $this->em->flush();
        }

        return $eligible;
    }

    /**
     * Vérifie les critères d'éligibilité.
     */
    public function isEligible(Utilisateurs $user): bool
    {
        // 1. Email présent
        if (empty($user->getEmail())) {
            return false;
        }

        // 2. Téléphone renseigné
        if (empty($user->getTelephone())) {
            return false;
        }

        // 3. Profil complet (photo + bio)
        if (empty($user->getPhoto()) || empty($user->getBio())) {
            return false;
        }

        // 4. Au moins N réclamations résolues
        $resolved = $this->reclamRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.utilisateur = :user')
            ->andWhere('r.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'RESOLU')
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $resolved < self::MIN_RESOLVED) {
            return false;
        }

        return true;
    }

    /**
     * Retire le badge (en cas d'abus).
     */
    public function revoke(Utilisateurs $user): void
    {
        $user->setBadgeVerifie(false);
        $user->setDateVerification(null);
        $this->em->flush();
    }
}

<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Utilisateurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Notifications (alias of Notification entity).
 * Used by EntrepreneurController for project management notification features.
 */
class NotificationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countNonLues(Utilisateurs $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.utilisateur = :user')
            ->andWhere('n.lu = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function marquerToutesLues(Utilisateurs $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.lu', true)
            ->where('n.utilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}

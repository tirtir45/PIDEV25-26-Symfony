<?php

namespace App\Repository;

use App\Entity\Notifications;
use App\Entity\Utilisateurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notifications::class);
    }

    public function findNonLues(Utilisateurs $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.utilisateur = :user')
            ->andWhere('n.lu = false')
            ->setParameter('user', $user)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult();
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

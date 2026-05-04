<?php

namespace App\Repository;

use App\Entity\Commentaires;
use App\Entity\Taches;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommentairesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaires::class);
    }

    public function findByTache(Taches $tache): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.tache = :tache')
            ->setParameter('tache', $tache)
            ->orderBy('c.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Demande_emplois;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Demande_emploisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_emplois::class);
    }

    public function findByCandidat(int $candidatId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.candidat_id = :cid')
            ->setParameter('cid', $candidatId)
            ->orderBy('d.date_demande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPublication(int $publicationId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.publication_id = :pid')
            ->setParameter('pid', $publicationId)
            ->getQuery()
            ->getResult();
    }
}

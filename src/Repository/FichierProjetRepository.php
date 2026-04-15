<?php

namespace App\Repository;

use App\Entity\FichierProjet;
use App\Entity\Projets;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FichierProjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FichierProjet::class);
    }

    public function findByProjet(Projets $projet): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.projet = :projet')
            ->setParameter('projet', $projet)
            ->orderBy('f.uploaded_at', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

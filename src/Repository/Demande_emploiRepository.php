<?php

namespace App\Repository;

use App\Entity\Demande_emploi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Demande_emploiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande_emploi::class);
    }

    // Add custom methods as needed
}
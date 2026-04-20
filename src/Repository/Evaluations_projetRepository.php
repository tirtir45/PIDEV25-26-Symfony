<?php

namespace App\Repository;

use App\Entity\Evaluations_projet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Evaluations_projetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evaluations_projet::class);
    }

    // Add custom methods as needed
}
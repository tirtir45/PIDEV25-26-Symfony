<?php

namespace App\Repository;

use App\Entity\Criteres_evaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Criteres_evaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Criteres_evaluation::class);
    }

    // Add custom methods as needed
}
<?php

namespace App\Repository;

use App\Entity\Membres_equipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Membres_equipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membres_equipe::class);
    }

    // Add custom methods as needed
}
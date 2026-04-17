<?php

namespace App\Repository;

use App\Entity\Historique_connexions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Historique_connexionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Historique_connexions::class);
    }

    // Add custom methods as needed
}
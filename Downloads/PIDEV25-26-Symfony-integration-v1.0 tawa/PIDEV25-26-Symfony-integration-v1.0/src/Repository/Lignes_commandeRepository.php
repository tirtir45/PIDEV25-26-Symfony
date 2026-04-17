<?php

namespace App\Repository;

use App\Entity\Lignes_commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Lignes_commandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lignes_commande::class);
    }

    // Add custom methods as needed
}
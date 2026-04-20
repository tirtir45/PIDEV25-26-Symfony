<?php

namespace App\Repository;

use App\Entity\Demandes_ressources;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Demandes_ressourcesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demandes_ressources::class);
    }

    // Add custom methods as needed
}
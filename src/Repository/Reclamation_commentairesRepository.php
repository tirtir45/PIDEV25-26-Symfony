<?php

namespace App\Repository;

use App\Entity\Reclamation_commentaires;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Reclamation_commentairesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation_commentaires::class);
    }

    // Add custom methods as needed
}
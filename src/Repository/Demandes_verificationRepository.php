<?php

namespace App\Repository;

use App\Entity\Demandes_verification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Demandes_verificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demandes_verification::class);
    }

    // Add custom methods as needed
}
<?php

namespace App\Repository;

use App\Entity\Daily_scrums;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Daily_scrumsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Daily_scrums::class);
    }

    // Add custom methods as needed
}
<?php

namespace App\Repository;

use App\Entity\User_stories;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class User_storiesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User_stories::class);
    }

    // Add custom methods as needed
}
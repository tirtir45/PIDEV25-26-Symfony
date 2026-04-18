<?php

namespace App\Repository;

use App\Entity\User_story_assignments;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class User_story_assignmentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User_story_assignments::class);
    }

    // Add custom methods as needed
}
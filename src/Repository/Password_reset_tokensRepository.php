<?php

namespace App\Repository;

use App\Entity\Password_reset_tokens;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Password_reset_tokensRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Password_reset_tokens::class);
    }

    // Add custom methods as needed
}
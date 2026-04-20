<?php

namespace App\Repository;

use App\Entity\Conversation_participants;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Conversation_participantsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation_participants::class);
    }

    // Add custom methods as needed
}
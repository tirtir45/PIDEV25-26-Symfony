<?php

namespace App\Repository;

use App\Entity\RessourceProjet;
use App\Entity\Projets;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RessourceProjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RessourceProjet::class);
    }

    public function findByProjet(Projets $projet): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.projet = :projet')
            ->setParameter('projet', $projet)
            ->orderBy('r.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDisponibles(Projets $projet): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.projet = :projet')
            ->andWhere('r.statut = :statut')
            ->setParameter('projet', $projet)
            ->setParameter('statut', RessourceProjet::STATUT_DISPONIBLE)
            ->getQuery()
            ->getResult();
    }
}

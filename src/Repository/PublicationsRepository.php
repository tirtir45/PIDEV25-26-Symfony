<?php

namespace App\Repository;

use App\Entity\Publications;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PublicationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publications::class);
    }

    public function findByFilters(?string $recherche, ?string $contrat, ?string $localisation): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.statut = :active')
            ->setParameter('active', 'active');

        if ($recherche) {
            $qb->andWhere('p.titre LIKE :r OR p.description LIKE :r')
               ->setParameter('r', '%' . $recherche . '%');
        }

        if ($contrat) {
            $qb->andWhere('p.type_contrat = :c')->setParameter('c', $contrat);
        }

        if ($localisation) {
            $qb->andWhere('p.localisation = :l')->setParameter('l', $localisation);
        }

        return $qb->orderBy('p.date_publication', 'DESC')->getQuery()->getResult();
    }

    public function findUniqueLocalisations(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.localisation')
            ->orderBy('p.localisation', 'ASC')
            ->getQuery()
            ->getScalarResult();
        return array_column($rows, 'localisation');
    }
}

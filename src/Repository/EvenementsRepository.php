<?php

namespace App\Repository;

use App\Entity\Evenements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvenementsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenements::class);
    }

    public function findWithFilters(?string $search, ?string $sort, ?float $prixMin, ?float $prixMax, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($search) {
            $qb->andWhere('e.titre LIKE :s OR e.lieu LIKE :s OR e.description LIKE :s')
               ->setParameter('s', '%' . $search . '%');
        }

        if ($prixMin !== null) {
            $qb->andWhere('e.prix >= :pmin')->setParameter('pmin', $prixMin);
        }

        if ($prixMax !== null) {
            $qb->andWhere('e.prix <= :pmax')->setParameter('pmax', $prixMax);
        }

        if ($statut === 'futur') {
            $qb->andWhere('e.date_evenement >= :today')->setParameter('today', new \DateTime('today'));
        } elseif ($statut === 'passe') {
            $qb->andWhere('e.date_evenement < :today')->setParameter('today', new \DateTime('today'));
        }

        match ($sort) {
            'date_asc'   => $qb->orderBy('e.date_evenement', 'ASC'),
            'date_desc'  => $qb->orderBy('e.date_evenement', 'DESC'),
            'prix_asc'   => $qb->orderBy('e.prix', 'ASC'),
            'prix_desc'  => $qb->orderBy('e.prix', 'DESC'),
            'titre_asc'  => $qb->orderBy('e.titre', 'ASC'),
            default      => $qb->orderBy('e.date_evenement', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    public function countFuturs(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id_evenement)')
            ->andWhere('e.date_evenement > :today')
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPasses(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id_evenement)')
            ->andWhere('e.date_evenement <= :today')
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFuturs(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.date_evenement > :today')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('e.date_evenement', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Reservations;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservations::class);
    }

    public function findByUtilisateur(int $idUtilisateur): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.id_evenement', 'e')
            ->join('r.id_utilisateur', 'u')
            ->andWhere('r.id_utilisateur = :uid')
            ->setParameter('uid', $idUtilisateur)
            ->orderBy('r.date_reservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByEvenement(int $idEvenement): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.id_utilisateur', 'u')
            ->andWhere('r.id_evenement = :eid')
            ->setParameter('eid', $idEvenement)
            ->orderBy('r.date_reservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existeDeja(int $idEvenement, int $idUtilisateur): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id_reservation)')
            ->andWhere('r.id_evenement = :eid')
            ->andWhere('r.id_utilisateur = :uid')
            ->setParameter('eid', $idEvenement)
            ->setParameter('uid', $idUtilisateur)
            ->getQuery()
            ->getSingleScalarResult();
        return $count > 0;
    }

    public function getTotalRevenu(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(e.prix)')
            ->join('r.id_evenement', 'e')
            ->getQuery()
            ->getSingleScalarResult();
        return (float) ($result ?? 0.0);
    }

    public function countByMonth(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "SELECT DATE_FORMAT(date_reservation, '%Y-%m') AS month,
                        COUNT(id_reservation) AS total
                 FROM reservations
                 WHERE date_reservation >= :start
                 GROUP BY month
                 ORDER BY month ASC";
        $result = $conn->executeQuery($sql, [
            'start' => (new \DateTime('-12 months'))->format('Y-m-d'),
        ]);
        return $result->fetchAllAssociative();
    }

    public function topEvenements(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('e.titre, COUNT(r.id_reservation) AS total')
            ->join('r.id_evenement', 'e')
            ->groupBy('e.id_evenement')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();
    }

    public function revenuParEvenement(): array
    {
        return $this->createQueryBuilder('r')
            ->select('e.titre, (COUNT(r.id_reservation) * e.prix) AS revenu')
            ->join('r.id_evenement', 'e')
            ->where('e.prix > 0')
            ->groupBy('e.id_evenement')
            ->orderBy('revenu', 'DESC')
            ->getQuery()
            ->getScalarResult();
    }
}

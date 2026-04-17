<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function save(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUtilisateur(int $idUtilisateur): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.evenement', 'e')
            ->join('r.utilisateur', 'u')
            ->andWhere('r.utilisateur = :uid')
            ->setParameter('uid', $idUtilisateur)
            ->orderBy('r.dateReservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByEvenement(int $idEvenement): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.utilisateur', 'u')
            ->andWhere('r.evenement = :eid')
            ->setParameter('eid', $idEvenement)
            ->orderBy('r.dateReservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existeDeja(int $idEvenement, int $idUtilisateur): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.idReservation)')
            ->andWhere('r.evenement = :eid')
            ->andWhere('r.utilisateur = :uid')
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
            ->join('r.evenement', 'e')
            ->getQuery()
            ->getSingleScalarResult();
        return (float) ($result ?? 0.0);
    }

    /** Reservations count per month — last 12 months (native SQL to support DATE_FORMAT) */
    public function countByMonth(): array
    {
        $conn  = $this->getEntityManager()->getConnection();
        $sql   = "SELECT DATE_FORMAT(date_reservation, '%Y-%m') AS month,
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

    /** Top N events by reservation count */
    public function topEvenements(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('e.titre, COUNT(r.idReservation) AS total')
            ->join('r.evenement', 'e')
            ->groupBy('e.idEvenement')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();
    }

    /** Revenue per paid event */
    public function revenuParEvenement(): array
    {
        return $this->createQueryBuilder('r')
            ->select('e.titre, (COUNT(r.idReservation) * e.prix) AS revenu')
            ->join('r.evenement', 'e')
            ->where('e.prix > 0')
            ->groupBy('e.idEvenement')
            ->orderBy('revenu', 'DESC')
            ->getQuery()
            ->getScalarResult();
    }
}

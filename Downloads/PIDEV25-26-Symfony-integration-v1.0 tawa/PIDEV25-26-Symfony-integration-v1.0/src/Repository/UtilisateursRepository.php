<?php

namespace App\Repository;

use App\Entity\Utilisateurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UtilisateursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateurs::class);
    }

    public function findWithSearchAndSort(string $search = '', string $sortBy = 'dateInscription', string $order = 'DESC', string $roleFilter = ''): array
    {
        $allowedSort = ['nom', 'email', 'telephone', 'dateInscription', 'actif', 'id'];
        $allowedOrder = ['ASC', 'DESC'];

        $sortBy = in_array($sortBy, $allowedSort) ? $sortBy : 'dateInscription';
        $order  = in_array(strtoupper($order), $allowedOrder) ? strtoupper($order) : 'DESC';

        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r');

        if ($search !== '') {
            $qb->andWhere('u.nom LIKE :q OR u.email LIKE :q OR u.telephone LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($roleFilter !== '') {
            $qb->andWhere('r.nomRole = :role')
               ->setParameter('role', $roleFilter);
        }

        $qb->orderBy('u.' . $sortBy, $order);

        return $qb->getQuery()->getResult();
    }

}
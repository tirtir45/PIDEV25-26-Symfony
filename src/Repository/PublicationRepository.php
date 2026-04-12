<?php

namespace App\Repository;

use App\Entity\Publication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    /**
     * Recherche les publications en fonction de plusieurs filtres (mot-clé, contrat, localisation).
     * C'est la méthode utilisée pour la page publique des offres (user/publication.html.twig).
     */
    public function findByFilters(?string $recherche, ?string $contrat, ?string $localisation): array
    {
        // 'p' est l'alias de notre entité Publication
        $qb = $this->createQueryBuilder('p')
            // CORRECTION : Utilisation de 'p.date_publication'
            ->orderBy('p.date_publication', 'DESC'); 

        // 1. Filtre par mot-clé de recherche
        if ($recherche) {
            // CORRECTION : Recherche dans 'titre', 'description', et 'departement'
            $qb->andWhere('p.titre LIKE :recherche OR p.description LIKE :recherche OR p.departement LIKE :recherche')
               ->setParameter('recherche', '%' . $recherche . '%');
        }

        // 2. Filtre par type de contrat
        if ($contrat) {
            // CORRECTION : Utilisation de 'p.type_contrat'
            $qb->andWhere('p.type_contrat = :contrat')
               ->setParameter('contrat', $contrat);
        }

        // 3. Filtre par localisation
        if ($localisation) {
            // CORRECTION : Utilisation de 'p.localisation'
            $qb->andWhere('p.localisation = :localisation')
               ->setParameter('localisation', $localisation);
        }

        return $qb->getQuery()->getResult();
    }


    /**
     * Récupère une liste de toutes les localisations uniques présentes dans les publications.
     */
    public function findUniqueLocalisations(): array
    {
        $qb = $this->createQueryBuilder('p')
            // CORRECTION : Utilisation de 'p.localisation'
            ->select('p.localisation') 
            ->distinct(true)
            ->orderBy('p.localisation', 'ASC');

        $results = $qb->getQuery()->getScalarResult();
        
        // La méthode array_column fonctionne toujours parfaitement ici
        return array_column($results, 'localisation');
    }

    /**
     * ANCIENNE METHODE que vous aviez, mise à jour pour être compatible.
     * Vous pouvez la supprimer si elle n'est plus utilisée.
     */
    public function findBySearch(?string $recherche, ?string $contrat): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($recherche) {
            // CORRECTION : Utilisation de 'p.departement'
            $qb->andWhere('p.titre LIKE :recherche OR p.departement LIKE :recherche')
               ->setParameter('recherche', '%' . $recherche . '%');
        }

        if ($contrat) {
            // CORRECTION : Utilisation de 'p.type_contrat'
            $qb->andWhere('p.type_contrat = :contrat')
               ->setParameter('contrat', $contrat);
        }

        // CORRECTION : Utilisation de 'p.date_publication'
        $qb->orderBy('p.date_publication', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
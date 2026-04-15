<?php

namespace App\Service;

use App\Entity\Projets;
use App\Entity\Taches;
use Doctrine\ORM\EntityManagerInterface;

class KanbanService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Retourne les tâches groupées par statut pour le kanban.
     */
    public function getColumns(Projets $projet): array
    {
        $taches = $this->em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);

        $columns = [
            Taches::STATUT_A_FAIRE  => ['label' => 'À faire',  'color' => '#6c757d', 'taches' => []],
            Taches::STATUT_EN_COURS => ['label' => 'En cours', 'color' => '#0d6efd', 'taches' => []],
            Taches::STATUT_TERMINEE => ['label' => 'Terminée', 'color' => '#198754', 'taches' => []],
        ];

        foreach ($taches as $tache) {
            $statut = $tache->getStatut();
            if (isset($columns[$statut])) {
                $columns[$statut]['taches'][] = $tache;
            }
        }

        return $columns;
    }

    /**
     * Déplace une tâche vers un nouveau statut.
     */
    public function deplacerTache(Taches $tache, string $nouveauStatut): void
    {
        $statutsValides = [Taches::STATUT_A_FAIRE, Taches::STATUT_EN_COURS, Taches::STATUT_TERMINEE];
        if (!in_array($nouveauStatut, $statutsValides, true)) {
            throw new \InvalidArgumentException('Statut invalide : ' . $nouveauStatut);
        }

        $tache->setStatut($nouveauStatut);
        $this->em->flush();
    }
}

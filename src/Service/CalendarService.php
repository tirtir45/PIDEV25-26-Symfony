<?php

namespace App\Service;

use App\Entity\Projets;
use App\Entity\Sprints;
use App\Entity\Taches;
use Doctrine\ORM\EntityManagerInterface;

class CalendarService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function getEvents(Projets $projet): array
    {
        $events = [];

        $taches = $this->em->getRepository(Taches::class)->findBy(['id_projet' => $projet]);
        foreach ($taches as $tache) {
            if (!$tache->getDateLimite()) continue;

            $color = match($tache->getStatut()) {
                Taches::STATUT_TERMINEE => '#198754',
                Taches::STATUT_EN_COURS => '#0d6efd',
                default                 => '#6c757d',
            };

            $events[] = [
                'id'    => 'tache_' . $tache->getId_tache(),
                'title' => $tache->getTitre(),
                'start' => $tache->getDateLimite()->format('Y-m-d'),
                'color' => $color,
                'type'  => 'tache',
                'url'   => null,
            ];
        }

        $sprints = $this->em->getRepository(Sprints::class)->findBy(['id_projet' => $projet]);
        foreach ($sprints as $sprint) {
            if (!$sprint->getDate_debut() || !$sprint->getDate_fin()) continue;
            $events[] = [
                'id'    => 'sprint_' . $sprint->getId_sprint(),
                'title' => '🏃 ' . $sprint->getNom(),
                'start' => $sprint->getDate_debut()->format('Y-m-d'),
                'end'   => $sprint->getDate_fin()->format('Y-m-d'),
                'color' => '#fd7e14',
                'type'  => 'sprint',
            ];
        }

        return $events;
    }
}

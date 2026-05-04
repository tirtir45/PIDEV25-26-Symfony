<?php
// src/EventListener/ProjectMarketAnalysisListener.php

namespace App\EventListener;

use App\Entity\Projets;
use App\Service\MarketstackService;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class ProjectMarketAnalysisListener
{
    public function __construct(private MarketstackService $marketstack)
    {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $projet = $event->getObject();
        
        // Vérifier que c'est bien un projet
        if (!$projet instanceof Projets) {
            return;
        }
        
        $this->analyzeProject($projet);
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $projet = $event->getObject();
        
        // Vérifier que c'est bien un projet
        if (!$projet instanceof Projets) {
            return;
        }
        
        // Analyser seulement si jamais analysé
        if ($projet->getMarketAnalyzedAt() === null) {
            $this->analyzeProject($projet);
        }
    }

    private function analyzeProject(Projets $projet): void
    {
        $analysis = $this->marketstack->analyzeSectorTrend($projet);
        
        $projet->setMarketScore($analysis['score']);
        $projet->setMarketTrend($analysis['trend']);
        $projet->setMarketData($analysis);
        $projet->setMarketAnalyzedAt($analysis['analyzed_at']);
    }
}
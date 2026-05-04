<?php

namespace App\Service;

use App\Entity\Ressources;
use App\Entity\Utilisateurs;
use App\Entity\Projets;
use Doctrine\ORM\EntityManagerInterface;

class RecommendationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Utilisateurs|null $user
     * @param Ressources[] $allRessources
     * @return array
     */
    public function getRecommendations(?Utilisateurs $user, array $allRessources): array
    {
        if (!$user) {
            return [];
        }

        $projects = $this->entityManager->getRepository(Projets::class)->findBy(['id_entrepreneur' => $user]);

        if (empty($projects)) {
            return [];
        }

        $scoredRessources = [];
        foreach ($allRessources as $ressource) {
            $bestScore = 0;
            $bestProject = null;

            foreach ($projects as $project) {
                $score = $this->calculateScore($ressource, $project);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestProject = $project;
                }
            }

            if ($bestScore > 0) {
                $scoredRessources[] = [
                    'ressource' => $ressource,
                    'project' => $bestProject,
                    'score' => $bestScore
                ];
            }
        }

        // Sort by score descending to allow picking top matches easily if needed,
        // but since we return all matches for badges, we don't slice.
        usort($scoredRessources, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scoredRessources;
    }

    private function calculateScore(Ressources $ressource, Projets $project): int
    {
        $score = 0;
        $sector = mb_strtolower($project->getSecteur() ?? '');
        $titleKeywords = $this->extractKeywords($project->getTitre() ?? '');
        
        $resName = mb_strtolower($ressource->getNom() ?? '');
        $resDesc = mb_strtolower($ressource->getDescription() ?? '');
        $resType = mb_strtolower($ressource->getType_r() ?? '');

        // Sector matching - high weight
        if ($sector !== '') {
            if (str_contains($resName, $sector)) $score += 20;
            if (str_contains($resDesc, $sector)) $score += 10;
            if (str_contains($resType, $sector)) $score += 15;
        }

        // Project Title Keywords matching - medium weight
        foreach ($titleKeywords as $keyword) {
            if (str_contains($resName, $keyword)) $score += 5;
            if (str_contains($resDesc, $keyword)) $score += 2;
        }

        return $score;
    }

    private function extractKeywords(string $text): array
    {
        $text = mb_strtolower($text);
        $stopWords = ['de', 'la', 'le', 'et', 'un', 'une', 'des', 'les', 'au', 'aux', 'du', 'pour', 'sur', 'dans', 'avec', 'projet', 'application', 'site'];
        
        $words = preg_split('/[\s,.:;!?()\'"]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        return array_filter($words, fn($word) => strlen($word) > 3 && !in_array($word, $stopWords));
    }
}

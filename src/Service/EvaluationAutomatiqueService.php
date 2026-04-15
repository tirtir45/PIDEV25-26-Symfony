<?php
// src/Service/EvaluationAutomatiqueService.php

namespace App\Service;

use App\Entity\Projets;

class EvaluationAutomatiqueService
{
    // Critères d'évaluation avec leurs poids
    private const CRITERES = [
        'qualite_description' => [
            'poids' => 15,
            'max_score' => 15
        ],
        'objectifs_clairs' => [
            'poids' => 15,
            'max_score' => 15
        ],
        'budget_realiste' => [
            'poids' => 20,
            'max_score' => 20
        ],
        'duree_realiste' => [
            'poids' => 10,
            'max_score' => 10
        ],
        'equipe_adequate' => [
            'poids' => 10,
            'max_score' => 10
        ],
        'secteur_pertinent' => [
            'poids' => 10,
            'max_score' => 10
        ],
        'financement' => [
            'poids' => 10,
            'max_score' => 10
        ],
        'experiences' => [
            'poids' => 10,
            'max_score' => 10
        ]
    ];

    private const SEUIL_ACCEPTATION = 70; // 70%

    public function evaluerProjet(Projets $projet): array
    {
        $evaluation = [
            'score_total' => 0,
            'score_max' => 0,
            'details' => [],
            'accepte' => false,
            'recommandations' => []
        ];

        foreach (self::CRITERES as $critere => $config) {
            $methode = 'evaluer' . str_replace('_', '', ucwords($critere, '_'));
            if (method_exists($this, $methode)) {
                $resultat = $this->$methode($projet);
                $evaluation['details'][$critere] = $resultat;
                $evaluation['score_total'] += $resultat['score'];
                $evaluation['score_max'] += $config['max_score'];
                
                if ($resultat['score'] < $config['max_score'] * 0.5) {
                    $evaluation['recommandations'][] = $resultat['recommandation'];
                }
            }
        }

        $pourcentage = ($evaluation['score_total'] / $evaluation['score_max']) * 100;
        $evaluation['accepte'] = $pourcentage >= self::SEUIL_ACCEPTATION;
        $evaluation['pourcentage'] = round($pourcentage, 2);

        return $evaluation;
    }

    private function evaluerQualiteDescription(Projets $projet): array
    {
        $description = $projet->getDescription();
        $longueur = strlen($description);
        
        if ($longueur >= 500) {
            $score = 15;
            $niveau = 'excellent';
            $commentaire = 'Description très complète et détaillée';
        } elseif ($longueur >= 300) {
            $score = 12;
            $niveau = 'bon';
            $commentaire = 'Bonne description, pourrait être plus détaillée';
        } elseif ($longueur >= 150) {
            $score = 8;
            $niveau = 'correct';
            $commentaire = 'Description correcte mais manque de détails';
        } elseif ($longueur >= 50) {
            $score = 4;
            $niveau = 'insuffisant';
            $commentaire = 'Description trop courte';
        } else {
            $score = 0;
            $niveau = 'critique';
            $commentaire = 'Description très insuffisante';
        }

        return [
            'score' => $score,
            'max_score' => 15,
            'niveau' => $niveau,
            'commentaire' => $commentaire,
            'recommandation' => 'Ajoutez plus de détails dans la description du projet (minimum 300 caractères)'
        ];
    }

    private function evaluerObjectifsClairs(Projets $projet): array
    {
        $objectifs = $projet->getObjectifs();
        $longueur = strlen($objectifs);
        
        // Détection de mots-clés d'objectifs SMART
        $mots_cles = ['mesurable', 'atteignable', 'réaliste', 'temporel', 'délai', 'chiffre', 'objectif', 'but'];
        $score_mots_cles = 0;
        foreach ($mots_cles as $mot) {
            if (stripos($objectifs, $mot) !== false) {
                $score_mots_cles += 2;
            }
        }
        
        $score_longueur = min(10, $longueur / 50);
        $score = min(15, $score_longueur + $score_mots_cles);
        
        $commentaire = $score >= 12 ? 'Objectifs clairs et bien définis' : 
                      ($score >= 8 ? 'Objectifs corrects mais à préciser' : 'Objectifs trop vagues');
        
        return [
            'score' => $score,
            'max_score' => 15,
            'niveau' => $score >= 12 ? 'bon' : ($score >= 8 ? 'correct' : 'insuffisant'),
            'commentaire' => $commentaire,
            'recommandation' => 'Utilisez la méthode SMART pour définir vos objectifs (Spécifique, Mesurable, Atteignable, Réaliste, Temporel)'
        ];
    }

    private function evaluerBudgetRealiste(Projets $projet): array
    {
        $budget = $projet->getBudgetEstime();
        
        if ($budget >= 10000 && $budget <= 500000) {
            $score = 20;
            $commentaire = 'Budget très réaliste pour un projet de cette envergure';
        } elseif ($budget >= 5000 && $budget <= 1000000) {
            $score = 15;
            $commentaire = 'Budget acceptable';
        } elseif ($budget >= 1000 && $budget <= 2000000) {
            $score = 10;
            $commentaire = 'Budget à justifier davantage';
        } elseif ($budget < 1000) {
            $score = 5;
            $commentaire = 'Budget trop faible pour être réaliste';
        } else {
            $score = 8;
            $commentaire = 'Budget très élevé, nécessite plus de justifications';
        }
        
        return [
            'score' => $score,
            'max_score' => 20,
            'niveau' => $score >= 15 ? 'bon' : ($score >= 10 ? 'correct' : 'insuffisant'),
            'commentaire' => $commentaire,
            'recommandation' => 'Justifiez votre budget avec un prévisionnel détaillé des dépenses'
        ];
    }

    private function evaluerDureeRealiste(Projets $projet): array
    {
        $duree = $projet->getDureeEstimee();
        $budget = $projet->getBudgetEstime();
        $ratio = $budget / $duree;
        
        if ($duree >= 3 && $duree <= 18) {
            $score = 10;
            $commentaire = 'Durée réaliste';
        } elseif ($duree >= 1 && $duree <= 24) {
            $score = 7;
            $commentaire = 'Durée acceptable';
        } elseif ($duree < 1) {
            $score = 3;
            $commentaire = 'Durée trop courte';
        } else {
            $score = 5;
            $commentaire = 'Durée trop longue';
        }
        
        // Bonus si le ratio budget/durée est cohérent
        if ($ratio >= 5000 && $ratio <= 50000) {
            $score += 2;
            $commentaire .= ' avec un budget mensuel cohérent';
        }
        
        return [
            'score' => min(10, $score),
            'max_score' => 10,
            'niveau' => $score >= 8 ? 'bon' : ($score >= 5 ? 'correct' : 'insuffisant'),
            'commentaire' => $commentaire,
            'recommandation' => 'Ajustez la durée du projet en fonction de sa complexité réelle'
        ];
    }

    private function evaluerEquipeAdequate(Projets $projet): array
    {
        $nbMembres = $projet->getNbMembresEquipe();
        $duree = $projet->getDureeEstimee();
        
        if ($nbMembres >= 2 && $nbMembres <= 10) {
            $score = 10;
            $commentaire = 'Taille d\'équipe idéale';
        } elseif ($nbMembres >= 1 && $nbMembres <= 15) {
            $score = 7;
            $commentaire = 'Taille d\'équipe acceptable';
        } else {
            $score = 4;
            $commentaire = 'Taille d\'équipe non adaptée';
        }
        
        // Cohérence avec la durée
        if ($duree < 6 && $nbMembres > 8) {
            $score -= 2;
            $commentaire .= ' - Trop de membres pour une courte durée';
        }
        
        return [
            'score' => max(0, $score),
            'max_score' => 10,
            'niveau' => $score >= 8 ? 'bon' : ($score >= 5 ? 'correct' : 'insuffisant'),
            'commentaire' => $commentaire,
            'recommandation' => 'Adaptez la taille de l\'équipe à la charge de travail réelle'
        ];
    }

    private function evaluerSecteurPertinent(Projets $projet): array
    {
        $secteurs_prioritaires = [
            'Technologie', 'Innovation', 'Green Tech', 'Santé', 'Éducation',
            'Intelligence Artificielle', 'Durabilité', 'Énergie renouvelable'
        ];
        
        $secteur = $projet->getSecteur();
        $estPrioritaire = in_array($secteur, $secteurs_prioritaires);
        
        if ($estPrioritaire) {
            $score = 10;
            $commentaire = 'Secteur prioritaire pour l\'incubation';
        } else {
            $score = 6;
            $commentaire = 'Secteur standard';
        }
        
        return [
            'score' => $score,
            'max_score' => 10,
            'niveau' => $score >= 8 ? 'bon' : 'correct',
            'commentaire' => $commentaire,
            'recommandation' => $estPrioritaire ? '' : 'Positionnez-vous sur un secteur plus innovant'
        ];
    }

    private function evaluerFinancement(Projets $projet): array
    {
        $financement = $projet->getFinancementActuel();
        $scores = [
            'Auto-financement total' => 10,
            'Auto-financement partiel' => 8,
            'Recherche de financement' => 5,
            'Aucun financement' => 3
        ];
        
        $score = $scores[$financement] ?? 5;
        $commentaire = $financement;
        
        return [
            'score' => $score,
            'max_score' => 10,
            'niveau' => $score >= 8 ? 'bon' : ($score >= 5 ? 'correct' : 'insuffisant'),
            'commentaire' => $commentaire,
            'recommandation' => 'Préparez un plan de financement détaillé'
        ];
    }

    private function evaluerExperiences(Projets $projet): array
    {
        $experiences = $projet->getExperiencesAnterieures();
        
        if (empty($experiences)) {
            return [
                'score' => 0,
                'max_score' => 10,
                'niveau' => 'insuffisant',
                'commentaire' => 'Aucune expérience mentionnée',
                'recommandation' => 'Décrivez vos expériences pertinentes (projets, formations, emplois)'
            ];
        }
        
        $longueur = strlen($experiences);
        $score = min(10, $longueur / 50);
        
        // Bonus pour mentions d'expériences concrètes
        $mots_cles = ['projet', 'entreprise', 'startup', 'création', 'gestion', 'leadership'];
        foreach ($mots_cles as $mot) {
            if (stripos($experiences, $mot) !== false) {
                $score = min(10, $score + 1);
            }
        }
        
        return [
            'score' => $score,
            'max_score' => 10,
            'niveau' => $score >= 7 ? 'bon' : ($score >= 4 ? 'correct' : 'insuffisant'),
            'commentaire' => $score >= 7 ? 'Bonne expérience' : 'Expérience à développer',
            'recommandation' => 'Mettez en avant vos expériences les plus pertinentes pour ce projet'
        ];
    }
}
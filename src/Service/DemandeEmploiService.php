<?php

namespace App\Service;

use App\Entity\Demande_emplois;

class DemandeEmploiService
{
    // Règle métier 1 :
    // Le CV doit exister
    public function verifierCv(?string $cv): bool
    {
        return !empty($cv);
    }

    // Règle métier 2 :
    // La lettre doit contenir au moins 20 caractères
    public function verifierLettreMotivation(?string $lettre): bool
    {
        if ($lettre === null) {
            return false;
        }

        return strlen(trim($lettre)) >= 20;
    }

    // Validation globale
    public function demandeValide(Demande_emplois $demande): bool
    {
        return $this->verifierCv($demande->getCvUrl())
            && $this->verifierLettreMotivation(
                $demande->getLettreMotivation()
            );
    }
}
<?php

namespace App\Service;

use App\Entity\Utilisateurs;
use App\Entity\Projets;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class EntrepreneurAuthorizationService
{
    public function isProjectOwner(Projets $projet, Utilisateurs $user): bool
    {
        return $projet->getIdEntrepreneur() === $user;
    }

    public function canModifyProject(Projets $projet, Utilisateurs $user): bool
    {
        return $this->isProjectOwner($projet, $user) &&
               $this->isProjectInModifiableState($projet);
    }

    public function canDeleteProject(Projets $projet, Utilisateurs $user): bool
    {
        return $this->isProjectOwner($projet, $user) &&
               in_array($projet->getEtat(), [
                   Projets::ETAT_EN_ATTENTE,
                   Projets::ETAT_REFUSE
               ]);
    }

    public function isProjectInModifiableState(Projets $projet): bool
    {
        return in_array($projet->getEtat(), [
            Projets::ETAT_ACCEPTE,
            Projets::ETAT_EN_COURS
        ]);
    }

    public function isEntrepreneur(Utilisateurs $user): bool
    {
        return $user->getProjetss()->count() > 0;
    }

    public function requireProjectOwner(Projets $projet, Utilisateurs $user): void
    {
        if (!$this->isProjectOwner($projet, $user)) {
            throw new AccessDeniedException('You do not own this project.');
        }
    }

    public function requireModifiableState(Projets $projet): void
    {
        if (!$this->isProjectInModifiableState($projet)) {
            throw new AccessDeniedException(
                'This project is not in a state that allows modifications.'
            );
        }
    }
}

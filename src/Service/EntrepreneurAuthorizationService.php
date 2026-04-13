<?php

namespace App\Service;

use App\Entity\Utilisateurs;
use App\Entity\Projets;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Service for handling authorization checks for entrepreneurs
 * Ensures proper access control for project management operations
 */
class EntrepreneurAuthorizationService
{
    /**
     * Check if a user is the owner/entrepreneur of a project
     */
    public function isProjectOwner(Projets $projet, Utilisateurs $user): bool
    {
        return $projet->getIdEntrepreneur() === $user;
    }

    /**
     * Check if user can modify a project
     */
    public function canModifyProject(Projets $projet, Utilisateurs $user): bool
    {
        return $this->isProjectOwner($projet, $user) && 
               $this->isProjectInModifiableState($projet);
    }

    /**
     * Check if user can delete a project
     */
    public function canDeleteProject(Projets $projet, Utilisateurs $user): bool
    {
        return $this->isProjectOwner($projet, $user) && 
               in_array($projet->getEtat(), [
                   Projets::ETAT_EN_ATTENTE,
                   Projets::ETAT_REFUSE
               ]);
    }

    /**
     * Check if project is in a state that allows modifications
     */
    public function isProjectInModifiableState(Projets $projet): bool
    {
        return in_array($projet->getEtat(), [
            Projets::ETAT_ACCEPTE,
            Projets::ETAT_EN_COURS
        ]);
    }

    /**
     * Verify user is an entrepreneur (has at least one project)
     */
    public function isEntrepreneur(Utilisateurs $user): bool
    {
        return $user->getProjetss()->count() > 0;
    }

    /**
     * Throw exception if user is not project owner
     */
    public function requireProjectOwner(Projets $projet, Utilisateurs $user): void
    {
        if (!$this->isProjectOwner($projet, $user)) {
            throw new AccessDeniedException('You do not own this project.');
        }
    }

    /**
     * Throw exception if project is not in modifiable state
     */
    public function requireModifiableState(Projets $projet): void
    {
        if (!$this->isProjectInModifiableState($projet)) {
            throw new AccessDeniedException(
                'This project is not in a state that allows modifications.'
            );
        }
    }
}

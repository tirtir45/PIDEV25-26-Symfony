<?php

namespace App\Service;

use App\Entity\Membres_equipe;
use App\Entity\Projets;
use App\Entity\Utilisateurs;
use App\Repository\MembresEquipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for managing team members
 * Enforces the rule that only entrepreneurs can create and manage team members
 */
class TeamMemberService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembresEquipeRepository $teamMemberRepository,
        private EntrepreneurAuthorizationService $authorizationService,
        private ValidatorInterface $validator
    ) {}

    /**
     * Create a new team member for a project
     * Only the entrepreneur who owns the project can add team members
     */
    public function createTeamMember(
        Utilisateurs $entrepreneur,
        Projets $project,
        Utilisateurs $teamMemberUser,
        string $role = ''
    ): Membres_equipe {
        // Verify that the current user is the entrepreneur
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);

        // Verify project is in a state that allows team member addition
        $this->authorizationService->requireModifiableState($project);

        // Check if team member is already assigned to this project
        $existingMember = $this->teamMemberRepository->findOneBy([
            'id_projet' => $project,
            'id_utilisateur' => $teamMemberUser
        ]);

        if ($existingMember !== null) {
            throw new \Exception(
                'This user is already a team member of this project.'
            );
        }

        // Create new team member entry
        $teamMember = new Membres_equipe();
        $teamMember->setIdProjet($project);
        $teamMember->setIdUtilisateur($teamMemberUser);
        
        if (!empty($role)) {
            $teamMember->setRoleEquipe($role);
        }

        // Validate the entity
        $errors = $this->validator->validate($teamMember);
        if (count($errors) > 0) {
            throw new \Exception((string)$errors);
        }

        // Persist and flush
        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();

        return $teamMember;
    }

    /**
     * Update an existing team member
     */
    public function updateTeamMember(
        Utilisateurs $entrepreneur,
        Membres_equipe $teamMember,
        string $roleEquipe = ''
    ): Membres_equipe {
        // Verify ownership of the project
        $this->authorizationService->requireProjectOwner(
            $teamMember->getIdProjet(),
            $entrepreneur
        );

        // Update role if provided
        if (!empty($roleEquipe)) {
            $teamMember->setRoleEquipe($roleEquipe);
        }

        // Validate before saving
        $errors = $this->validator->validate($teamMember);
        if (count($errors) > 0) {
            throw new \Exception((string)$errors);
        }

        $this->entityManager->flush();
        return $teamMember;
    }

    /**
     * Delete a team member from a project
     * Only the entrepreneur can remove team members
     */
    public function deleteTeamMember(
        Utilisateurs $entrepreneur,
        Membres_equipe $teamMember
    ): void {
        // Verify ownership
        $this->authorizationService->requireProjectOwner(
            $teamMember->getIdProjet(),
            $entrepreneur
        );

        // Remove any task assignments for this team member
        // (Tasks should be reassigned or deleted)
        $project = $teamMember->getIdProjet();
        
        $this->entityManager->remove($teamMember);
        $this->entityManager->flush();
    }

    /**
     * Get all team members for a specific project
     */
    public function getProjectTeamMembers(Projets $project): array
    {
        return $this->teamMemberRepository->findBy([
            'id_projet' => $project
        ]);
    }

    /**
     * Check if a user is already a team member of a project
     */
    public function isTeamMemberOfProject(
        Utilisateurs $user,
        Projets $project
    ): bool {
        $member = $this->teamMemberRepository->findOneBy([
            'id_projet' => $project,
            'id_utilisateur' => $user
        ]);

        return $member !== null;
    }

    /**
     * Get team members for a project, excluding a specific user
     * Useful for filtering in forms
     */
    public function getAvailableTeamMembers(Projets $project): array
    {
        // This would need a custom query to get available users
        // For now, returns empty array - implement based on your user assignment logic
        return [];
    }

    /**
     * Count team members in a project
     */
    public function getTeamMemberCount(Projets $project): int
    {
        return count($this->getProjectTeamMembers($project));
    }
}

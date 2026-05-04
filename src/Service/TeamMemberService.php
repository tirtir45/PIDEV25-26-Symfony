<?php

namespace App\Service;

use App\Entity\Membres_equipe;
use App\Entity\Projets;
use App\Entity\Utilisateurs;
use App\Repository\MembresEquipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TeamMemberService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembresEquipeRepository $teamMemberRepository,
        private EntrepreneurAuthorizationService $authorizationService,
        private ValidatorInterface $validator
    ) {}

    public function createTeamMember(Utilisateurs $entrepreneur, Projets $project, Utilisateurs $teamMemberUser, string $role = ''): Membres_equipe
    {
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);
        $this->authorizationService->requireModifiableState($project);

        $existingMember = $this->teamMemberRepository->findOneBy(['id_projet' => $project, 'id_utilisateur' => $teamMemberUser]);
        if ($existingMember !== null) throw new \Exception('This user is already a team member of this project.');

        $teamMember = new Membres_equipe();
        $teamMember->setIdProjet($project);
        $teamMember->setIdUtilisateur($teamMemberUser);
        if (!empty($role)) $teamMember->setRoleEquipe($role);

        $errors = $this->validator->validate($teamMember);
        if (count($errors) > 0) throw new \Exception((string)$errors);

        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();
        return $teamMember;
    }

    public function deleteTeamMember(Utilisateurs $entrepreneur, Membres_equipe $teamMember): void
    {
        $this->authorizationService->requireProjectOwner($teamMember->getIdProjet(), $entrepreneur);
        $this->entityManager->remove($teamMember);
        $this->entityManager->flush();
    }

    public function getProjectTeamMembers(Projets $project): array
    {
        return $this->teamMemberRepository->findBy(['id_projet' => $project]);
    }

    public function isTeamMemberOfProject(Utilisateurs $user, Projets $project): bool
    {
        return $this->teamMemberRepository->findOneBy(['id_projet' => $project, 'id_utilisateur' => $user]) !== null;
    }

    public function getTeamMemberCount(Projets $project): int
    {
        return count($this->getProjectTeamMembers($project));
    }
}

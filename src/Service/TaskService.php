<?php

namespace App\Service;

use App\Entity\Membres_equipe;
use App\Entity\Projets;
use App\Entity\Taches;
use App\Entity\Utilisateurs;
use App\Repository\TachesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TaskService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TachesRepository $taskRepository,
        private EntrepreneurAuthorizationService $authorizationService,
        private ValidatorInterface $validator
    ) {}

    public function createTask(Utilisateurs $entrepreneur, Projets $project, string $titre, string $description = '', ?\DateTimeInterface $dateLimite = null, ?Membres_equipe $assignedTeamMember = null): Taches
    {
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);
        $this->authorizationService->requireModifiableState($project);

        if (empty(trim($titre))) throw new \Exception('Task title cannot be empty.');
        if (strlen($titre) > 150) throw new \Exception('Task title cannot exceed 150 characters.');

        $task = new Taches();
        $task->setId_projet($project);
        $task->setTitre($titre);
        $task->setDescription($description);
        $task->setDate_limite($dateLimite);
        $task->setStatut(Taches::STATUT_A_FAIRE);

        if ($assignedTeamMember !== null) $this->assignTaskToTeamMember($task, $assignedTeamMember);

        $errors = $this->validator->validate($task);
        if (count($errors) > 0) throw new \Exception((string)$errors);

        $this->entityManager->persist($task);
        $this->entityManager->flush();
        return $task;
    }

    public function deleteTask(Utilisateurs $entrepreneur, Taches $task): void
    {
        $this->authorizationService->requireProjectOwner($task->getIdProjet(), $entrepreneur);
        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }

    public function assignTaskToTeamMember(Taches $task, Membres_equipe $teamMember): Taches
    {
        if ($task->getIdProjet() !== $teamMember->getIdProjet()) {
            throw new \Exception('Team member does not belong to this project.');
        }
        $task->setId_responsable($teamMember->getIdUtilisateur());
        $this->entityManager->flush();
        return $task;
    }

    public function getProjectTasks(Projets $project): array
    {
        return $this->taskRepository->findBy(['id_projet' => $project], ['date_limite' => 'ASC', 'id_tache' => 'DESC']);
    }

    public function getProjectCompletion(Projets $project): float
    {
        $allTasks = $this->getProjectTasks($project);
        if (count($allTasks) === 0) return 0.0;
        $completedTasks = count(array_filter($allTasks, fn($t) => $t->getStatut() === Taches::STATUT_TERMINEE));
        return ($completedTasks / count($allTasks)) * 100;
    }
}

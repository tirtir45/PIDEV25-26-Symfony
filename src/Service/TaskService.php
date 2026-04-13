<?php

namespace App\Service;

use App\Entity\Membres_equipe;
use App\Entity\Projets;
use App\Entity\Taches;
use App\Entity\Utilisateurs;
use App\Repository\TachesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for managing tasks
 * Handles task CRUD operations with proper validation and authorization
 */
class TaskService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TachesRepository $taskRepository,
        private EntrepreneurAuthorizationService $authorizationService,
        private ValidatorInterface $validator
    ) {}

    /**
     * Create a new task in a project
     * Only the entrepreneur who owns the project can create tasks
     */
    public function createTask(
        Utilisateurs $entrepreneur,
        Projets $project,
        string $titre,
        string $description = '',
        ?\DateTimeInterface $dateLimite = null,
        ?Membres_equipe $assignedTeamMember = null
    ): Taches {
        // Verify authorization
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);
        $this->authorizationService->requireModifiableState($project);

        // Validate title
        if (empty(trim($titre))) {
            throw new \Exception('Task title cannot be empty.');
        }

        if (strlen($titre) > 150) {
            throw new \Exception('Task title cannot exceed 150 characters.');
        }

        // Create task
        $task = new Taches();
        $task->setId_projet($project);
        $task->setTitre($titre);
        $task->setDescription($description);
        $task->setDate_limite($dateLimite);
        $task->setStatut(Taches::STATUT_A_FAIRE);

        // Assign to team member if provided
        if ($assignedTeamMember !== null) {
            $this->assignTaskToTeamMember($task, $assignedTeamMember);
        }

        // Validate the entity
        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            throw new \Exception((string)$errors);
        }

        // Persist and flush
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Update an existing task
     */
    public function updateTask(
        Utilisateurs $entrepreneur,
        Taches $task,
        string $titre = '',
        string $description = '',
        ?\DateTimeInterface $dateLimite = null,
        string $statut = ''
    ): Taches {
        // Verify authorization
        $this->authorizationService->requireProjectOwner(
            $task->getIdProjet(),
            $entrepreneur
        );

        // Update fields if provided
        if (!empty($titre)) {
            if (strlen($titre) > 150) {
                throw new \Exception('Task title cannot exceed 150 characters.');
            }
            $task->setTitre($titre);
        }

        if (!empty($description)) {
            $task->setDescription($description);
        }

        if ($dateLimite !== null) {
            $task->setDate_limite($dateLimite);
        }

        if (!empty($statut)) {
            if (!in_array($statut, [
                Taches::STATUT_A_FAIRE,
                Taches::STATUT_EN_COURS,
                Taches::STATUT_TERMINEE
            ])) {
                throw new \Exception('Invalid task status.');
            }
            $task->setStatut($statut);
        }

        // Validate before saving
        $errors = $this->validator->validate($task);
        if (count($errors) > 0) {
            throw new \Exception((string)$errors);
        }

        $this->entityManager->flush();
        return $task;
    }

    /**
     * Delete a task from a project
     */
    public function deleteTask(
        Utilisateurs $entrepreneur,
        Taches $task
    ): void {
        // Verify authorization
        $this->authorizationService->requireProjectOwner(
            $task->getIdProjet(),
            $entrepreneur
        );

        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }

    /**
     * Assign a task to a team member
     */
    public function assignTaskToTeamMember(
        Taches $task,
        Membres_equipe $teamMember
    ): Taches {
        // Verify the team member belongs to the same project
        if ($task->getIdProjet() !== $teamMember->getIdProjet()) {
            throw new \Exception(
                'Team member does not belong to this project.'
            );
        }

        // Assign the user who is the team member
        $task->setId_responsable($teamMember->getIdUtilisateur());

        $this->entityManager->flush();
        return $task;
    }

    /**
     * Unassign a task from a team member
     */
    public function unassignTask(Taches $task): Taches
    {
        $task->setId_responsable(null);
        $this->entityManager->flush();
        return $task;
    }

    /**
     * Get all tasks for a specific project
     */
    public function getProjectTasks(Projets $project): array
    {
        return $this->taskRepository->findBy(
            ['id_projet' => $project],
            ['date_limite' => 'ASC', 'id_tache' => 'DESC']
        );
    }

    /**
     * Get tasks assigned to a specific team member
     */
    public function getTeamMemberTasks(Membres_equipe $teamMember): array
    {
        return $this->taskRepository->findBy(
            [
                'id_projet' => $teamMember->getIdProjet(),
                'id_responsable' => $teamMember->getIdUtilisateur()
            ],
            ['date_limite' => 'ASC']
        );
    }

    /**
     * Get tasks by status
     */
    public function getProjectTasksByStatus(Projets $project, string $status): array
    {
        if (!in_array($status, [
            Taches::STATUT_A_FAIRE,
            Taches::STATUT_EN_COURS,
            Taches::STATUT_TERMINEE
        ])) {
            throw new \Exception('Invalid status.');
        }

        return $this->taskRepository->findBy(
            [
                'id_projet' => $project,
                'statut' => $status
            ]
        );
    }

    /**
     * Get overdue tasks for a project
     */
    public function getOverdueTasks(Projets $project): array
    {
        $now = new \DateTime();
        $allTasks = $this->getProjectTasks($project);
        
        return array_filter(
            $allTasks,
            fn(Taches $task) => 
                $task->getDate_limite() !== null && 
                $task->getDate_limite() < $now &&
                $task->getStatut() !== Taches::STATUT_TERMINEE
        );
    }

    /**
     * Count tasks by status
     */
    public function getTaskCountByStatus(Projets $project): array
    {
        return [
            'todo' => count($this->getProjectTasksByStatus($project, Taches::STATUT_A_FAIRE)),
            'in_progress' => count($this->getProjectTasksByStatus($project, Taches::STATUT_EN_COURS)),
            'completed' => count($this->getProjectTasksByStatus($project, Taches::STATUT_TERMINEE))
        ];
    }

    /**
     * Calculate project completion percentage
     */
    public function getProjectCompletion(Projets $project): float
    {
        $allTasks = $this->getProjectTasks($project);
        if (count($allTasks) === 0) {
            return 0.0;
        }

        $completedTasks = count(
            $this->getProjectTasksByStatus($project, Taches::STATUT_TERMINEE)
        );

        return ($completedTasks / count($allTasks)) * 100;
    }
}

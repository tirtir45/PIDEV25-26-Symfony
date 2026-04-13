<?php

namespace App\Service;

use App\Entity\Projets;
use App\Entity\Utilisateurs;
use App\Repository\ProjetsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for managing projects
 * Handles project CRUD operations with proper authorization and validation
 */
class ProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjetsRepository $projectRepository,
        private EntrepreneurAuthorizationService $authorizationService,
        private ValidatorInterface $validator
    ) {}

    /**
     * Create a new project for an entrepreneur
     */
    public function createProject(
        Utilisateurs $entrepreneur,
        string $titre,
        string $description = '',
        string $secteur = '',
        string $objectifs = ''
    ): Projets {
        // Validate title
        if (empty(trim($titre))) {
            throw new \Exception('Project title is required.');
        }

        if (strlen($titre) > 150) {
            throw new \Exception('Project title cannot exceed 150 characters.');
        }

        // Create new project
        $project = new Projets();
        $project->setId_entrepreneur($entrepreneur);
        $project->setTitre($titre);
        $project->setDescription($description);
        $project->setSecteur($secteur);
        $project->setObjectifs($objectifs);
        $project->setEtat(Projets::ETAT_EN_ATTENTE);
        $project->setDate_soumission(new \DateTime());

        // Validate the entity
        $errors = $this->validator->validate($project);
        if (count($errors) > 0) {
            throw new \Exception((string)$errors);
        }

        // Persist and flush
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Update an existing project
     * Projects can only be edited by their owner
     */
    public function updateProject(
        Utilisateurs $entrepreneur,
        Projets $project,
        string $titre = '',
        string $description = '',
        string $secteur = '',
        string $objectifs = ''
    ): Projets {
        // Verify authorization
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);

        // Only allow editing of specific states
        $this->authorizationService->requireModifiableState($project);

        // Update fields if provided
        if (!empty($titre)) {
            if (strlen($titre) > 150) {
                throw new \Exception('Project title cannot exceed 150 characters.');
            }
            $project->setTitre($titre);
        }

        if (!empty($description)) {
            $project->setDescription($description);
        }

        if (!empty($secteur)) {
            $project->setSecteur($secteur);
        }

        if (!empty($objectifs)) {
            $project->setObjectifs($objectifs);
        }

        // Validate before saving
        $errors = $this->validator->validate($project);
        if (count($errors) > 0) {
            throw new \Exception((string)$errors);
        }

        $this->entityManager->flush();
        return $project;
    }

    /**
     * Delete a project
     * Can only be deleted in specific states (pending or rejected)
     */
    public function deleteProject(
        Utilisateurs $entrepreneur,
        Projets $project
    ): void {
        // Verify authorization
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);

        // Only allow deletion of certain states
        if (!$this->authorizationService->canDeleteProject($project, $entrepreneur)) {
            throw new \Exception(
                'Projects can only be deleted while pending or rejected.'
            );
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();
    }

    /**
     * Update project status
     * Entrepreneurs can change status from Accepted to In Progress or to Completed
     */
    public function updateProjectStatus(
        Utilisateurs $entrepreneur,
        Projets $project,
        string $newStatus
    ): Projets {
        // Verify authorization
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);

        // Validate status transition
        $validTransitions = $this->getValidStatusTransitions($project->getEtat());
        if (!in_array($newStatus, $validTransitions)) {
            throw new \Exception(
                "Cannot transition from {$project->getEtat()} to {$newStatus}."
            );
        }

        $project->setEtat($newStatus);

        $this->entityManager->flush();
        return $project;
    }

    /**
     * Get valid status transitions for a project state
     */
    private function getValidStatusTransitions(string $currentStatus): array
    {
        $transitions = [
            Projets::ETAT_EN_ATTENTE => [],  // Only admin can change from pending
            Projets::ETAT_ACCEPTE => [Projets::ETAT_EN_COURS],
            Projets::ETAT_EN_COURS => [Projets::ETAT_TERMINE],
            Projets::ETAT_REFUSE => [],  // Cannot change from refused
            Projets::ETAT_TERMINE => []   // Cannot change from completed
        ];

        return $transitions[$currentStatus] ?? [];
    }

    /**
     * Get all projects for an entrepreneur
     */
    public function getEntrepreneurProjects(
        Utilisateurs $entrepreneur,
        string $status = '',
        string $sortBy = 'date_soumission'
    ): array {
        $qb = $this->projectRepository->createQueryBuilder('p')
            ->where('p.id_entrepreneur = :entrepreneur')
            ->setParameter('entrepreneur', $entrepreneur);

        if (!empty($status)) {
            $qb->andWhere('p.etat = :status')
                ->setParameter('status', $status);
        }

        // Validate sort field
        $validSortFields = ['date_soumission', 'titre', 'etat'];
        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'date_soumission';
        }

        $qb->orderBy("p.{$sortBy}", 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get project statistics
     */
    public function getProjectStatistics(Projets $project): array
    {
        return [
            'total_members' => count($project->getMembresEquipes()),
            'total_tasks' => count($project->getTachess()),
            'status_distribution' => $this->getStatusDistribution($project)
        ];
    }

    /**
     * Get status distribution for entrepreneur's projects
     */
    public function getEntrepreneurStatistics(Utilisateurs $entrepreneur): array
    {
        $projects = $this->getEntrepreneurProjects($entrepreneur);

        return [
            'total_projects' => count($projects),
            'pending' => count(array_filter(
                $projects,
                fn($p) => $p->getEtat() === Projets::ETAT_EN_ATTENTE
            )),
            'accepted' => count(array_filter(
                $projects,
                fn($p) => $p->getEtat() === Projets::ETAT_ACCEPTE
            )),
            'in_progress' => count(array_filter(
                $projects,
                fn($p) => $p->getEtat() === Projets::ETAT_EN_COURS
            )),
            'completed' => count(array_filter(
                $projects,
                fn($p) => $p->getEtat() === Projets::ETAT_TERMINE
            )),
            'rejected' => count(array_filter(
                $projects,
                fn($p) => $p->getEtat() === Projets::ETAT_REFUSE
            ))
        ];
    }

    /**
     * Helper to get status distribution for a project
     */
    private function getStatusDistribution(Projets $project): array
    {
        $tasks = $project->getTachess();
        
        return [
            'todo' => count(array_filter(
                $tasks->toArray(),
                fn($t) => $t->getStatut() === \App\Entity\Taches::STATUT_A_FAIRE
            )),
            'in_progress' => count(array_filter(
                $tasks->toArray(),
                fn($t) => $t->getStatut() === \App\Entity\Taches::STATUT_EN_COURS
            )),
            'completed' => count(array_filter(
                $tasks->toArray(),
                fn($t) => $t->getStatut() === \App\Entity\Taches::STATUT_TERMINEE
            ))
        ];
    }

    /**
     * Calculate project completion percentage
     */
    public function getProjectCompletion(Projets $project): float
    {
        $tasks = $project->getTachess();
        if ($tasks->count() === 0) {
            return 0.0;
        }

        $completed = count(array_filter(
            $tasks->toArray(),
            fn($t) => $t->getStatut() === \App\Entity\Taches::STATUT_TERMINEE
        ));

        return ($completed / $tasks->count()) * 100;
    }

    /**
     * Get recent projects for an entrepreneur
     */
    public function getRecentProjects(Utilisateurs $entrepreneur, int $limit = 5): array
    {
        return $this->projectRepository->createQueryBuilder('p')
            ->where('p.id_entrepreneur = :entrepreneur')
            ->setParameter('entrepreneur', $entrepreneur)
            ->orderBy('p.date_soumission', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

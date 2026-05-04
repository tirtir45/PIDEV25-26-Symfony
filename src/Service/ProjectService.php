<?php

namespace App\Service;

use App\Entity\Projets;
use App\Entity\Utilisateurs;
use App\Repository\ProjetsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjetsRepository $projectRepository,
        private EntrepreneurAuthorizationService $authorizationService,
        private ValidatorInterface $validator
    ) {}

    public function createProject(Utilisateurs $entrepreneur, string $titre, string $description = '', string $secteur = '', string $objectifs = ''): Projets
    {
        if (empty(trim($titre))) throw new \Exception('Project title is required.');
        if (strlen($titre) > 150) throw new \Exception('Project title cannot exceed 150 characters.');

        $project = new Projets();
        $project->setId_entrepreneur($entrepreneur);
        $project->setTitre($titre);
        $project->setDescription($description);
        $project->setSecteur($secteur);
        $project->setObjectifs($objectifs);
        $project->setEtat(Projets::ETAT_EN_ATTENTE);
        $project->setDate_soumission(new \DateTime());

        $errors = $this->validator->validate($project);
        if (count($errors) > 0) throw new \Exception((string)$errors);

        $this->entityManager->persist($project);
        $this->entityManager->flush();
        return $project;
    }

    public function updateProject(Utilisateurs $entrepreneur, Projets $project, string $titre = '', string $description = '', string $secteur = '', string $objectifs = ''): Projets
    {
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);
        $this->authorizationService->requireModifiableState($project);

        if (!empty($titre)) { if (strlen($titre) > 150) throw new \Exception('Project title cannot exceed 150 characters.'); $project->setTitre($titre); }
        if (!empty($description)) $project->setDescription($description);
        if (!empty($secteur))     $project->setSecteur($secteur);
        if (!empty($objectifs))   $project->setObjectifs($objectifs);

        $errors = $this->validator->validate($project);
        if (count($errors) > 0) throw new \Exception((string)$errors);

        $this->entityManager->flush();
        return $project;
    }

    public function deleteProject(Utilisateurs $entrepreneur, Projets $project): void
    {
        $this->authorizationService->requireProjectOwner($project, $entrepreneur);
        if (!$this->authorizationService->canDeleteProject($project, $entrepreneur)) {
            throw new \Exception('Projects can only be deleted while pending or rejected.');
        }
        $this->entityManager->remove($project);
        $this->entityManager->flush();
    }

    public function getEntrepreneurProjects(Utilisateurs $entrepreneur, string $status = '', string $sortBy = 'date_soumission'): array
    {
        $qb = $this->projectRepository->createQueryBuilder('p')
            ->where('p.id_entrepreneur = :entrepreneur')
            ->setParameter('entrepreneur', $entrepreneur);

        if (!empty($status)) $qb->andWhere('p.etat = :status')->setParameter('status', $status);

        $validSortFields = ['date_soumission', 'titre', 'etat'];
        if (!in_array($sortBy, $validSortFields)) $sortBy = 'date_soumission';
        $qb->orderBy("p.{$sortBy}", 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function getProjectCompletion(Projets $project): float
    {
        $tasks = $project->getTachess();
        if ($tasks->count() === 0) return 0.0;
        $completed = count(array_filter($tasks->toArray(), fn($t) => $t->getStatut() === \App\Entity\Taches::STATUT_TERMINEE));
        return ($completed / $tasks->count()) * 100;
    }
}

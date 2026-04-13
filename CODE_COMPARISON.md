# Code Comparison: Before vs After

This document shows specific examples of improvements made to your code.

---

## 1. Authorization Checks

### BEFORE (Problematic):
```php
// Old controller code - scattered authorization checks
#[Route('/entrepreneur/projet/{id}/gestion', name: 'entrepreneur_gestion_projet')]
public function gestionProjet(Projets $projet, Request $request, EntityManagerInterface $em): Response
{
    // Vérifier que le projet appartient à l'entrepreneur
    $userId = $request->getSession()->get('user_id');
    $entrepreneur = $em->getRepository(Utilisateurs::class)->find($userId);
    if ($projet->getId_entrepreneur() !== $entrepreneur) {  // ❌ Direct comparison
        $this->addFlash('error', 'Vous n\'avez pas accès à ce projet.');
        return $this->redirectToRoute('entrepreneur_dashboard');
    }
    
    // More checks repeated later...
    if ($projet->getEtat() !== Projets::ETAT_ACCEPTE && $projet->getEtat() !== Projets::ETAT_EN_COURS) {
        $this->addFlash('error', 'Ce projet doit être accepté...');
        return $this->redirectToRoute('entrepreneur_dashboard');
    }
  
    // Business logic mixed with authorization...
}
```

### AFTER (Improved):
```php
// New controller code - clean authorization using service
#[Route('/entrepreneur/projet/{id}', name: 'entrepreneur_project_detail')]
public function projectDetail(Projets $projet): Response
{
    $user = $this->getCurrentUser();
    
    // ✅ Centralized authorization checks
    try {
        $this->authorizationService->requireProjectOwner($projet, $user);
    } catch (AccessDeniedException $e) {
        $this->addFlash('error', 'You do not have access to this project.');
        return $this->redirectToRoute('entrepreneur_dashboard');
    }
    
    // Clean business logic follows...
    $members = $this->teamMemberService->getProjectTeamMembers($projet);
    $tasks = $this->taskService->getProjectTasks($projet);
    
    return $this->render('entrepreneur/projet_detail.html.twig', [...]);
}
```

**Benefits:**
- ✅ Authorization logic is centralized and reusable
- ✅ Easy to test
- ✅ Consistent enforcement across all endpoints
- ✅ Clear separation of concerns

---

## 2. Project CRUD Operations

### BEFORE (Only Create & Read):
```php
// Only had create and read operations
public function nouveauProjet(Request $request, EntityManagerInterface $em): Response
{
    // Create logic...
    $em->persist($projet);
    $em->flush();
}

public function projectDetail(Projets $projet): Response
{
    // Read logic - view only
    return $this->render(...);
}
// ❌ Missing: Edit, Delete operations
```

### AFTER (Full CRUD):
```php
// ✅ CREATE
public function nouveauProjet(Request $request): Response
{
    $createdProjet = $this->projectService->createProject(
        $user,
        $titre,
        $description,
        $secteur,
        $objectifs
    );
}

// ✅ READ
public function projectDetail(Projets $projet): Response
{
    // Display project details
}

// ✅ UPDATE  
#[Route('/entrepreneur/projet/{id}/edit', name: 'entrepreneur_edit_projet', methods: ['GET', 'POST'])]
public function editProject(Projets $projet, Request $request): Response
{
    $this->projectService->updateProject(
        $user,
        $projet,
        $newTitle,
        $newDescription,
        $newSector,
        $newObjectives
    );
}

// ✅ DELETE
#[Route('/entrepreneur/projet/{id}/delete', name: 'entrepreneur_delete_projet', methods: ['POST'])]
public function deleteProject(Projets $projet, Request $request): Response
{
    $this->projectService->deleteProject($user, $projet);
}
```

---

## 3. Task Management Improvements

### BEFORE (Incomplete):
```php
// Tasks were created inside gestionProjet route
// No proper validation
// Assigned directly to Utilisateurs, not team members

$tacheForm = $this->createForm(TacheType::class);
if ($tacheForm->isSubmitted() && $tacheForm->isValid()) {
    $tache = $tacheForm->getData();
    $tache->setId_projet($projet);
    
    if (empty($tache->getTitre())) {  // ❌ Runtime check instead of validation
        $this->addFlash('error', 'Le titre de la tâche est obligatoire.');
    } else {
        $em->persist($tache);
        $em->flush();
    }
}
```

### AFTER (Complete & Validated):
```php
// ✅ Separate routes for each operation
#[Route('/entrepreneur/projet/{id}/tache/new', name: 'entrepreneur_new_tache')]
public function newTask(Projets $projet, Request $request): Response
{
    // Authorization checks first
    $this->authorizationService->requireProjectOwner($projet, $user);
    
    // Service handles validation and creation
    $this->taskService->createTask(
        $user,
        $projet,
        $titre,
        $description,
        $dateLimite
    );
}

// ✅ Edit task
#[Route('/entrepreneur/tache/{id}/edit', name: 'entrepreneur_edit_tache')]
public function editTask(Taches $tache, Request $request): Response
{
    $this->taskService->updateTask(
        $user,
        $tache,
        $newTitle,
        $newDescription,
        $newDateLimit,
        $newStatus
    );
}

// ✅ Delete task
#[Route('/entrepreneur/tache/{id}/delete', name: 'entrepreneur_delete_tache')]
public function deleteTache(Taches $tache, Request $request): Response
{
    $this->taskService->deleteTask($user, $tache);
}
```

---

## 4. Team Member Management

### BEFORE (Problematic):
```php
// ❌ Issues:
// 1. Anyone can select any existing user
// 2. No prevention of duplicate assignments
// 3. No clear distinction of roles
// 4. Mixed into main controller

$membreForm = $this->createForm(MembreEquipeType::class);
$membreForm->handleRequest($request);

if ($membreForm->isSubmitted() && $membreForm->isValid()) {
    $membreEquipe = $membreForm->getData();
    $membreEquipe->setIdProjet($projet);
    
    if (!$membreEquipe->getIdUtilisateur()) {
        $this->addFlash('error', 'Veuillez sélectionner un membre.');
    } else {
        $em->persist($membreEquipe);  // ❌ No duplicate check
        $em->flush();
    }
}
```

### AFTER (Proper):
```php
// ✅ Dedicated routes for team member management
#[Route('/entrepreneur/projet/{id}/membre/new', name: 'entrepreneur_new_membre')]
public function newTeamMember(Projets $projet, Request $request): Response
{
    // Authorization check
    $this->authorizationService->requireProjectOwner($projet, $user);
    
    // Service enforces business rules
    $this->teamMemberService->createTeamMember(
        $user,        // ✅ Only entrepreneur can add
        $projet,      // ✅ To their project
        $teamMemberUser,
        $role
    );
}

// ✅ Edit team member
#[Route('/entrepreneur/membre/{id}/edit', name: 'entrepreneur_edit_membre')]
public function editTeamMember(Membres_equipe $membre, Request $request): Response
{
    $this->teamMemberService->updateTeamMember(
        $user,
        $membre,
        $newRole
    );
}

// ✅ Delete team member
#[Route('/entrepreneur/membre/{id}/delete', name: 'entrepreneur_delete_membre')]
public function deleteTeamMember(Membres_equipe $membre, Request $request): Response
{
    $this->teamMemberService->deleteTeamMember($user, $membre);
}
```

The service ensures:
```php
// TeamMemberService.php
public function createTeamMember(Utilisateurs $entrepreneur, Projets $project, ...): Membres_equipe
{
    // ✅ Verify entrepreneur ownership
    $this->authorizationService->requireProjectOwner($project, $entrepreneur);
    
    // ✅ Check for duplicates
    $existingMember = $this->teamMemberRepository->findOneBy([
        'id_projet' => $project,
        'id_utilisateur' => $teamMemberUser
    ]);
    
    if ($existingMember !== null) {
        throw new \Exception('This user is already a team member of this project.');
    }
    
    // ✅ Only then create
    $teamMember = new Membres_equipe();
    $teamMember->setIdProjet($project);
    $teamMember->setIdUtilisateur($teamMemberUser);
    
    return $teamMember;
}
```

---

## 5. Form Validation

### BEFORE (Minimal):
```php
class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du projet',
                'attr' => ['class' => 'form-control']
                // ❌ No validation constraints!
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 5]
                // ❌ No validation constraints!
            ])
            // ... more fields without validation
    }
}
```

### AFTER (Comprehensive):
```php
class ProjetTypeImproved extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Project Title',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Mobile application...',
                    'maxlength' => 150  // ✅ HTML validation
                ],
                'constraints' => [
                    // ✅ Server-side validation
                    new Assert\NotBlank(['message' => 'Title is required.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 150,
                        'minMessage' => 'Minimum {{ limit }} characters.',
                        'maxMessage' => 'Maximum {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5
                ],
                'required' => false,
                'constraints' => [
                    // ✅ Length validation
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'Cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            // ... more fields with proper validation
    }
}
```

---

## 6. Naming Conventions

### BEFORE (Inconsistent):
```php
class Taches
{
    // ❌ Duplicate property names with different conventions
    private ?int $id_tache = null;
    
    public function getIdTache(): ?int { return $this->id_tache; }
    public function getId_tache(): ?int { return $this->id_tache; }  // ❌ Duplicate!

    // ❌ Inconsistent private property names
    private ?Projets $id_projet = null;
    public function getIdProjet(): ?Projets { return $this->id_projet; }
    public function getId_projet(): ?Projets { return $this->id_projet; }  // ❌ Duplicate!
}
```

### AFTER (Consistent):
```php
class Taches
{
    // ✅ Single property name in camelCase
    private ?int $idTache = null;
    
    // ✅ Single getter method
    public function getIdTache(): ?int
    {
        return $this->idTache;
    }

    // ✅ Consistent naming convention
    private ?Projets $idProjet = null;
    
    public function getIdProjet(): ?Projets
    {
        return $this->idProjet;
    }
    
    public function setIdProjet(?Projets $idProjet): static
    {
        $this->idProjet = $idProjet;
        return $this;
    }
}
```

---

## 7. Business Logic Organization

### BEFORE (Logic in Controller):
```php
// ❌ All logic mixed in controller
class EntrepreneurController extends AbstractController
{
    public function gestionProjet(Projets $projet, ...)
    {
        // Authorization logic
        if ($projet->getId_entrepreneur() !== $entrepreneur) {
            // error...
        }
        
        // Validation logic
        if (empty($tache->getTitre())) {
            // error...
        }
        
        // Business logic
        $projet->setEtat($newStatus);
        $em->persist($projet);
        $em->flush();
        
        // Status calculation
        $completed = count(array_filter(...));
        $percentage = ($completed / total) * 100;
        
        return $this->render(..., [...]);
    }
}
```

### AFTER (Logic in Services):
```php
// ✅ Clean separation of concerns
class EntrepreneurController extends AbstractController
{
    public function projectDetail(Projets $projet): Response
    {
        // Authorization handled by service
        $this->authorizationService->requireProjectOwner($projet, $user);
        
        // Business logic delegated to services
        $stats = $this->taskService->getTaskCountByStatus($projet);
        $completion = $this->taskService->getProjectCompletion($projet);
        $members = $this->teamMemberService->getProjectTeamMembers($projet);
        
        // Clean controller - just coordination
        return $this->render('...', [
            'stats' => $stats,
            'completion' => $completion,
            'members' => $members
        ]);
    }
}

// ✅ Dedicated service classes
class TaskService
{
    public function getProjectCompletion(Projets $project): float
    {
        $allTasks = $this->getProjectTasks($project);
        if (count($allTasks) === 0) return 0.0;
        
        $completed = count($this->getProjectTasksByStatus($project, Taches::STATUT_TERMINEE));
        return ($completed / count($allTasks)) * 100;
    }
}
```

---

## 8. Error Handling

### BEFORE (Basic):
```php
if ($form->isSubmitted() && $form->isValid()) {
    $em->persist($projet);
    $em->flush();
    $this->addFlash('success', 'Votre projet a été soumis avec succès !');
    return $this->redirectToRoute('entrepreneur_dashboard');
}
// ❌ No error handling for save failures
```

### AFTER (Comprehensive):
```php
if ($form->isSubmitted() && $form->isValid()) {
    try {
        // ✅ Services validate and throw exceptions
        $createdProjet = $this->projectService->createProject(
            $user,
            $form->get('titre')->getData(),
            // ... other fields
        );
        
        $this->addFlash('success', 'Your project has been submitted successfully!');
        return $this->redirectToRoute('entrepreneur_dashboard');
        
    } catch (\Exception $e) {
        // ✅ Catch and display specific error messages
        $this->addFlash('error', 'Error creating project: ' . $e->getMessage());
        // Continue to re-render form with error
    }
}
```

---

## Summary of Improvements

| Aspect | Before | After |
|--------|--------|-------|
| **CRUD Operations** | Create, Read only | Full CRUD ✅ |
| **Authorization** | Ad-hoc checks | Centralized service ✅ |
| **Form Validation** | Minimal | Comprehensive ✅ |
| **Business Logic** | Mixed in controller | Service layer ✅ |
| **Error Handling** | Basic | Comprehensive ✅ |
| **Naming** | Inconsistent | Consistent ✅ |
| **Code Organization** | Monolithic | Modular ✅ |
| **Testability** | Difficult | Easy ✅ |
| **Maintainability** | Low | High ✅ |
| **Scalability** | Limited | Extensible ✅ |

---

This refactoring follows **SOLID principles** and ensures your application is **production-ready, secure, and maintainable**.

# Quick Reference Card

## 🎯 Implementation Checklist

### Preparation
- [ ] Backup existing code
- [ ] Create feature branch: `git checkout -b feature/code-improvements`
- [ ] Read DELIVERABLES_SUMMARY.md
- [ ] Review IMPROVEMENTS_DOCUMENTATION.md

### Service Layer Setup
- [ ] Copy `src/Service/EntrepreneurAuthorizationService.php`
- [ ] Copy `src/Service/TeamMemberService.php`
- [ ] Copy `src/Service/TaskService.php`
- [ ] Copy `src/Service/ProjectService.php`
- [ ] Configure in `config/services.yaml` (if needed)
- [ ] Clear cache: `php bin/console cache:clear`

### Forms Update
- [ ] Review improved forms
- [ ] Copy `src/Form/ProjetTypeImproved.php`
- [ ] Copy `src/Form/TacheTypeImproved.php`
- [ ] Copy `src/Form/MembreEquipeTypeImproved.php`
- [ ] Update controller to use improved forms

### Controller Updates
- [ ] Choose strategy: Replace or Integrate?
- [ ] Update authorization checks
- [ ] Use new services for business logic
- [ ] Add missing CRUD methods
- [ ] Test all routes

### Template Creation
- [ ] Create `templates/entrepreneur/edit_projet.html.twig`
- [ ] Create `templates/entrepreneur/new_tache.html.twig`
- [ ] Create `templates/entrepreneur/edit_tache.html.twig`
- [ ] Create `templates/entrepreneur/new_membre.html.twig`
- [ ] Create `templates/entrepreneur/edit_membre.html.twig`

### Testing
- [ ] Test project CRUD
- [ ] Test task CRUD
- [ ] Test team member CRUD
- [ ] Test authorization checks
- [ ] Test form validation
- [ ] Test error handling

### Deployment
- [ ] Run all tests
- [ ] Clear cache again
- [ ] Deploy to staging
- [ ] Final testing
- [ ] Deploy to production

---

## 🔑 Key Service Methods

### EntrepreneurAuthorizationService
```php
$auth = new EntrepreneurAuthorizationService();

// Check ownership
$auth->isProjectOwner($projet, $user);     // boolean

// Check if editable
$auth->canModifyProject($projet, $user);   // boolean

// Throw if not owner
$auth->requireProjectOwner($projet, $user);
```

### ProjectService
```php
$project = new ProjectService($em, $repo, $auth, $validator);

// Create
$project->createProject($user, $title, $description, $sector, $objectives);

// Read
$project->getEntrepreneurProjects($user);
$project->getProjectStatistics($projet);

// Update status
$project->updateProjectStatus($user, $projet, $newStatus);

// Delete
$project->deleteProject($user, $projet);
```

### TaskService
```php
$task = new TaskService($em, $repo, $auth, $validator);

// Create
$task->createTask($user, $projet, $titre, $description, $deadline);

// Update
$task->updateTask($user, $tache, $titre, $description, $deadline, $status);

// Get
$task->getProjectTasks($projet);
$task->getTaskCountByStatus($projet);

// Delete
$task->deleteTask($user, $tache);
```

### TeamMemberService
```php
$member = new TeamMemberService($em, $repo, $auth, $validator);

// Create
$member->createTeamMember($entrepreneur, $project, $user, $role);

// Update
$member->updateTeamMember($entrepreneur, $teamMember, $role);

// Get
$member->getProjectTeamMembers($projet);
$member->isTeamMemberOfProject($user, $projet);

// Delete
$member->deleteTeamMember($entrepreneur, $teamMember);
```

---

## 📋 Routes Reference

### Projects
```
GET    /entrepreneur-demo                    # Dashboard
GET    /entrepreneur/projet/nouveau           # Create form
POST   /entrepreneur/projet/nouveau           # Save new
GET    /entrepreneur/projet/{id}              # View detail
GET    /entrepreneur/projet/{id}/edit         # Edit form
POST   /entrepreneur/projet/{id}/edit         # Save edit
POST   /entrepreneur/projet/{id}/delete       # Delete
POST   /entrepreneur/projet/{id}/status       # Change status
GET    /entrepreneur/projet/{id}/gestion      # Manage
```

### Tasks
```
GET    /entrepreneur/projet/{id}/tache/new    # Create form
POST   /entrepreneur/projet/{id}/tache/new    # Save new
GET    /entrepreneur/tache/{id}/edit          # Edit form
POST   /entrepreneur/tache/{id}/edit          # Save edit
POST   /entrepreneur/tache/{id}/delete        # Delete
```

### Team Members
```
GET    /entrepreneur/projet/{id}/membre/new   # Create form
POST   /entrepreneur/projet/{id}/membre/new   # Save new
GET    /entrepreneur/membre/{id}/edit         # Edit form
POST   /entrepreneur/membre/{id}/edit         # Save edit
POST   /entrepreneur/membre/{id}/delete       # Delete
```

---

## 🔐 Authorization Patterns

### Check Ownership
```php
try {
    $this->authorizationService->requireProjectOwner($projet, $user);
} catch (AccessDeniedException $e) {
    $this->addFlash('error', 'You do not own this project.');
    return $this->redirectToRoute('entrepreneur_dashboard');
}
```

### Check Modifiable State
```php
try {
    $this->authorizationService->requireModifiableState($projet);
} catch (AccessDeniedException $e) {
    $this->addFlash('error', 'Cannot modify this project.');
    return $this->redirectToRoute('entrepreneur_dashboard');
}
```

### Combined Check
```php
try {
    $this->authorizationService->requireProjectOwner($projet, $user);
    $this->authorizationService->requireModifiableState($projet);
} catch (AccessDeniedException $e) {
    $this->addFlash('error', $e->getMessage());
    return $this->redirectToRoute('entrepreneur_dashboard');
}
```

---

## 📝 Form Usage

### In Controller (Old)
```php
$form = $this->createForm(ProjetType::class, $projet);
```

### In Controller (New)
```php
$form = $this->createForm(ProjetTypeImproved::class, $projet);
```

### With Options
```php
$form = $this->createForm(TacheTypeImproved::class, $tache, [
    'project' => $projet  // Pass project for filtering
]);
```

---

## ⚠️ Common Mistakes to Avoid

### ❌ Forgetting Authorization
```php
// WRONG - No authorization check!
public function editProject(Projets $projet, Request $request): Response
{
    // Just updates without checking ownership
}

// RIGHT - Always check first
public function editProject(Projets $projet, Request $request): Response
{
    $this->authorizationService->requireProjectOwner($projet, $user);
    // Then proceed
}
```

### ❌ Direct Database Calls Instead of Services
```php
// WRONG - Bypasses validation
$projet->setEtat(Projets::ETAT_EN_COURS);
$em->persist($projet);
$em->flush();

// RIGHT - Use service
$this->projectService->updateProjectStatus($user, $projet, Projets::ETAT_EN_COURS);
```

### ❌ Forgetting to Validate Forms
```php
// WRONG - No validation
$form->handleRequest($request);
if ($form->isSubmitted()) {
    // Creates without checking validity
}

// RIGHT - Check validity
if ($form->isSubmitted() && $form->isValid()) {
    // Safe to create
}
```

### ❌ Not Checking User Ownership
```php
// WRONG - Allows any user to add members
public function addTeamMember(Projets $projet, ...) {
    $this->teamMemberService->createTeamMember(...);
}

// RIGHT - Only entrepreneur who owns project
public function addTeamMember(Projets $projet, ...) {
    $this->authorizationService->requireProjectOwner($projet, $user);
    $this->teamMemberService->createTeamMember($user, $projet, ...);
}
```

---

## 🧪 Quick Test Cases

### Test Project Creation
```bash
POST /entrepreneur/projet/nouveau
- Body: titre=Test, description=Desc, secteur=Tech, objectifs=Obj
- Expected: Project created, redirect to dashboard
```

### Test Project Edit (Unauthorized)
```bash
POST /entrepreneur/projet/1/edit
- As: User who doesn't own project
- Expected: Error message, redirect to dashboard
```

### Test Team Member Add
```bash
POST /entrepreneur/projet/1/membre/new
- Body: id_utilisateur=2, role_equipe=Developer
- Expected: Member added, success message
```

### Test Duplicate Member
```bash
POST /entrepreneur/projet/1/membre/new
- Body: Same user as already added
- Expected: Error "already a member"
```

---

## 📞 Support

### If Services Not Found
Check:
1. Files in `src/Service/` with correct names
2. Namespaces match file structure
3. Cache cleared: `php bin/console cache:clear`

### If Forms Not Validating
Check:
1. Form class extends `AbstractType`
2. Form type specified in controller
3. Constraints properly defined

### If Authorization Failing
Check:
1. User ID retrieved correctly from session
2. Project ownership verified
3. Exception handling in place

### If Templates Not Found
Check:
1. File names match exactly
2. Located in `templates/entrepreneur/`
3. Path quoted correctly in render()

---

## 🚀 Quick Commands

```bash
# Clear cache
php bin/console cache:clear

# List routes
php bin/console debug:routes

# Check services
php bin/console debug:container

# Validate entities
php bin/console doctrine:schema:validate

# Run tests
php bin/console test

# Create migration (if schema changes)
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

---

## 📊 File Structure

```
src/
├── Service/                          (NEW)
│   ├── EntrepreneurAuthorizationService.php
│   ├── ProjectService.php
│   ├── TaskService.php
│   └── TeamMemberService.php
├── Controller/
│   ├── EntrepreneurController.php   (UPDATED)
│   └── EntrepreneurControllerImproved.php (NEW)
├── Form/
│   ├── ProjetType.php               (EXISTING)
│   ├── ProjetTypeImproved.php       (NEW)
│   ├── TacheType.php                (EXISTING)
│   ├── TacheTypeImproved.php        (NEW)
│   ├── MembreEquipeType.php         (EXISTING)
│   └── MembreEquipeTypeImproved.php (NEW)
└── Entity/                          (EXISTING)

templates/
└── entrepreneur/
    ├── dashboard.html.twig          (EXISTING)
    ├── nouveau_projet.html.twig     (EXISTING)
    ├── edit_projet.html.twig        (NEW)
    ├── projet_detail.html.twig      (EXISTING)
    ├── gestion_projet.html.twig     (EXISTING)
    ├── new_tache.html.twig          (NEW)
    ├── edit_tache.html.twig         (NEW)
    ├── new_membre.html.twig         (NEW)
    └── edit_membre.html.twig        (NEW)
```

---

## ✅ Verification Checklist

After implementation:
- [ ] All services load without errors
- [ ] All routes work
- [ ] Authorization checks pass
- [ ] Forms validate correctly
- [ ] Templates render properly
- [ ] No duplicate assignments
- [ ] Status transitions work
- [ ] Statistics calculated correctly
- [ ] Error messages display
- [ ] Authorization failures redirect properly

---

**Keep this card handy during implementation!**

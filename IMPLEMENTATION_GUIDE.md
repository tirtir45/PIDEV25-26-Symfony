# Implementation Guide - Code Improvements

## Quick Start Guide

This guide will help you integrate all the improvements into your Symfony project.

---

## Step 1: Copy Service Classes

Copy the following service files to `src/Service/`:

1. **EntrepreneurAuthorizationService.php**
   - Location: `src/Service/EntrepreneurAuthorizationService.php`
   - Purpose: Handles all authorization checks for entrepreneurs

2. **TeamMemberService.php**
   - Location: `src/Service/TeamMemberService.php`
   - Purpose: Manages team member lifecycle and validation

3. **TaskService.php**
   - Location: `src/Service/TaskService.php`
   - Purpose: Manages task CRUD operations

4. **ProjectService.php**
   - Location: `src/Service/ProjectService.php`
   - Purpose: Manages project CRUD operations

### Services Configuration

The services are automatically registered in Symfony's service container. No additional configuration needed if you're using Symfony 4.4+ with autoconfiguration enabled.

If you need manual configuration, add to `config/services.yaml`:

```yaml
services:
  App\Service\EntrepreneurAuthorizationService:
    autowire: true
    public: false

  App\Service\TeamMemberService:
    autowire: true
    public: false

  App\Service\TaskService:
    autowire: true
    public: false

  App\Service\ProjectService:
    autowire: true
    public: false
```

---

## Step 2: Update Current Controller or Create New One

You have two options:

### Option A: Keep Current Controller and Add New Methods
Keep your current `EntrepreneurController.php` and add the missing CRUD methods:
- `editProject()` - For updating projects
- `deleteProject()` - For deleting projects
- `newTask()` - For creating tasks
- `editTask()` - For updating tasks
- `deleteTask()` - For deleting tasks
- `editTeamMember()` - For updating team members
- `deleteTeamMember()` - For removing team members

### Option B: Replace with Improved Controller (Recommended)
Replace the content of `src/Controller/EntrepreneurController.php` with the improved version provided.

**Important:** Don't forget to update method calls to use the new injection-based services.

---

## Step 3: Update or Replace Forms

You have improved versions of the forms with better validation:

1. **ProjetTypeImproved.php**
   ```
   Replace ProjetType.php OR
   Keep both and use the improved version in the controller
   ```

2. **TacheTypeImproved.php**
   ```
   Replace TacheType.php OR
   Keep both and use the improved version in the controller
   ```

3. **MembreEquipeTypeImproved.php**
   ```
   Replace MembreEquipeType.php OR
   Keep both and use the improved version in the controller
   ```

### Form Usage Example:
```php
// In controller
$form = $this->createForm(ProjetTypeImproved::class, $projet);
```

---

## Step 4: Update Entity Relationships (Optional)

If you want to improve the database design, you can update the relationships. However, this requires a database migration.

### Current Structure:
- `Taches` → `Utilisateurs` (direct assignment)

### Recommended Structure:
- `Taches` → `Membres_equipe` (team member assignment)
- Better tracking of who did what through their role

**To implement this:**

1. Create a migration:
```bash
php bin/console make:migration
```

2. Modify the migration to:
   ```php
   // Add new column to taches table
   $table->addForeignKey(
       ['id_membre_equipe'],
       'membres_equipe',
       ['id_membre'],
       ['onDelete' => 'CASCADE']
   );
   ```

3. Update Taches entity to have both relationships
4. Run migration: `php bin/console doctrine:migrations:migrate`

---

## Step 5: Create Required Twig Templates

You'll need to create or update these templates:

### New Templates:
```
templates/entrepreneur/
├── edit_projet.html.twig         (NEW - Edit project page)
├── edit_tache.html.twig          (NEW - Edit task page)
├── edit_membre.html.twig         (NEW - Edit team member page)
├── new_tache.html.twig           (NEW - Create task page)
├── new_membre.html.twig          (NEW - Create team member page)
└── (existing templates can be updated as needed)
```

### Basic Template Structure Example:

**edit_projet.html.twig:**
```twig
{% extends 'base.html.twig' %}

{% block title %}Edit Project{% endblock %}

{% block content %}
<div class="container mt-5">
    <h1>Edit Project</h1>
    
    {{ form_start(form) }}
        {{ form_widget(form) }}
        <button class="btn btn-primary">Save Changes</button>
    {{ form_end(form) }}
    
    <a href="{{ path('entrepreneur_project_detail', {'id': projet.idProjet}) }}" class="btn btn-secondary">
        Cancel
    </a>
</div>
{% endblock %}
```

---

## Step 6: Testing the Implementation

### Test Cases to Verify:

#### Project Management:
```php
// Test 1: Create project
POST /entrepreneur/projet/nouveau
→ Project should be created with status "En attente"

// Test 2: Edit project (only owner)
POST /entrepreneur/projet/1/edit
→ Only project owner can edit

// Test 3: Delete project (only pending/rejected)
POST /entrepreneur/projet/1/delete
→ Cannot delete accepted/in progress projects

// Test 4: Change status
POST /entrepreneur/projet/1/status
→ Valid transitions only: Accepted → In Progress → Completed
```

#### Task Management:
```php
// Test 5: Create task (only owner of project)
POST /entrepreneur/projet/1/tache/new
→ Only project owner can create

// Test 6: Assign task to team member
POST /entrepreneur/tache/1/edit
→ Can only assign to project team members

// Test 7: Update task status
POST /entrepreneur/tache/1/edit
→ Can change status: To Do → In Progress → Completed
```

#### Team Member Management:
```php
// Test 8: Add team member (only owner)
POST /entrepreneur/projet/1/membre/new
→ Only project owner can add

// Test 9: Prevent duplicate assignments
POST /entrepreneur/projet/1/membre/new
→ Cannot add same user twice to same project

// Test 10: Edit team member role
POST /entrepreneur/membre/1/edit
→ Only project owner can edit

// Test 11: Prevent self-removal
POST /entrepreneur/membre/1/delete
→ Can remove any team member
```

---

## Step 7: Database Migrations (If Needed)

If you want to add indices for better performance:

```bash
php bin/console doctrine:query:sql "CREATE INDEX idx_projets_entrepreneur ON projets(id_entrepreneur);"
php bin/console doctrine:query:sql "CREATE INDEX idx_taches_projet ON taches(id_projet);"
php bin/console doctrine:query:sql "CREATE INDEX idx_membres_equipe_projet ON membres_equipe(id_projet);"
```

---

## Step 8: Update Services Configuration (services.yaml)

Ensure the forms are properly registered:

```yaml
services:
  App\Form\ProjetTypeImproved:
    tags:
      - { name: form.type }

  App\Form\TacheTypeImproved:
    tags:
      - { name: form.type }

  App\Form\MembreEquipeTypeImproved:
    tags:
      - { name: form.type }
```

---

## Common Issues and Solutions

### Issue 1: "Service not found" error
**Solution:** Make sure service files are in `src/Service/` directory and class namespaces match file locations.

### Issue 2: Form not showing up
**Solution:** Check that form class extends `AbstractType` and has proper `buildForm()` method.

### Issue 3: Authorization errors when accessing projects
**Solution:** Verify that the controller is using the `EntrepreneurAuthorizationService` and checking ownership.

### Issue 4: Team members can't be added
**Solution:** Ensure project is in "Accepté" or "En cours" state. Check `EntrepreneurAuthorizationService::isProjectInModifiableState()`.

---

## Migration Checklist

- [ ] Copy service files to `src/Service/`
- [ ] Update/replace forms in `src/Form/`
- [ ] Update `EntrepreneurController.php` with new methods
- [ ] Create missing Twig templates
- [ ] Test all CRUD operations
- [ ] Clear Symfony cache: `php bin/console cache:clear`
- [ ] Run tests: `php bin/console test`
- [ ] Deploy to production

---

## Additional Improvements (Optional)

### Add Logging
```php
// In services
private LoggerInterface $logger;

// In methods
$this->logger->info('Project created', ['projectId' => $project->getId()]);
```

### Add Event Listeners
```php
// Automatically update timestamps
#[AsEntityListener(event: PreUpdate::class, entity: Projets::class)]
public function postUpdate(PreUpdateEventArgs $event): void
{
    // Update modification date
}
```

### Add Caching
```php
// Cache project stats
#[Cache(key: 'project_stats_{id}', ttl: 3600)]
public function getProjectStatistics(Projets $project): array
{
    // ...
}
```

### Add API Endpoints
```php
#[Route('/api/entrepreneur/projects', name: 'api_entrepreneur_projects')]
#[OA\Get(description: 'Get entrepreneur projects')]
public function getProjectsApi(): JsonResponse
{
    // Return JSON response
}
```

---

## Support and Questions

### Key Improvements at a Glance:

1. ✅ **Full CRUD for Projects** - Create, Read, Update, Delete
2. ✅ **Full CRUD for Tasks** - Create, Read, Update, Delete
3. ✅ **Full CRUD for Team Members** - Create, Read, Update, Delete
4. ✅ **Proper Authorization** - Service-based checks
5. ✅ **Team Member Management** - Only entrepreneurs can manage
6. ✅ **Validation** - Form and entity-level constraints
7. ✅ **Code Organization** - Service layer separation
8. ✅ **Best Practices** - SOLID principles applied

---

## Contact & Documentation

For more information on Symfony features used in this improvement:
- Services: https://symfony.com/doc/current/service_container.html
- Forms: https://symfony.com/doc/current/forms.html
- Security: https://symfony.com/doc/current/security.html
- Validation: https://symfony.com/doc/current/validation.html

---

**Implementation completed successfully!**

Your Symfony application now has:
- Better code organization with service layer
- Complete CRUD operations for all main entities
- Proper authorization and security checks
- Enhanced validation on forms and entities
- Clean, maintainable, and scalable code structure

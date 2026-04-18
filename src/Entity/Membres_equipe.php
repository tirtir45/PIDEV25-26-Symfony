<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'membres_equipe')]
class Membres_equipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_membre', type: 'integer')]
    private ?int $id_membre = null;

    #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: 'membres_equipes')]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private ?Projets $id_projet = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: 'membres_equipes')]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private ?Utilisateurs $id_utilisateur = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $role_equipe = null;

    #[ORM\OneToMany(mappedBy: 'id_membre_equipe', targetEntity: User_story_assignments::class)]
    private Collection $user_story_assignmentss;

    #[ORM\OneToMany(mappedBy: 'id_membre_equipe', targetEntity: User_stories::class)]
    private Collection $user_storiess;

    // ===== CONSTRUCTOR =====

    public function __construct()
    {
        $this->user_story_assignmentss = new ArrayCollection();
        $this->user_storiess = new ArrayCollection();
    }

    // ===== GETTERS & SETTERS =====

    public function getIdMembre(): ?int
    {
        return $this->id_membre;
    }

    public function getIdProjet(): ?Projets
    {
        return $this->id_projet;
    }

    public function setIdProjet(?Projets $id_projet): static
    {
        $this->id_projet = $id_projet;
        return $this;
    }

    public function getIdUtilisateur(): ?Utilisateurs
    {
        return $this->id_utilisateur;
    }

    public function setIdUtilisateur(?Utilisateurs $id_utilisateur): static
    {
        $this->id_utilisateur = $id_utilisateur;
        return $this;
    }

    public function getRoleEquipe(): ?string
    {
        return $this->role_equipe;
    }

    public function setRoleEquipe(?string $role_equipe): static
    {
        $this->role_equipe = $role_equipe;
        return $this;
    }

    // ===== USER STORY ASSIGNMENTS =====

    public function getUserStoryAssignmentss(): Collection
    {
        return $this->user_story_assignmentss;
    }

    public function addUserStoryAssignments(User_story_assignments $user_story_assignments): static
    {
        if (!$this->user_story_assignmentss->contains($user_story_assignments)) {
            $this->user_story_assignmentss[] = $user_story_assignments;
            $user_story_assignments->setIdMembreEquipe($this);
        }
        return $this;
    }

    public function removeUserStoryAssignments(User_story_assignments $user_story_assignments): static
    {
        if ($this->user_story_assignmentss->removeElement($user_story_assignments)) {
            if ($user_story_assignments->getIdMembreEquipe() === $this) {
                $user_story_assignments->setIdMembreEquipe(null);
            }
        }
        return $this;
    }

    // ===== USER STORIES =====

    public function getUserStoriess(): Collection
    {
        return $this->user_storiess;
    }

    public function addUserStories(User_stories $user_stories): static
    {
        if (!$this->user_storiess->contains($user_stories)) {
            $this->user_storiess[] = $user_stories;
            $user_stories->setIdMembreEquipe($this);
        }
        return $this;
    }

    public function removeUserStories(User_stories $user_stories): static
    {
        if ($this->user_storiess->removeElement($user_stories)) {
            if ($user_stories->getIdMembreEquipe() === $this) {
                $user_stories->setIdMembreEquipe(null);
            }
        }
        return $this;
    }
}
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateur;
use Doctrine\Common\Collections\Collection;
use App\Entity\User_stories;

#[ORM\Entity]
class Membres_equipe
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_membre;

        #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: "membres_equipes")]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private Projets $id_projet;

        #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "membres_equipes")]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateur $id_utilisateur;

    #[ORM\Column(type: "string", length: 100)]
    private string $role_equipe;

    public function getId_membre()
    {
        return $this->id_membre;
    }

    public function setId_membre($value)
    {
        $this->id_membre = $value;
    }

    public function getId_projet()
    {
        return $this->id_projet;
    }

    public function setId_projet($value)
    {
        $this->id_projet = $value;
    }

    public function getId_utilisateur()
    {
        return $this->id_utilisateur;
    }

    public function setId_utilisateur($value)
    {
        $this->id_utilisateur = $value;
    }

    public function getRole_equipe()
    {
        return $this->role_equipe;
    }

    public function setRole_equipe($value)
    {
        $this->role_equipe = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_membre_equipe", targetEntity: User_story_assignments::class)]
    private Collection $user_story_assignmentss;

        public function getUser_story_assignmentss(): Collection
        {
            return $this->user_story_assignmentss;
        }
    
        public function addUser_story_assignments(User_story_assignments $user_story_assignments): self
        {
            if (!$this->user_story_assignmentss->contains($user_story_assignments)) {
                $this->user_story_assignmentss[] = $user_story_assignments;
                $user_story_assignments->setId_membre_equipe($this);
            }
    
            return $this;
        }
    
        public function removeUser_story_assignments(User_story_assignments $user_story_assignments): self
        {
            if ($this->user_story_assignmentss->removeElement($user_story_assignments)) {
                // set the owning side to null (unless already changed)
                if ($user_story_assignments->getId_membre_equipe() === $this) {
                    $user_story_assignments->setId_membre_equipe(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "id_membre_equipe", targetEntity: User_stories::class)]
    private Collection $user_storiess;
}

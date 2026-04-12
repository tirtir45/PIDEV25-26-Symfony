<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;
use Doctrine\Common\Collections\Collection;
use App\Entity\User_stories;

#[ORM\Entity]
class Projets
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_projet;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "projetss")]
    #[ORM\JoinColumn(name: 'id_entrepreneur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_entrepreneur;

    #[ORM\Column(type: "string", length: 150)]
    private string $titre;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "string", length: 100)]
    private string $secteur;

    #[ORM\Column(type: "text")]
    private string $objectifs;

    #[ORM\Column(type: "string")]
    private string $etat;

    #[ORM\Column(type: "text")]
    private string $commentaire_admin;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_soumission;

    #[ORM\Column(type: "float")]
    private float $note_moyenne;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_evaluation;

    #[ORM\Column(type: "boolean")]
    private bool $evaluation_automatique;

    #[ORM\Column(type: "string", length: 500)]
    private string $question1_answer;

    #[ORM\Column(type: "string", length: 500)]
    private string $question2_answer;

    #[ORM\Column(type: "string", length: 500)]
    private string $question3_answer;

    public function getId_projet()
    {
        return $this->id_projet;
    }

    public function setId_projet($value)
    {
        $this->id_projet = $value;
    }

    public function getId_entrepreneur()
    {
        return $this->id_entrepreneur;
    }

    public function setId_entrepreneur($value)
    {
        $this->id_entrepreneur = $value;
    }

    public function getTitre()
    {
        return $this->titre;
    }

    public function setTitre($value)
    {
        $this->titre = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getSecteur()
    {
        return $this->secteur;
    }

    public function setSecteur($value)
    {
        $this->secteur = $value;
    }

    public function getObjectifs()
    {
        return $this->objectifs;
    }

    public function setObjectifs($value)
    {
        $this->objectifs = $value;
    }

    public function getEtat()
    {
        return $this->etat;
    }

    public function setEtat($value)
    {
        $this->etat = $value;
    }

    public function getCommentaire_admin()
    {
        return $this->commentaire_admin;
    }

    public function setCommentaire_admin($value)
    {
        $this->commentaire_admin = $value;
    }

    public function getDate_soumission()
    {
        return $this->date_soumission;
    }

    public function setDate_soumission($value)
    {
        $this->date_soumission = $value;
    }

    public function getNote_moyenne()
    {
        return $this->note_moyenne;
    }

    public function setNote_moyenne($value)
    {
        $this->note_moyenne = $value;
    }

    public function getDate_evaluation()
    {
        return $this->date_evaluation;
    }

    public function setDate_evaluation($value)
    {
        $this->date_evaluation = $value;
    }

    public function getEvaluation_automatique()
    {
        return $this->evaluation_automatique;
    }

    public function setEvaluation_automatique($value)
    {
        $this->evaluation_automatique = $value;
    }

    public function getQuestion1_answer()
    {
        return $this->question1_answer;
    }

    public function setQuestion1_answer($value)
    {
        $this->question1_answer = $value;
    }

    public function getQuestion2_answer()
    {
        return $this->question2_answer;
    }

    public function setQuestion2_answer($value)
    {
        $this->question2_answer = $value;
    }

    public function getQuestion3_answer()
    {
        return $this->question3_answer;
    }

    public function setQuestion3_answer($value)
    {
        $this->question3_answer = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_projet", targetEntity: Sprints::class)]
    private Collection $sprintss;

        public function getSprintss(): Collection
        {
            return $this->sprintss;
        }
    
        public function addSprints(Sprints $sprints): self
        {
            if (!$this->sprintss->contains($sprints)) {
                $this->sprintss[] = $sprints;
                $sprints->setId_projet($this);
            }
    
            return $this;
        }
    
        public function removeSprints(Sprints $sprints): self
        {
            if ($this->sprintss->removeElement($sprints)) {
                // set the owning side to null (unless already changed)
                if ($sprints->getId_projet() === $this) {
                    $sprints->setId_projet(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "id_projet", targetEntity: Evaluations_projet::class)]
    private Collection $evaluations_projets;

    #[ORM\OneToMany(mappedBy: "id_projet", targetEntity: Membres_equipe::class)]
    private Collection $membres_equipes;

    #[ORM\OneToMany(mappedBy: "id_projet", targetEntity: Userstory::class)]
    private Collection $userstorys;

    #[ORM\OneToMany(mappedBy: "id_projet", targetEntity: Taches::class)]
    private Collection $tachess;

    #[ORM\OneToMany(mappedBy: "id_projet", targetEntity: User_stories::class)]
    private Collection $user_storiess;
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Membres_equipe;
use Doctrine\Common\Collections\Collection;
use App\Entity\User_story_assignments;

#[ORM\Entity]
class User_stories
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_user_story;

        #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: "user_storiess")]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private Projets $id_projet;

        #[ORM\ManyToOne(targetEntity: Sprints::class, inversedBy: "user_storiess")]
    #[ORM\JoinColumn(name: 'id_sprint', referencedColumnName: 'id_sprint', onDelete: 'CASCADE')]
    private Sprints $id_sprint;

    #[ORM\Column(type: "string", length: 200)]
    private string $titre;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "string", length: 200)]
    private string $en_tant_que;

    #[ORM\Column(type: "string", length: 500)]
    private string $je_veux;

    #[ORM\Column(type: "string", length: 500)]
    private string $afin_de;

    #[ORM\Column(type: "integer")]
    private int $story_points;

    #[ORM\Column(type: "string")]
    private string $priorite;

    #[ORM\Column(type: "string")]
    private string $statut;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_creation;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_completion;

    #[ORM\Column(type: "text")]
    private string $criteres_acceptation;

        #[ORM\ManyToOne(targetEntity: Membres_equipe::class, inversedBy: "user_storiess")]
    #[ORM\JoinColumn(name: 'id_membre_equipe', referencedColumnName: 'id_membre', onDelete: 'CASCADE')]
    private Membres_equipe $id_membre_equipe;

    #[ORM\Column(type: "string", length: 100)]
    private string $membre_nom;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_debut;

    public function getId_user_story()
    {
        return $this->id_user_story;
    }

    public function setId_user_story($value)
    {
        $this->id_user_story = $value;
    }

    public function getId_projet()
    {
        return $this->id_projet;
    }

    public function setId_projet($value)
    {
        $this->id_projet = $value;
    }

    public function getId_sprint()
    {
        return $this->id_sprint;
    }

    public function setId_sprint($value)
    {
        $this->id_sprint = $value;
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

    public function getEn_tant_que()
    {
        return $this->en_tant_que;
    }

    public function setEn_tant_que($value)
    {
        $this->en_tant_que = $value;
    }

    public function getJe_veux()
    {
        return $this->je_veux;
    }

    public function setJe_veux($value)
    {
        $this->je_veux = $value;
    }

    public function getAfin_de()
    {
        return $this->afin_de;
    }

    public function setAfin_de($value)
    {
        $this->afin_de = $value;
    }

    public function getStory_points()
    {
        return $this->story_points;
    }

    public function setStory_points($value)
    {
        $this->story_points = $value;
    }

    public function getPriorite()
    {
        return $this->priorite;
    }

    public function setPriorite($value)
    {
        $this->priorite = $value;
    }

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($value)
    {
        $this->statut = $value;
    }

    public function getDate_creation()
    {
        return $this->date_creation;
    }

    public function setDate_creation($value)
    {
        $this->date_creation = $value;
    }

    public function getDate_completion()
    {
        return $this->date_completion;
    }

    public function setDate_completion($value)
    {
        $this->date_completion = $value;
    }

    public function getCriteres_acceptation()
    {
        return $this->criteres_acceptation;
    }

    public function setCriteres_acceptation($value)
    {
        $this->criteres_acceptation = $value;
    }

    public function getId_membre_equipe()
    {
        return $this->id_membre_equipe;
    }

    public function setId_membre_equipe($value)
    {
        $this->id_membre_equipe = $value;
    }

    public function getMembre_nom()
    {
        return $this->membre_nom;
    }

    public function setMembre_nom($value)
    {
        $this->membre_nom = $value;
    }

    public function getDate_debut()
    {
        return $this->date_debut;
    }

    public function setDate_debut($value)
    {
        $this->date_debut = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_user_story", targetEntity: User_story_assignments::class)]
    private Collection $user_story_assignmentss;
}

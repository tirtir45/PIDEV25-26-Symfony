<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Projets;
use Doctrine\Common\Collections\Collection;
use App\Entity\Daily_scrums;

#[ORM\Entity]
class Sprints
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_sprint", type: "integer")]
    private ?int $id_sprint = null;

        #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: "sprintss")]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private Projets $id_projet;

    #[ORM\Column(type: "string", length: 100)]
    private string $nom;

    #[ORM\Column(type: "text")]
    private string $objectif;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_debut;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_fin;

    #[ORM\Column(type: "string")]
    private string $statut;

    #[ORM\Column(type: "integer")]
    private int $capacite_equipe;

    #[ORM\Column(type: "integer")]
    private int $velocite_prevue;

    #[ORM\Column(type: "integer")]
    private int $velocite_reelle;

    public function getId_sprint()
    {
        return $this->id_sprint;
    }

    public function setId_sprint($value)
    {
        $this->id_sprint = $value;
    }

    public function getId_projet()
    {
        return $this->id_projet;
    }

    public function setId_projet($value)
    {
        $this->id_projet = $value;
    }

    public function getNom()
    {
        return $this->nom;
    }

    public function setNom($value)
    {
        $this->nom = $value;
    }

    public function getObjectif()
    {
        return $this->objectif;
    }

    public function setObjectif($value)
    {
        $this->objectif = $value;
    }

    public function getDate_debut()
    {
        return $this->date_debut;
    }

    public function setDate_debut($value)
    {
        $this->date_debut = $value;
    }

    public function getDate_fin()
    {
        return $this->date_fin;
    }

    public function setDate_fin($value)
    {
        $this->date_fin = $value;
    }

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($value)
    {
        $this->statut = $value;
    }

    public function getCapacite_equipe()
    {
        return $this->capacite_equipe;
    }

    public function setCapacite_equipe($value)
    {
        $this->capacite_equipe = $value;
    }

    public function getVelocite_prevue()
    {
        return $this->velocite_prevue;
    }

    public function setVelocite_prevue($value)
    {
        $this->velocite_prevue = $value;
    }

    public function getVelocite_reelle()
    {
        return $this->velocite_reelle;
    }

    public function setVelocite_reelle($value)
    {
        $this->velocite_reelle = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_sprint", targetEntity: Daily_scrums::class)]
    private Collection $daily_scrumss;

    #[ORM\OneToMany(mappedBy: "id_sprint", targetEntity: Userstory::class)]
    private Collection $userstorys;

    #[ORM\OneToMany(mappedBy: "id_sprint", targetEntity: Taches::class)]
    private Collection $tachess;

    #[ORM\OneToMany(mappedBy: "id_sprint", targetEntity: User_stories::class)]
    private Collection $user_storiess;
}

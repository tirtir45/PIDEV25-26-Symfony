<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Sprints;

#[ORM\Entity]
class Userstory
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_userstory;

        #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: "userstorys")]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private Projets $id_projet;

    #[ORM\Column(type: "string", length: 255)]
    private string $titre;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "integer")]
    private int $story_points;

    #[ORM\Column(type: "string")]
    private string $priorite;

    #[ORM\Column(type: "string")]
    private string $statut;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_creation;

        #[ORM\ManyToOne(targetEntity: Sprints::class, inversedBy: "userstorys")]
    #[ORM\JoinColumn(name: 'id_sprint', referencedColumnName: 'id_sprint', onDelete: 'CASCADE')]
    private Sprints $id_sprint;

    #[ORM\Column(type: "string", length: 255)]
    private string $en_tant_que;

    #[ORM\Column(type: "string", length: 255)]
    private string $je_veux;

    #[ORM\Column(type: "string", length: 255)]
    private string $afin_de;

    #[ORM\Column(type: "text")]
    private string $criteres_acceptation;

    public function getId_userstory()
    {
        return $this->id_userstory;
    }

    public function setId_userstory($value)
    {
        $this->id_userstory = $value;
    }

    public function getId_projet()
    {
        return $this->id_projet;
    }

    public function setId_projet($value)
    {
        $this->id_projet = $value;
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

    public function getId_sprint()
    {
        return $this->id_sprint;
    }

    public function setId_sprint($value)
    {
        $this->id_sprint = $value;
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

    public function getCriteres_acceptation()
    {
        return $this->criteres_acceptation;
    }

    public function setCriteres_acceptation($value)
    {
        $this->criteres_acceptation = $value;
    }
}

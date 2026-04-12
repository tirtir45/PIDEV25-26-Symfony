<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;

#[ORM\Entity]
class Taches
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_tache;

        #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: "tachess")]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private Projets $id_projet;

        #[ORM\ManyToOne(targetEntity: Sprints::class, inversedBy: "tachess")]
    #[ORM\JoinColumn(name: 'id_sprint', referencedColumnName: 'id_sprint', onDelete: 'CASCADE')]
    private Sprints $id_sprint;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "tachess")]
    #[ORM\JoinColumn(name: 'id_responsable', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_responsable;

    #[ORM\Column(type: "string", length: 150)]
    private string $titre;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "string")]
    private string $statut;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_limite;

    public function getId_tache()
    {
        return $this->id_tache;
    }

    public function setId_tache($value)
    {
        $this->id_tache = $value;
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

    public function getId_responsable()
    {
        return $this->id_responsable;
    }

    public function setId_responsable($value)
    {
        $this->id_responsable = $value;
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

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($value)
    {
        $this->statut = $value;
    }

    public function getDate_limite()
    {
        return $this->date_limite;
    }

    public function setDate_limite($value)
    {
        $this->date_limite = $value;
    }
}

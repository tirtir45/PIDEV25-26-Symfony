<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'taches')]
class Taches
{
    // ===== CONSTANTS (from Tache.php) =====
    public const STATUT_A_FAIRE = 'À faire';
    public const STATUT_EN_COURS = 'En cours';
    public const STATUT_TERMINEE = 'Terminée';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_tache', type: 'integer')]
    private ?int $id_tache = null;

    #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: 'tachess')]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private ?Projets $id_projet = null;

    #[ORM\ManyToOne(targetEntity: Sprints::class, inversedBy: 'tachess')]
    #[ORM\JoinColumn(name: 'id_sprint', referencedColumnName: 'id_sprint', onDelete: 'CASCADE')]
    private ?Sprints $id_sprint = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: 'tachess')]
    #[ORM\JoinColumn(name: 'id_responsable', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private ?Utilisateurs $id_responsable = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', options: ['default' => 'À faire'])]
    private ?string $statut = self::STATUT_A_FAIRE;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $date_limite = null;

    // ===== GETTERS & SETTERS =====

    public function getIdTache(): ?int { return $this->id_tache; }
    public function getId_tache(): ?int { return $this->id_tache; }

    public function getIdProjet(): ?Projets { return $this->id_projet; }
    public function getId_projet(): ?Projets { return $this->id_projet; }
    public function setId_projet(?Projets $id_projet): static
    {
        $this->id_projet = $id_projet;
        return $this;
    }

    public function getIdSprint(): ?Sprints { return $this->id_sprint; }
    public function getId_sprint(): ?Sprints { return $this->id_sprint; }
    public function setId_sprint(?Sprints $id_sprint): static
    {
        $this->id_sprint = $id_sprint;
        return $this;
    }

    public function getIdResponsable(): ?Utilisateurs { return $this->id_responsable; }
    public function getId_responsable(): ?Utilisateurs { return $this->id_responsable; }
    public function setId_responsable(?Utilisateurs $id_responsable): static
    {
        $this->id_responsable = $id_responsable;
        return $this;
    }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $titre): static { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getDateLimite(): ?\DateTimeInterface { return $this->date_limite; }
    public function getDate_limite(): ?\DateTimeInterface { return $this->date_limite; }
    public function setDate_limite(?\DateTimeInterface $date_limite): static
    {
        $this->date_limite = $date_limite;
        return $this;
    }
    public function setDateLimite(?\DateTimeInterface $date_limite): static
    {
        $this->date_limite = $date_limite;
        return $this;
    }
}
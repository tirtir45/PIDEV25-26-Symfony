<?php

namespace App\Entity;

use App\Repository\RessourceProjetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RessourceProjetRepository::class)]
#[ORM\Table(name: 'ressources_projet')]
class RessourceProjet
{
    public const STATUT_DISPONIBLE = 'disponible';
    public const STATUT_OCCUPE = 'occupe';
    public const STATUT_MAINTENANCE = 'maintenance';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Projets::class)]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', nullable: false, onDelete: 'CASCADE')]
    private ?Projets $projet = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emplacement = null;

    #[ORM\Column(length: 20, options: ['default' => 'disponible'])]
    private string $statut = self::STATUT_DISPONIBLE;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $caracteristiques = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getProjet(): ?Projets { return $this->projet; }
    public function setProjet(?Projets $projet): static { $this->projet = $projet; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getEmplacement(): ?string { return $this->emplacement; }
    public function setEmplacement(?string $emplacement): static { $this->emplacement = $emplacement; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getCaracteristiques(): ?array { return $this->caracteristiques; }
    public function setCaracteristiques(?array $caracteristiques): static { $this->caracteristiques = $caracteristiques; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }

    public function getIcon(): string
    {
        return match($this->type) {
            'materiel' => '🖥️',
            'logiciel' => '💻',
            'salle'    => '🏢',
            'vehicule' => '🚗',
            default    => '📦',
        };
    }
}

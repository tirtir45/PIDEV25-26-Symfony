<?php

namespace App\Entity;

use App\Repository\ReclamationsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReclamationsRepository::class)]
#[ORM\Table(name: 'reclamations')]
class Reclamations
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reclamation')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: 'reclamations')]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateurs $utilisateur = null;

    #[ORM\Column(length: 150)]
    private ?string $sujet = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'EN_ATTENTE';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\OneToMany(mappedBy: 'id_reclamation', targetEntity: Reclamation_commentaires::class)]
    private Collection $reclamation_commentairess;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->reclamation_commentairess = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): ?Utilisateurs { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateurs $u): static { $this->utilisateur = $u; return $this; }

    public function getSujet(): ?string { return $this->sujet; }
    public function setSujet(string $s): static { $this->sujet = $s; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $d): static { $this->description = $d; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): static { $this->statut = $s; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }

    public function getReclamationCommentairess(): Collection { return $this->reclamation_commentairess; }
}
<?php

namespace App\Entity;

use App\Repository\CommentairesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentairesRepository::class)]
#[ORM\Table(name: 'commentaires')]
class Commentaires
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Taches::class)]
    #[ORM\JoinColumn(name: 'id_tache', referencedColumnName: 'id_tache', nullable: false, onDelete: 'CASCADE')]
    private ?Taches $tache = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class)]
    #[ORM\JoinColumn(name: 'id_auteur', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateurs $auteur = null;

    #[ORM\Column(type: 'text')]
    private ?string $contenu = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true, options: ['default' => 'commentaire'])]
    private ?string $type = 'commentaire';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTache(): ?Taches { return $this->tache; }
    public function setTache(?Taches $tache): static { $this->tache = $tache; return $this; }
    public function getAuteur(): ?Utilisateurs { return $this->auteur; }
    public function setAuteur(?Utilisateurs $auteur): static { $this->auteur = $auteur; return $this; }
    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
}

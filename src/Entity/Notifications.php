<?php

namespace App\Entity;

use App\Repository\NotificationsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationsRepository::class)]
#[ORM\Table(name: 'notifications')]
class Notifications
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateurs $utilisateur = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lien = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $lu = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateurs { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateurs $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getLien(): ?string { return $this->lien; }
    public function setLien(?string $lien): static { $this->lien = $lien; return $this; }
    public function isLu(): bool { return $this->lu; }
    public function setLu(bool $lu): static { $this->lu = $lu; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
}

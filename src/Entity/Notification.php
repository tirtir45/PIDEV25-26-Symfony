<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private ?Utilisateurs $utilisateur = null;

    #[ORM\Column(length: 50)]
    private string $type = 'info'; // info, success, warning, error

    #[ORM\Column(length: 255)]
    private string $titre = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(nullable: true)]
    private ?string $lien = null;

    #[ORM\Column]
    private bool $lu = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUtilisateur(): ?Utilisateurs { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateurs $u): static { $this->utilisateur = $u; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $t): static { $this->type = $t; return $this; }

    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $t): static { $this->titre = $t; return $this; }

    public function getMessage(): string { return $this->message; }
    public function setMessage(string $m): static { $this->message = $m; return $this; }

    public function getLien(): ?string { return $this->lien; }
    public function setLien(?string $l): static { $this->lien = $l; return $this; }

    public function isLu(): bool { return $this->lu; }
    public function setLu(bool $l): static { $this->lu = $l; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}

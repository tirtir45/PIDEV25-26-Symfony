<?php

namespace App\Entity;

use App\Repository\RolesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RolesRepository::class)]
#[ORM\Table(name: 'roles')]
class Roles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_role')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom_role', length: 50, unique: true)]
    private ?string $nomRole = null;

    public function getId(): ?int { return $this->id; }

    public function getNomRole(): ?string { return $this->nomRole; }

    public function setNomRole(string $nomRole): static
    {
        $this->nomRole = $nomRole;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nomRole ?? '';
    }
}
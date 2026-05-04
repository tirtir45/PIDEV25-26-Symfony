<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ResourceReportRepository;

#[ORM\Entity(repositoryClass: ResourceReportRepository::class)]
#[ORM\Table(name: "resource_reports")]
class ResourceReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ressources::class)]
    #[ORM\JoinColumn(name: "id_ressource", referencedColumnName: "id_ressource", nullable: false, onDelete: "CASCADE")]
    private ?Ressources $ressource = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class)]
    #[ORM\JoinColumn(name: "id_utilisateur", referencedColumnName: "id_utilisateur", nullable: false)]
    private ?Utilisateurs $reporter = null;

    #[ORM\Column(type: "string", length: 100)]
    private ?string $reason = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "datetime_immutable")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: "string", length: 20)]
    private string $status = 'PENDING'; // PENDING, RESOLVED, REJECTED

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getRessource(): ?Ressources { return $this->ressource; }
    public function setRessource(?Ressources $r): self { $this->ressource = $r; return $this; }

    public function getReporter(): ?Utilisateurs { return $this->reporter; }
    public function setReporter(?Utilisateurs $u): self { $this->reporter = $u; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(string $r): self { $this->reason = $r; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
}

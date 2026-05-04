<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateurs;
use App\Entity\Publications;
use App\Repository\Demande_emploisRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Demande_emploisRepository::class)]
#[ORM\Table(name: 'demande_emplois')]
class Demande_emplois
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "demande_id", type: "integer")]
    private ?int $demande_id = null;

    #[ORM\ManyToOne(targetEntity: Publications::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'publication_id', onDelete: 'CASCADE')]
    private ?Publications $publication_id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private ?Utilisateurs $candidat_id = null;

    #[ORM\Column(name: 'cv_url', type: "string", length: 255, nullable: true)]
    private ?string $cv_url = null;

    #[ORM\Column(name: 'lettre_motivation', type: "text", nullable: true)]
    private ?string $lettre_motivation = null;

    #[ORM\Column(name: 'motivation_ciblee', type: "text", nullable: true)]
    private ?string $motivation_ciblee = null;

    #[ORM\Column(name: 'statut_demande', type: "string", length: 50)]
    private string $statut_demande = 'En attente';

    #[ORM\Column(name: 'date_demande', type: "date")]
    private ?\DateTimeInterface $date_demande = null;
    #[ORM\Column(type: 'float', nullable: true)]
private ?float $matchingScore = null;

    public function __construct()
    {
        $this->date_demande = new \DateTime();
        $this->statut_demande = 'En attente';
    }

    // ── original getters/setters ─────────────────────────────────────────────

    public function getDemande_id(): ?int { return $this->demande_id; }
    public function setDemande_id($value): self { $this->demande_id = $value; return $this; }

    public function getPublication_id(): ?Publications { return $this->publication_id; }
    public function setPublication_id(?Publications $value): self { $this->publication_id = $value; return $this; }

    public function getCandidat_id(): ?Utilisateurs { return $this->candidat_id; }
    public function setCandidat_id($value): self { $this->candidat_id = $value; return $this; }

    public function getCv_url(): ?string { return $this->cv_url; }
    public function setCv_url(?string $value): self { $this->cv_url = $value; return $this; }

    public function getLettre_motivation(): ?string { return $this->lettre_motivation; }
    public function setLettre_motivation(?string $value): self { $this->lettre_motivation = $value; return $this; }

    public function getMotivation_ciblee(): ?string { return $this->motivation_ciblee; }
    public function setMotivation_ciblee(?string $value): self { $this->motivation_ciblee = $value; return $this; }

    public function getStatut_demande(): string { return $this->statut_demande; }
    public function setStatut_demande(string $value): self { $this->statut_demande = $value; return $this; }

    public function getDate_demande(): ?\DateTimeInterface { return $this->date_demande; }
    public function setDate_demande($value): self { $this->date_demande = $value; return $this; }

    // ── camelCase aliases (used by ported controllers & forms) ───────────────

    public function getDemandeId(): ?int { return $this->demande_id; }

    public function getPublication(): ?Publications { return $this->publication_id; }
    public function setPublication(?Publications $publication): self { $this->publication_id = $publication; return $this; }

    public function getCandidat(): ?Utilisateurs { return $this->candidat_id; }
    public function setCandidat(?Utilisateurs $candidat): self { $this->candidat_id = $candidat; return $this; }

    public function getCvUrl(): ?string { return $this->cv_url; }
    public function setCvUrl(?string $v): self { $this->cv_url = $v; return $this; }

    public function getLettreMotivation(): ?string { return $this->lettre_motivation; }
    public function setLettreMotivation(?string $v): self { $this->lettre_motivation = $v; return $this; }

    public function getMotivationCiblee(): ?string { return $this->motivation_ciblee; }
    public function setMotivationCiblee(?string $v): self { $this->motivation_ciblee = $v; return $this; }

    public function getStatutDemande(): string { return $this->statut_demande; }
    public function setStatutDemande(string $v): self { $this->statut_demande = $v; return $this; }

    public function getDateDemande(): ?\DateTimeInterface { return $this->date_demande; }
    public function setDateDemande(\DateTimeInterface $v): self { $this->date_demande = $v; return $this; }
    public function getMatchingScore(): ?float
{
    return $this->matchingScore;
}

public function setMatchingScore(?float $matchingScore): self
{
    $this->matchingScore = $matchingScore;
    return $this;
}
}

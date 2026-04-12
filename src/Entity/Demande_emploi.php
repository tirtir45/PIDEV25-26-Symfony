<?php

namespace App\Entity;

use App\Repository\DemandeEmploiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Utilisateur;

#[ORM\Entity(repositoryClass: DemandeEmploiRepository::class)]
class Demande_emploi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $demande_id = null;

    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'publication_id', nullable: false)]
    #[Assert\NotNull(message: "L'offre d'emploi est obligatoire.")]
    private ?Publication $publication_id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id_utilisateur', nullable: false)]
    #[Assert\NotNull(message: "Le candidat est obligatoire.")]
    private ?Utilisateur $candidat_id = null;

    /**
     * Nom du fichier CV (ex: 'cv-jean-dupont.pdf').
     * Stocké tel quel en base (pas de déplacement de fichier par le back-end pour l'instant).
     */
    #[ORM\Column(name: 'cv_url', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: "Le nom du fichier CV ne peut pas dépasser 255 caractères.")]
    #[Assert\Regex(
        pattern: '/\.pdf$/i',
        message: "Le nom du fichier CV doit se terminer par .pdf"
    )]
    private ?string $cv_url = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La lettre de motivation est obligatoire.")]
    #[Assert\Length(min: 50, minMessage: "Votre lettre de motivation doit faire au moins 50 caractères.")]
    private ?string $lettre_motivation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motivation_ciblee = null;

    #[ORM\Column(name: "statut_demande", type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: "Le statut est obligatoire.")]
    private ?string $statut_demande = null;

    #[ORM\Column(name: "date_demande", type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date_demande = null;

    public function __construct()
    {
        $this->date_demande = new \DateTime();
        $this->statut_demande = 'En attente';
    }

    // --- GETTERS / SETTERS ---

    public function getDemande_id(): ?int
    {
        return $this->demande_id;
    }

    public function getPublication_id(): ?Publication
    {
        return $this->publication_id;
    }

    public function setPublication_id(?Publication $publication_id): self
    {
        $this->publication_id = $publication_id;
        return $this;
    }

    public function getCandidat_id(): ?Utilisateur
    {
        return $this->candidat_id;
    }

    public function setCandidat_id(?Utilisateur $candidat_id): self
    {
        $this->candidat_id = $candidat_id;
        return $this;
    }

    public function getCv_url(): ?string
    {
        return $this->cv_url;
    }

    public function setCv_url(?string $cv_url): self
    {
        $this->cv_url = $cv_url;
        return $this;
    }

    public function getLettre_motivation(): ?string
    {
        return $this->lettre_motivation;
    }

    public function setLettre_motivation(string $lettre_motivation): self
    {
        $this->lettre_motivation = $lettre_motivation;
        return $this;
    }

    public function getMotivation_ciblee(): ?string
    {
        return $this->motivation_ciblee;
    }

    public function setMotivation_ciblee(?string $motivation_ciblee): self
    {
        $this->motivation_ciblee = $motivation_ciblee;
        return $this;
    }

    public function getStatut_demande(): ?string
    {
        return $this->statut_demande;
    }

    public function setStatut_demande(string $statut_demande): self
    {
        $this->statut_demande = $statut_demande;
        return $this;
    }

    public function getDate_demande(): ?\DateTimeInterface
    {
        return $this->date_demande;
    }

    public function setDate_demande(\DateTimeInterface $date_demande): self
    {
        $this->date_demande = $date_demande;
        return $this;
    }
}
<?php

namespace App\Entity;

use App\Repository\Demande_emploiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Utilisateur;

#[ORM\Entity(repositoryClass: Demande_emploiRepository::class)]
class Demande_emploi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $demande_id = null;

    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'publication_id', nullable: false)]
    #[Assert\NotNull(message: "L'offre d'emploi est obligatoire.")]
    private ?Publication $publication = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id_utilisateur', nullable: false)]
    #[Assert\NotNull(message: "Le candidat est obligatoire.")]
    private ?Utilisateur $candidat = null;

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
    private ?string $cvUrl = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La lettre de motivation est obligatoire.")]
    #[Assert\Length(min: 50, minMessage: "Votre lettre de motivation doit faire au moins 50 caractères.")]
    private ?string $lettreMotivation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motivationCiblee = null;

    #[ORM\Column(name: "statut_demande", type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: "Le statut est obligatoire.")]
    private ?string $statutDemande = null;

    #[ORM\Column(name: "date_demande", type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateDemande = null;

    public function __construct()
    {
        $this->dateDemande = new \DateTime();
        $this->statutDemande = 'En attente';
    }

    // --- GETTERS / SETTERS ---

    public function getDemande_id(): ?int
    {
        return $this->demande_id;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(?Publication $publication): self
    {
        $this->publication = $publication;
        return $this;
    }

    public function getCandidat(): ?Utilisateur
    {
        return $this->candidat;
    }

    public function setCandidat(?Utilisateur $candidat): self
    {
        $this->candidat = $candidat;
        return $this;
    }

    public function getCvUrl(): ?string
    {
        return $this->cvUrl;
    }

    public function setCvUrl(?string $cvUrl): self
    {
        $this->cvUrl = $cvUrl;
        return $this;
    }

    public function getLettreMotivation(): ?string
    {
        return $this->lettreMotivation;
    }

    public function setLettreMotivation(string $lettreMotivation): self
    {
        $this->lettreMotivation = $lettreMotivation;
        return $this;
    }

    public function getMotivationCiblee(): ?string
    {
        return $this->motivationCiblee;
    }

    public function setMotivationCiblee(?string $motivationCiblee): self
    {
        $this->motivationCiblee = $motivationCiblee;
        return $this;
    }

    public function getStatutDemande(): ?string
    {
        return $this->statutDemande;
    }

    public function setStatutDemande(string $statutDemande): self
    {
        $this->statutDemande = $statutDemande;
        return $this;
    }

    public function getDateDemande(): ?\DateTimeInterface
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeInterface $dateDemande): self
    {
        $this->dateDemande = $dateDemande;
        return $this;
    }
}
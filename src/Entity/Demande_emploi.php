<?php
// src/Entity/Demande_emploi.php

namespace App\Entity;

// Imports nécessaires pour le fonctionnement
use App\Repository\Demande_emploiRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Imports ajoutés pour VichUploaderBundle
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * Cette annotation active le bundle VichUploader sur cette entité.
 */
#[Vich\Uploadable]
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


    // --- SECTION MODIFIÉE POUR L'UPLOAD DE CV ---

    /**
     * @var File|null
     * Cette propriété n'est pas mappée en base de données.
     * Elle sert uniquement à recevoir le fichier uploadé depuis le formulaire.
     * L'annotation @Assert\File valide le fichier uploadé (taille, type).
     */
    #[Vich\UploadableField(mapping: 'cv_files', fileNameProperty: 'cvUrl')]
    #[Assert\File(
        maxSize: '2M',
        mimeTypes: ['application/pdf', 'application/x-pdf'],
        mimeTypesMessage: 'Veuillez uploader un fichier PDF valide (taille max 2Mo).'
    )]
    private ?File $cvFile = null;

    /**
     * Votre propriété existante.
     * Le bundle va maintenant l'utiliser pour stocker le nom unique du fichier.
     */
    #[ORM\Column(name: 'cv_url', type: Types::STRING, length: 255, nullable: true)]
    private ?string $cvUrl = null;

    /**
     * @var \DateTimeImmutable|null
     * Propriété technique requise par VichUploaderBundle pour détecter les changements de fichier.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // --- FIN DE LA SECTION MODIFIÉE ---


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


    // --- GETTERS & SETTERS ---

    // Getters/Setters que vous aviez déjà (inchangés)
    public function getDemande_id(): ?int { return $this->demande_id; }
    public function getPublication(): ?Publication { return $this->publication; }
    public function setPublication(?Publication $publication): self { $this->publication = $publication; return $this; }
    public function getCandidat(): ?Utilisateur { return $this->candidat; }
    public function setCandidat(?Utilisateur $candidat): self { $this->candidat = $candidat; return $this; }
    public function getLettreMotivation(): ?string { return $this->lettreMotivation; }
    public function setLettreMotivation(string $lettreMotivation): self { $this->lettreMotivation = $lettreMotivation; return $this; }
    public function getMotivationCiblee(): ?string { return $this->motivationCiblee; }
    public function setMotivationCiblee(?string $motivationCiblee): self { $this->motivationCiblee = $motivationCiblee; return $this; }
    public function getStatutDemande(): ?string { return $this->statutDemande; }
    public function setStatutDemande(string $statutDemande): self { $this->statutDemande = $statutDemande; return $this; }
    public function getDateDemande(): ?\DateTimeInterface { return $this->dateDemande; }
    public function setDateDemande(\DateTimeInterface $dateDemande): self { $this->dateDemande = $dateDemande; return $this; }
    
    // Getters/Setters pour votre propriété 'cvUrl' (inchangés)
    public function getCvUrl(): ?string { return $this->cvUrl; }
    public function setCvUrl(?string $cvUrl): self { $this->cvUrl = $cvUrl; return $this; }


    // NOUVEAUX Getters/Setters ajoutés pour le bundle VichUploader

    /**
     * @param File|\Symfony\Component\HttpFoundation\File\UploadedFile|null $cvFile
     */
    public function setCvFile(?File $cvFile = null): void
    {
        $this->cvFile = $cvFile;

        if (null !== $cvFile) {
            // Un changement doit être détecté par Doctrine pour que les événements du bundle se déclenchent.
            // La mise à jour de ce champ force la mise à jour de l'entité.
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getCvFile(): ?File
    {
        return $this->cvFile;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
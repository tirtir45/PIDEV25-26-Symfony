<?php

namespace App\Entity;

use App\Repository\FichierProjetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FichierProjetRepository::class)]
#[ORM\Table(name: 'fichiers_projet')]
class FichierProjet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Projets::class)]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', nullable: false, onDelete: 'CASCADE')]
    private ?Projets $projet = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class)]
    #[ORM\JoinColumn(name: 'id_uploaded_by', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateurs $uploaded_by = null;

    #[ORM\Column(length: 255)]
    private ?string $nom_original = null;

    #[ORM\Column(length: 255)]
    private ?string $nom_fichier = null;

    #[ORM\Column(length: 100)]
    private ?string $mime_type = null;

    #[ORM\Column(type: 'integer')]
    private ?int $taille = null;

    #[ORM\Column(length: 10)]
    private ?string $extension = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $uploaded_at = null;

    public function __construct()
    {
        $this->uploaded_at = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getProjet(): ?Projets { return $this->projet; }
    public function setProjet(?Projets $projet): static { $this->projet = $projet; return $this; }
    public function getUploadedBy(): ?Utilisateurs { return $this->uploaded_by; }
    public function setUploadedBy(?Utilisateurs $uploaded_by): static { $this->uploaded_by = $uploaded_by; return $this; }
    public function getNomOriginal(): ?string { return $this->nom_original; }
    public function setNomOriginal(string $nom_original): static { $this->nom_original = $nom_original; return $this; }
    public function getNomFichier(): ?string { return $this->nom_fichier; }
    public function setNomFichier(string $nom_fichier): static { $this->nom_fichier = $nom_fichier; return $this; }
    public function getMimeType(): ?string { return $this->mime_type; }
    public function setMimeType(string $mime_type): static { $this->mime_type = $mime_type; return $this; }
    public function getTaille(): ?int { return $this->taille; }
    public function setTaille(int $taille): static { $this->taille = $taille; return $this; }
    public function getExtension(): ?string { return $this->extension; }
    public function setExtension(string $extension): static { $this->extension = $extension; return $this; }
    public function getUploadedAt(): ?\DateTimeInterface { return $this->uploaded_at; }

    public function getTailleFormatee(): string
    {
        $bytes = $this->taille ?? 0;
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}

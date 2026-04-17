<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\UtilisateurRepository;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    // id_role mapping (same as Java project)
    public const ROLE_ADMIN_ID       = 1;
    public const ROLE_ENTREPRENEUR_ID = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur', type: 'integer')]
    private ?int $idUtilisateur = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: false)]
    private ?string $email = null;

    #[ORM\Column(name: 'mot_de_passe', type: 'string', length: 255, nullable: false)]
    private ?string $motDePasse = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $competences = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: 'id_role', type: 'integer', nullable: false, options: ['default' => 2])]
    private int $idRole = self::ROLE_ENTREPRENEUR_ID;

    #[ORM\Column(name: 'date_inscription', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $actif = true;

    // Extra columns present in the real DB (mapped to avoid schema drift)
    #[ORM\Column(name: 'badge_verifie', type: 'boolean', nullable: true)]
    private ?bool $badgeVerifie = false;

    #[ORM\Column(name: 'badge_verification', type: 'boolean', nullable: true)]
    private ?bool $badgeVerification = false;

    #[ORM\Column(name: 'profil_complet', type: 'boolean', nullable: true)]
    private ?bool $profilComplet = false;

    #[ORM\Column(name: 'adresse', type: 'string', length: 500, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(name: 'latitude', type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'decimal', precision: 11, scale: 8, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(name: 'avatar_url', type: 'string', length: 500, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'utilisateur')]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations    = new ArrayCollection();
        $this->dateInscription = new \DateTime();
        $this->actif           = true;
        $this->idRole          = self::ROLE_ENTREPRENEUR_ID;
    }

    // ── UserInterface ────────────────────────────────────────────
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Maps id_role (Java int) → Symfony role strings.
     * id_role 1 = Admin, 2 = Entrepreneur
     */
    public function getRoles(): array
    {
        if ($this->idRole === self::ROLE_ADMIN_ID) {
            return ['ROLE_ADMIN', 'ROLE_USER'];
        }
        return ['ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    public function eraseCredentials(): void
    {
        // nothing to erase — we never store the plain password
    }

    // ── Getters / Setters ────────────────────────────────────────
    public function getIdUtilisateur(): ?int { return $this->idUtilisateur; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $nom): self { $this->nom = $nom; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getMotDePasse(): ?string { return $this->motDePasse; }
    public function setMotDePasse(string $motDePasse): self { $this->motDePasse = $motDePasse; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): self { $this->photo = $photo; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): self { $this->bio = $bio; return $this; }

    public function getCompetences(): ?string { return $this->competences; }
    public function setCompetences(?string $competences): self { $this->competences = $competences; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }

    public function getIdRole(): int { return $this->idRole; }
    public function setIdRole(int $idRole): self { $this->idRole = $idRole; return $this; }

    public function isAdmin(): bool { return $this->idRole === self::ROLE_ADMIN_ID; }

    public function getDateInscription(): ?\DateTimeInterface { return $this->dateInscription; }
    public function setDateInscription(?\DateTimeInterface $d): self { $this->dateInscription = $d; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): self { $this->actif = $actif; return $this; }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        if (!$this->reservations instanceof Collection) {
            $this->reservations = new ArrayCollection();
        }
        return $this->reservations;
    }

    public function __toString(): string
    {
        return ($this->nom ?? '(sans nom)') . ' — ' . ($this->email ?? '');
    }
}

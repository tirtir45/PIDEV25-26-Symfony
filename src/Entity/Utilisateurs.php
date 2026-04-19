<?php

namespace App\Entity;

use App\Repository\UtilisateursRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UtilisateursRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class Utilisateurs
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom', length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/', message: 'Le nom ne peut contenir que des lettres et des espaces.')]
    private ?string $nom = null;

    #[ORM\Column(name: 'email', length: 150, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.')]
    #[Assert\Length(max: 150, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $email = null;

    #[ORM\Column(name: 'mot_de_passe', length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(name: 'telephone', length: 30, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9\+\-\s\(\)]{8,20}$/', message: 'Le numéro de téléphone n\'est pas valide (8 à 20 chiffres).')]
    private ?string $telephone = null;

    #[ORM\ManyToOne(targetEntity: Roles::class)]
    #[ORM\JoinColumn(name: 'id_role', referencedColumnName: 'id_role', nullable: true)]
    private ?Roles $role = null;

    #[ORM\Column(name: 'date_inscription', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(name: 'actif', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'bio', type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La bio ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $bio = null;

    #[ORM\Column(name: 'competences', type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les compétences ne peuvent pas dépasser {{ limit }} caractères.')]
    private ?string $competences = null;

    #[ORM\Column(name: 'photo', length: 255, nullable: true)]
    private ?string $photo = null;
 
    #[ORM\Column(name: 'badge_verifie', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $badgeVerifie = false;
 
    #[ORM\Column(name: 'date_verification', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateVerification = null;
 
    #[ORM\Column(name: 'profil_complet', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $profilComplet = false;
 
    #[ORM\Column(name: 'adresse', type: Types::STRING, length: 255, nullable: true)]
    private ?string $adresse = null;
 
    #[ORM\Column(name: 'latitude', type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;
 
    #[ORM\Column(name: 'longitude', type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;
 
    #[ORM\Column(name: 'badge_verification', type: Types::STRING, length: 100, nullable: true)]
    private ?string $badgeVerification = null;
 
    #[ORM\Column(name: 'avatar_url', type: Types::STRING, length: 255, nullable: true)]
    private ?string $avatarUrl = null;


    // ===== INVERSE RELATIONS =====

    #[ORM\OneToMany(mappedBy: 'id_entrepreneur', targetEntity: Projets::class)]
    private Collection $projetss;

    #[ORM\OneToMany(mappedBy: 'id_responsable', targetEntity: Taches::class)]
    private Collection $tachess;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Reclamations::class)]
    private Collection $reclamations;

    #[ORM\OneToMany(mappedBy: 'id_fournisseur', targetEntity: Ressources::class)]
    private Collection $ressourcess;

    #[ORM\OneToMany(mappedBy: 'id_utilisateur', targetEntity: Reservations::class)]
    private Collection $reservationss;

    #[ORM\OneToMany(mappedBy: 'id_expediteur', targetEntity: Messages::class)]
    private Collection $messagess;

    #[ORM\OneToMany(mappedBy: 'id_entrepreneur', targetEntity: Commandes::class)]
    private Collection $commandess;

    #[ORM\OneToMany(mappedBy: 'id_utilisateur', targetEntity: Historique_connexions::class)]
    private Collection $historique_connexionss;

    #[ORM\OneToMany(mappedBy: 'id_entrepreneur', targetEntity: Demandes_ressources::class)]
    private Collection $demandes_ressourcess;

    #[ORM\OneToMany(mappedBy: 'candidat_id', targetEntity: Demande_emplois::class)]
    private Collection $demande_emplois;

    #[ORM\OneToMany(mappedBy: 'id_admin', targetEntity: Evaluations_projet::class)]
    private Collection $evaluations_projets;

    #[ORM\OneToMany(mappedBy: 'id_utilisateur', targetEntity: Password_reset_tokens::class)]
    private Collection $password_reset_tokenss;

    #[ORM\OneToMany(mappedBy: 'id_utilisateur', targetEntity: Membres_equipe::class)]
    private Collection $membres_equipes;

    #[ORM\OneToMany(mappedBy: 'id_utilisateur', targetEntity: Conversation_participants::class)]
    private Collection $conversation_participantss;

    #[ORM\OneToMany(mappedBy: 'id_auteur', targetEntity: Reclamation_commentaires::class)]
    private Collection $reclamation_commentairess;

    #[ORM\OneToMany(mappedBy: 'id_utilisateur', targetEntity: Demandes_verification::class)]
    private Collection $demandes_verifications;

    #[ORM\OneToMany(mappedBy: 'id_admin_traitant', targetEntity: Demandes_verification::class)]
    private Collection $demandes_verifications_traitees;

    public function __construct()
    {
        $this->dateInscription = new \DateTime();
        $this->projetss = new ArrayCollection();
        $this->tachess = new ArrayCollection();
        $this->reclamations = new ArrayCollection();
        $this->ressourcess = new ArrayCollection();
        $this->reservationss = new ArrayCollection();
        $this->messagess = new ArrayCollection();
        $this->commandess = new ArrayCollection();
        $this->historique_connexionss = new ArrayCollection();
        $this->demandes_ressourcess = new ArrayCollection();
        $this->demande_emplois = new ArrayCollection();
        $this->evaluations_projets = new ArrayCollection();
        $this->password_reset_tokenss = new ArrayCollection();
        $this->membres_equipes = new ArrayCollection();
        $this->conversation_participantss = new ArrayCollection();
        $this->reclamation_commentairess = new ArrayCollection();
        $this->demandes_verifications = new ArrayCollection();
        $this->demandes_verifications_traitees = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $nom): static { $this->nom = $nom; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getMotDePasse(): ?string { return $this->motDePasse; }
    public function setMotDePasse(string $motDePasse): static { $this->motDePasse = $motDePasse; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getRole(): ?Roles { return $this->role; }
    public function setRole(?Roles $role): static { $this->role = $role; return $this; }

    public function getDateInscription(): ?\DateTimeInterface { return $this->dateInscription; }
    public function setDateInscription(?\DateTimeInterface $d): static { $this->dateInscription = $d; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): static { $this->bio = $bio; return $this; }

    public function getCompetences(): ?string { return $this->competences; }
    public function setCompetences(?string $c): static { $this->competences = $c; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $p): static { $this->photo = $p; return $this; }

    public function isBadgeVerifie(): bool { return $this->badgeVerifie; }
    public function setBadgeVerifie(bool $b): static { $this->badgeVerifie = $b; return $this; }

    public function getDateVerification(): ?\DateTimeInterface { return $this->dateVerification; }
    public function setDateVerification(?\DateTimeInterface $d): static { $this->dateVerification = $d; return $this; }

    // ===== GETTERS FOR COLLECTIONS =====

    public function getProjetss(): Collection { return $this->projetss; }
    public function getTachess(): Collection { return $this->tachess; }
    public function getReclamations(): Collection { return $this->reclamations; }
    public function getRessourcess(): Collection { return $this->ressourcess; }
    public function getReservationss(): Collection { return $this->reservationss; }
    public function getMessagess(): Collection { return $this->messagess; }
    public function getCommandess(): Collection { return $this->commandess; }
    public function getHistoriqueConnexionss(): Collection { return $this->historique_connexionss; }
    public function getDemandesRessourcess(): Collection { return $this->demandes_ressourcess; }
    public function getDemandeEmplois(): Collection { return $this->demande_emplois; }
    public function getEvaluationsProjets(): Collection { return $this->evaluations_projets; }
    public function getPasswordResetTokenss(): Collection { return $this->password_reset_tokenss; }
    public function getMembresEquipes(): Collection { return $this->membres_equipes; }
    public function getConversationParticipantss(): Collection { return $this->conversation_participantss; }
    public function getReclamationCommentairess(): Collection { return $this->reclamation_commentairess; }
    public function getDemandesVerifications(): Collection { return $this->demandes_verifications; }
    public function getDemandesVerificationsTraitees(): Collection { return $this->demandes_verifications_traitees; }
}
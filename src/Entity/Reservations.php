<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ReservationsRepository;
use App\Entity\Utilisateurs;
use App\Entity\Evenements;

#[ORM\Entity(repositoryClass: ReservationsRepository::class)]
#[ORM\Table(name: 'reservations')]
class Reservations
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_reservation", type: "integer")]
    private ?int $id_reservation = null;

    #[ORM\ManyToOne(targetEntity: Evenements::class, inversedBy: "reservationss")]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id_evenement', onDelete: 'CASCADE')]
    private ?Evenements $id_evenement = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "reservationss")]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private ?Utilisateurs $id_utilisateur = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $date_reservation = null;

    #[ORM\Column(type: "string", length: 64, unique: true, nullable: true)]
    private ?string $tokenVerification = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $stripePaymentId = null;

    #[ORM\Column(type: "string", length: 20, options: ['default' => 'pending'])]
    private string $statutPaiement = 'pending';

    public function __construct()
    {
        $this->date_reservation = new \DateTime();
        $this->tokenVerification = bin2hex(random_bytes(16));
        $this->statutPaiement = 'pending';
    }

    // ── original getters/setters ─────────────────────────────────────────────

    public function getId_reservation(): ?int { return $this->id_reservation; }
    public function setId_reservation($value): self { $this->id_reservation = $value; return $this; }

    public function getId_evenement(): ?Evenements { return $this->id_evenement; }
    public function setId_evenement($value): self { $this->id_evenement = $value; return $this; }

    public function getId_utilisateur(): ?Utilisateurs { return $this->id_utilisateur; }
    public function setId_utilisateur($value): self { $this->id_utilisateur = $value; return $this; }

    public function getDate_reservation(): ?\DateTimeInterface { return $this->date_reservation; }
    public function setDate_reservation($value): self { $this->date_reservation = $value; return $this; }

    // ── camelCase aliases (used by ported controllers & services) ────────────

    public function getIdReservation(): ?int { return $this->id_reservation; }

    public function getEvenement(): ?Evenements { return $this->id_evenement; }
    public function setEvenement(?Evenements $evenement): self { $this->id_evenement = $evenement; return $this; }

    public function getUtilisateur(): ?Utilisateurs { return $this->id_utilisateur; }
    public function setUtilisateur(?Utilisateurs $utilisateur): self { $this->id_utilisateur = $utilisateur; return $this; }

    public function getDateReservation(): ?\DateTimeInterface { return $this->date_reservation; }
    public function setDateReservation(\DateTimeInterface $v): self { $this->date_reservation = $v; return $this; }

    // ── payment fields ───────────────────────────────────────────────────────

    public function getTokenVerification(): ?string { return $this->tokenVerification; }
    public function setTokenVerification(?string $token): self { $this->tokenVerification = $token; return $this; }

    public function getStripePaymentId(): ?string { return $this->stripePaymentId; }
    public function setStripePaymentId(?string $id): self { $this->stripePaymentId = $id; return $this; }

    public function getStatutPaiement(): string { return $this->statutPaiement; }
    public function setStatutPaiement(string $statut): self { $this->statutPaiement = $statut; return $this; }
}

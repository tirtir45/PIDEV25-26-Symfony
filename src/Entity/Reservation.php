<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ReservationRepository;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idReservation = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id_evenement', nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $dateReservation = null;

    /** Unique token for QR code verification */
    #[ORM\Column(type: 'string', length: 64, unique: true, nullable: true)]
    private ?string $tokenVerification = null;

    /** Stripe Checkout Session ID */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripePaymentId = null;

    /** paid | free | pending */
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'pending'])]
    private string $statutPaiement = 'pending';

    public function __construct()
    {
        $this->dateReservation = new \DateTime();
        $this->tokenVerification = bin2hex(random_bytes(16)); // unique 32-char hex token
    }

    public function getIdReservation(): ?int { return $this->idReservation; }

    public function getEvenement(): ?Evenement { return $this->evenement; }
    public function setEvenement(?Evenement $evenement): self { $this->evenement = $evenement; return $this; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    public function getDateReservation(): ?\DateTimeInterface { return $this->dateReservation; }
    public function setDateReservation(\DateTimeInterface $dateReservation): self { $this->dateReservation = $dateReservation; return $this; }

    public function getTokenVerification(): ?string { return $this->tokenVerification; }
    public function setTokenVerification(?string $token): self { $this->tokenVerification = $token; return $this; }

    public function getStripePaymentId(): ?string { return $this->stripePaymentId; }
    public function setStripePaymentId(?string $id): self { $this->stripePaymentId = $id; return $this; }

    public function getStatutPaiement(): string { return $this->statutPaiement; }
    public function setStatutPaiement(string $statut): self { $this->statutPaiement = $statut; return $this; }
}

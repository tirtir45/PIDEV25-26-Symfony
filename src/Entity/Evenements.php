<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Reservations;
use App\Repository\EvenementsRepository;

#[ORM\Entity(repositoryClass: EvenementsRepository::class)]
#[ORM\Table(name: 'evenements')]
class Evenements
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_evenement", type: "integer")]
    private ?int $id_evenement = null;

    public function __construct()
    {
        $this->reservationss = new ArrayCollection();
    }

    #[ORM\Column(type: "string", length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $date_evenement = null;

    #[ORM\Column(type: "string", length: 150)]
    private ?string $lieu = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $capacite = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $prix = 0.0;

    public function getId_evenement()
    {
        return $this->id_evenement;
    }

    public function setId_evenement($value)
    {
        $this->id_evenement = $value;
    }

    public function getTitre()
    {
        return $this->titre;
    }

    public function setTitre($value)
    {
        $this->titre = $value;
    }

    public function getDate_evenement()
    {
        return $this->date_evenement;
    }

    public function setDate_evenement($value)
    {
        $this->date_evenement = $value;
    }

    public function getLieu()
    {
        return $this->lieu;
    }

    public function setLieu($value)
    {
        $this->lieu = $value;
    }

    public function getCapacite()
    {
        return $this->capacite;
    }

    public function setCapacite($value)
    {
        $this->capacite = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getPrix()
    {
        return $this->prix;
    }

    public function setPrix($value)
    {
        $this->prix = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_evenement", targetEntity: Reservations::class, cascade: ['remove'])]
    private Collection $reservationss;

    public function getReservationss(): Collection
    {
        if (!$this->reservationss instanceof Collection) {
            $this->reservationss = new ArrayCollection();
        }
        return $this->reservationss;
    }

    public function addReservations(Reservations $reservations): self
    {
        if (!$this->getReservationss()->contains($reservations)) {
            $this->getReservationss()->add($reservations);
            $reservations->setId_evenement($this);
        }
        return $this;
    }

    public function removeReservations(Reservations $reservations): self
    {
        if ($this->getReservationss()->removeElement($reservations)) {
            if ($reservations->getId_evenement() === $this) {
                $reservations->setId_evenement(null);
            }
        }
        return $this;
    }

    // ── camelCase aliases (used by ported controllers & services) ────────────

    public function getIdEvenement(): ?int { return $this->id_evenement; }

    public function getDateEvenement(): ?\DateTimeInterface { return $this->date_evenement; }
    public function setDateEvenement(?\DateTimeInterface $v): self { $this->date_evenement = $v; return $this; }

    // ── helper methods ───────────────────────────────────────────────────────

    public function isComplet(): bool
    {
        return $this->capacite !== null && $this->capacite <= 0;
    }

    public function isFutur(): bool
    {
        if ($this->date_evenement === null) return false;
        return $this->date_evenement > new \DateTime('today');
    }

    public function getNbReservations(): int
    {
        return $this->getReservationss()->count();
    }

    public function __toString(): string
    {
        return ($this->titre ?? '') . ' [' . ($this->capacite ?? 0) . ' places]';
    }
}

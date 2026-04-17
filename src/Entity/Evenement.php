<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\EvenementRepository;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenements')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idEvenement = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $titre = null;

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $dateEvenement = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $lieu = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $capacite = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'float', nullable: false, options: ['default' => 0.0])]
    private ?float $prix = 0.0;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'evenement', cascade: ['remove'])]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->prix = 0.0;
    }

    public function getIdEvenement(): ?int
    {
        return $this->idEvenement;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDateEvenement(): ?\DateTimeInterface
    {
        return $this->dateEvenement;
    }

    public function setDateEvenement(?\DateTimeInterface $dateEvenement): self
    {
        $this->dateEvenement = $dateEvenement;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): self
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

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

    public function addReservation(Reservation $reservation): self
    {
        if (!$this->getReservations()->contains($reservation)) {
            $this->getReservations()->add($reservation);
            $reservation->setEvenement($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        $this->getReservations()->removeElement($reservation);
        return $this;
    }

    public function getNbReservations(): int
    {
        return $this->getReservations()->count();
    }

    public function isComplet(): bool
    {
        return $this->capacite !== null && $this->capacite <= 0;
    }

    public function isFutur(): bool
    {
        if ($this->dateEvenement === null) {
            return false;
        }
        return $this->dateEvenement > new \DateTime('today');
    }

    public function __toString(): string
    {
        return $this->titre . ' [' . $this->capacite . ' places]';
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Reservations;

#[ORM\Entity]
class Evenements
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_evenement", type: "integer")]
    private ?int $id_evenement = null;

    #[ORM\Column(type: "string", length: 150)]
    private string $titre;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_evenement;

    #[ORM\Column(type: "string", length: 150)]
    private string $lieu;

    #[ORM\Column(type: "integer")]
    private int $capacite;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "float")]
    private float $prix;

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

    #[ORM\OneToMany(mappedBy: "id_evenement", targetEntity: Reservations::class)]
    private Collection $reservationss;

        public function getReservationss(): Collection
        {
            return $this->reservationss;
        }
    
        public function addReservations(Reservations $reservations): self
        {
            if (!$this->reservationss->contains($reservations)) {
                $this->reservationss[] = $reservations;
                $reservations->setId_evenement($this);
            }
    
            return $this;
        }
    
        public function removeReservations(Reservations $reservations): self
        {
            if ($this->reservationss->removeElement($reservations)) {
                // set the owning side to null (unless already changed)
                if ($reservations->getId_evenement() === $this) {
                    $reservations->setId_evenement(null);
                }
            }
    
            return $this;
        }
}

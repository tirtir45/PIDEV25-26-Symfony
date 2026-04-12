<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateur;

#[ORM\Entity]
class Reservations
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_reservation;

        #[ORM\ManyToOne(targetEntity: Evenements::class, inversedBy: "reservationss")]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id_evenement', onDelete: 'CASCADE')]
    private Evenements $id_evenement;

        #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "reservationss")]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateur $id_utilisateur;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_reservation;

    public function getId_reservation()
    {
        return $this->id_reservation;
    }

    public function setId_reservation($value)
    {
        $this->id_reservation = $value;
    }

    public function getId_evenement()
    {
        return $this->id_evenement;
    }

    public function setId_evenement($value)
    {
        $this->id_evenement = $value;
    }

    public function getId_utilisateur()
    {
        return $this->id_utilisateur;
    }

    public function setId_utilisateur($value)
    {
        $this->id_utilisateur = $value;
    }

    public function getDate_reservation()
    {
        return $this->date_reservation;
    }

    public function setDate_reservation($value)
    {
        $this->date_reservation = $value;
    }
}

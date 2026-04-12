<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;

#[ORM\Entity]
class Demandes_ressources
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_demande;

        #[ORM\ManyToOne(targetEntity: Ressources::class, inversedBy: "demandes_ressourcess")]
    #[ORM\JoinColumn(name: 'id_ressource', referencedColumnName: 'id_ressource', onDelete: 'CASCADE')]
    private Ressources $id_ressource;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "demandes_ressourcess")]
    #[ORM\JoinColumn(name: 'id_entrepreneur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_entrepreneur;

    #[ORM\Column(type: "string")]
    private string $statut;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_demande;

    public function getId_demande()
    {
        return $this->id_demande;
    }

    public function setId_demande($value)
    {
        $this->id_demande = $value;
    }

    public function getId_ressource()
    {
        return $this->id_ressource;
    }

    public function setId_ressource($value)
    {
        $this->id_ressource = $value;
    }

    public function getId_entrepreneur()
    {
        return $this->id_entrepreneur;
    }

    public function setId_entrepreneur($value)
    {
        $this->id_entrepreneur = $value;
    }

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($value)
    {
        $this->statut = $value;
    }

    public function getDate_demande()
    {
        return $this->date_demande;
    }

    public function setDate_demande($value)
    {
        $this->date_demande = $value;
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Ressources;

#[ORM\Entity]
class Lignes_commande
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_ligne;

        #[ORM\ManyToOne(targetEntity: Commandes::class, inversedBy: "lignes_commandes")]
    #[ORM\JoinColumn(name: 'id_commande', referencedColumnName: 'id_commande', onDelete: 'CASCADE')]
    private Commandes $id_commande;

        #[ORM\ManyToOne(targetEntity: Ressources::class, inversedBy: "lignes_commandes")]
    #[ORM\JoinColumn(name: 'id_ressource', referencedColumnName: 'id_ressource', onDelete: 'CASCADE')]
    private Ressources $id_ressource;

    #[ORM\Column(type: "integer")]
    private int $quantite;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_deb;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_fin;

    #[ORM\Column(type: "string")]
    private string $prix_ligne;

    public function getId_ligne()
    {
        return $this->id_ligne;
    }

    public function setId_ligne($value)
    {
        $this->id_ligne = $value;
    }

    public function getId_commande()
    {
        return $this->id_commande;
    }

    public function setId_commande($value)
    {
        $this->id_commande = $value;
    }

    public function getId_ressource()
    {
        return $this->id_ressource;
    }

    public function setId_ressource($value)
    {
        $this->id_ressource = $value;
    }

    public function getQuantite()
    {
        return $this->quantite;
    }

    public function setQuantite($value)
    {
        $this->quantite = $value;
    }

    public function getDate_deb()
    {
        return $this->date_deb;
    }

    public function setDate_deb($value)
    {
        $this->date_deb = $value;
    }

    public function getDate_fin()
    {
        return $this->date_fin;
    }

    public function setDate_fin($value)
    {
        $this->date_fin = $value;
    }

    public function getPrix_ligne()
    {
        return $this->prix_ligne;
    }

    public function setPrix_ligne($value)
    {
        $this->prix_ligne = $value;
    }
}

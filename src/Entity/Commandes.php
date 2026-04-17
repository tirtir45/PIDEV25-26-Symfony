<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateur;
use Doctrine\Common\Collections\Collection;
use App\Entity\Lignes_commande;

#[ORM\Entity]
class Commandes
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_commande;

        #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "commandess")]
    #[ORM\JoinColumn(name: 'id_entrepreneur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateur $id_entrepreneur;

    #[ORM\Column(type: "string", length: 20)]
    private string $statut;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_creation;

    #[ORM\Column(type: "string")]
    private string $total_global;

    #[ORM\Column(type: "string", length: 100)]
    private string $tracking_number;

    #[ORM\Column(type: "string", length: 50)]
    private string $carrier_code;

    public function getId_commande()
    {
        return $this->id_commande;
    }

    public function setId_commande($value)
    {
        $this->id_commande = $value;
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

    public function getDate_creation()
    {
        return $this->date_creation;
    }

    public function setDate_creation($value)
    {
        $this->date_creation = $value;
    }

    public function getTotal_global()
    {
        return $this->total_global;
    }

    public function setTotal_global($value)
    {
        $this->total_global = $value;
    }

    public function getTracking_number()
    {
        return $this->tracking_number;
    }

    public function setTracking_number($value)
    {
        $this->tracking_number = $value;
    }

    public function getCarrier_code()
    {
        return $this->carrier_code;
    }

    public function setCarrier_code($value)
    {
        $this->carrier_code = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_commande", targetEntity: Lignes_commande::class)]
    private Collection $lignes_commandes;

        public function getLignes_commandes(): Collection
        {
            return $this->lignes_commandes;
        }
    
        public function addLignes_commande(Lignes_commande $lignes_commande): self
        {
            if (!$this->lignes_commandes->contains($lignes_commande)) {
                $this->lignes_commandes[] = $lignes_commande;
                $lignes_commande->setId_commande($this);
            }
    
            return $this;
        }
    
        public function removeLignes_commande(Lignes_commande $lignes_commande): self
        {
            if ($this->lignes_commandes->removeElement($lignes_commande)) {
                // set the owning side to null (unless already changed)
                if ($lignes_commande->getId_commande() === $this) {
                    $lignes_commande->setId_commande(null);
                }
            }
    
            return $this;
        }
}

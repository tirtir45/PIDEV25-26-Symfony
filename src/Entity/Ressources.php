<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;
use Doctrine\Common\Collections\Collection;
use App\Entity\Lignes_commande;

#[ORM\Entity]
class Ressources
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_ressource;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "ressourcess")]
    #[ORM\JoinColumn(name: 'id_fournisseur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_fournisseur;

    #[ORM\Column(type: "string", length: 150)]
    private string $nom;

    #[ORM\Column(type: "string", length: 30)]
    private string $offre;

    #[ORM\Column(type: "string", length: 30)]
    private string $type_r;

    #[ORM\Column(type: "string", length: 255)]
    private string $image_r;

    #[ORM\Column(type: "string")]
    private string $prix_achat;

    #[ORM\Column(type: "boolean")]
    private bool $disponibilite;

    #[ORM\Column(type: "string")]
    private string $prix_louer;

    #[ORM\Column(type: "string", length: 30)]
    private string $unite_louer;

    #[ORM\Column(type: "integer")]
    private int $quantite;

    #[ORM\Column(type: "string", length: 30)]
    private string $etat;

    #[ORM\Column(type: "text")]
    private string $description;

    public function getId_ressource()
    {
        return $this->id_ressource;
    }

    public function setId_ressource($value)
    {
        $this->id_ressource = $value;
    }

    public function getId_fournisseur()
    {
        return $this->id_fournisseur;
    }

    public function setId_fournisseur($value)
    {
        $this->id_fournisseur = $value;
    }

    public function getNom()
    {
        return $this->nom;
    }

    public function setNom($value)
    {
        $this->nom = $value;
    }

    public function getOffre()
    {
        return $this->offre;
    }

    public function setOffre($value)
    {
        $this->offre = $value;
    }

    public function getType_r()
    {
        return $this->type_r;
    }

    public function setType_r($value)
    {
        $this->type_r = $value;
    }

    public function getImage_r()
    {
        return $this->image_r;
    }

    public function setImage_r($value)
    {
        $this->image_r = $value;
    }

    public function getPrix_achat()
    {
        return $this->prix_achat;
    }

    public function setPrix_achat($value)
    {
        $this->prix_achat = $value;
    }

    public function getDisponibilite()
    {
        return $this->disponibilite;
    }

    public function setDisponibilite($value)
    {
        $this->disponibilite = $value;
    }

    public function getPrix_louer()
    {
        return $this->prix_louer;
    }

    public function setPrix_louer($value)
    {
        $this->prix_louer = $value;
    }

    public function getUnite_louer()
    {
        return $this->unite_louer;
    }

    public function setUnite_louer($value)
    {
        $this->unite_louer = $value;
    }

    public function getQuantite()
    {
        return $this->quantite;
    }

    public function setQuantite($value)
    {
        $this->quantite = $value;
    }

    public function getEtat()
    {
        return $this->etat;
    }

    public function setEtat($value)
    {
        $this->etat = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_ressource", targetEntity: Demandes_ressources::class)]
    private Collection $demandes_ressourcess;

        public function getDemandes_ressourcess(): Collection
        {
            return $this->demandes_ressourcess;
        }
    
        public function addDemandes_ressources(Demandes_ressources $demandes_ressources): self
        {
            if (!$this->demandes_ressourcess->contains($demandes_ressources)) {
                $this->demandes_ressourcess[] = $demandes_ressources;
                $demandes_ressources->setId_ressource($this);
            }
    
            return $this;
        }
    
        public function removeDemandes_ressources(Demandes_ressources $demandes_ressources): self
        {
            if ($this->demandes_ressourcess->removeElement($demandes_ressources)) {
                // set the owning side to null (unless already changed)
                if ($demandes_ressources->getId_ressource() === $this) {
                    $demandes_ressources->setId_ressource(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "id_ressource", targetEntity: Lignes_commande::class)]
    private Collection $lignes_commandes;
}

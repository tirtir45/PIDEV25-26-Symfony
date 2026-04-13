<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Demandes_ressources;

#[ORM\Entity]
class Ressources
{

    public function __construct()
    {
        $this->demandes_ressourcess = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_ressource", type: "integer")]
    private ?int $id_ressource = null;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "ressourcess")]
    #[ORM\JoinColumn(name: 'id_fournisseur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
        private ?Utilisateurs $id_fournisseur = null;

    #[ORM\Column(type: "string", length: 150)]
    private string $nom = '';

    #[ORM\Column(type: "string", length: 30)]
    private string $offre = '';

    #[ORM\Column(type: "string", length: 30)]
    private string $type_r = '';

    #[ORM\Column(type: "string", length: 255)]
    private string $image_r = '';

    #[ORM\Column(type: "float")]
    private float $prix_achat = 0.0;

    #[ORM\Column(type: "boolean")]
    private bool $disponibilite = false;

    #[ORM\Column(type: "float")]
    private float $prix_louer = 0.0;

    #[ORM\Column(type: "string", length: 30)]
    private string $unite_louer = '';

    #[ORM\Column(type: "integer")]
    private int $quantite = 0;

    #[ORM\Column(type: "string", length: 30)]
    private string $etat = '';

    #[ORM\Column(type: "text")]
    private string $description = '';

    #[ORM\Column(name: "created_at", type: "datetime_immutable")]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: "boolean")]
    private bool $is_banned = false;

    #[ORM\Column(type: "integer")]
    private int $moderation_score = 0;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $moderation_reason = null;

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
        return isset($this->nom) ? $this->nom : '';
    }

    public function setNom($value)
    {
        $this->nom = $value;
    }

    public function getOffre()
    {
        return isset($this->offre) ? $this->offre : '';
    }

    public function setOffre($value)
    {
        $this->offre = $value;
    }

    public function getType_r()
    {
        return isset($this->type_r) ? $this->type_r : '';
    }

    public function setType_r($value)
    {
        $this->type_r = $value;
    }

    public function getImage_r()
    {
        return isset($this->image_r) ? $this->image_r : '';
    }

    public function setImage_r($value)
    {
        $this->image_r = $value === null ? '' : (string) $value;
    }

    public function getPrix_achat()
    {
        return isset($this->prix_achat) ? $this->prix_achat : 0.0;
    }

    public function setPrix_achat($value)
    {
        $this->prix_achat = $value === null ? 0.0 : (float) $value;
    }

    public function getDisponibilite()
    {
        return isset($this->disponibilite) ? $this->disponibilite : false;
    }

    public function setDisponibilite($value)
    {
        $this->disponibilite = $value;
    }

    public function getPrix_louer()
    {
        return isset($this->prix_louer) ? $this->prix_louer : 0.0;
    }

    public function setPrix_louer($value)
    {
        $this->prix_louer = $value === null ? 0.0 : (float) $value;
    }

    public function getUnite_louer()
    {
        return isset($this->unite_louer) ? $this->unite_louer : '';
    }

    public function setUnite_louer($value)
    {
        $this->unite_louer = $value === null ? '' : (string) $value;
    }

    public function getQuantite()
    {
        return isset($this->quantite) ? $this->quantite : 0;
    }

    public function setQuantite($value)
    {
        $this->quantite = $value;
    }

    public function getEtat()
    {
        return isset($this->etat) ? $this->etat : '';
    }

    public function setEtat($value)
    {
        $this->etat = $value;
    }

    public function getDescription()
    {
        return isset($this->description) ? $this->description : '';
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getCreated_at(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreated_at(?\DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->getCreated_at();
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): self
    {
        return $this->setCreated_at($created_at);
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

    public function getIdRessource()
    {
        return $this->getId_ressource();
    }

    public function setIdRessource($value)
    {
        $this->setId_ressource($value);
    }

    public function getIdFournisseur()
    {
        return $this->getId_fournisseur();
    }

    public function setIdFournisseur($value)
    {
        $this->setId_fournisseur($value);
    }

    public function getTypeR()
    {
        return $this->getType_r();
    }

    public function setTypeR($value)
    {
        $this->setType_r($value);
    }

    public function getImageR()
    {
        return $this->getImage_r();
    }

    public function setImageR($value)
    {
        $this->setImage_r($value);
    }

    public function getPrixAchat()
    {
        return $this->getPrix_achat();
    }

    public function setPrixAchat($value)
    {
        $this->setPrix_achat($value);
    }

    public function getPrixLouer()
    {
        return $this->getPrix_louer();
    }

    public function setPrixLouer($value)
    {
        $this->setPrix_louer($value);
    }

    public function getUniteLouer()
    {
        return $this->getUnite_louer();
    }

    public function setUniteLouer($value)
    {
        $this->setUnite_louer($value);
    }

    public function isBanned(): bool { return $this->is_banned; }
    public function setIsBanned(bool $b): self { $this->is_banned = $b; return $this; }

    public function getModerationScore(): int { return $this->moderation_score; }
    public function setModerationScore(int $s): self { $this->moderation_score = $s; return $this; }

    public function getModerationReason(): ?string { return $this->moderation_reason; }
    public function setModerationReason(?string $r): self { $this->moderation_reason = $r; return $this; }
}

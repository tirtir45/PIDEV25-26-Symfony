<?php
 
namespace App\Entity;
 
use App\Repository\CommandesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
 
#[ORM\Entity(repositoryClass: CommandesRepository::class)]
#[ORM\Table(name: 'commandes')]
class Commandes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_commande', type: 'integer')]
    private ?int $id_commande = null;
 
    #[ORM\ManyToOne(targetEntity: Utilisateurs::class)]
    #[ORM\JoinColumn(name: 'id_fournisseur', referencedColumnName: 'id_utilisateur', nullable: true)]
    private ?Utilisateurs $id_fournisseur = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: 'commandess')]
    #[ORM\JoinColumn(name: 'id_entrepreneur', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateurs $id_entrepreneur = null;
 
    #[ORM\Column(type: "string", length: 20)]
    private string $statut;
 
    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_creation;
 
    #[ORM\Column(type: "string")]
    private string $total_global;
 
    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $tracking_number = null;
 
    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $carrier_code = null;
 
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $adresse_livraison = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $ville_livraison = null;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $code_postal = null;

    #[ORM\Column(type: "string", length: 30, nullable: true)]
    private ?string $telephone_livraison = null;

    #[ORM\OneToMany(mappedBy: 'id_commande', targetEntity: Lignes_commande::class)]
    private Collection $lignes_commandes;

    public function __construct()
    {
        $this->lignes_commandes = new ArrayCollection();
        $this->date_creation = new \DateTime();
    }

    public function getIdCommande(): ?int
    {
        return $this->id_commande;
    }

    public function setIdCommande(int $id_commande): self
    {
        $this->id_commande = $id_commande;
        return $this;
    }

    public function getIdFournisseur(): ?Utilisateurs
    {
        return $this->id_fournisseur;
    }

    public function setIdFournisseur(?Utilisateurs $value): self
    {
        $this->id_fournisseur = $value;
        return $this;
    }

    public function getIdEntrepreneur(): ?Utilisateurs
    {
        return $this->id_entrepreneur;
    }

    public function setIdEntrepreneur(?Utilisateurs $value): self
    {
        $this->id_entrepreneur = $value;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $value): self
    {
        $this->statut = $value;
        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(\DateTimeInterface $value): self
    {
        $this->date_creation = $value;
        return $this;
    }

    public function getTotalGlobal(): string
    {
        return $this->total_global;
    }

    public function setTotalGlobal(string $value): self
    {
        $this->total_global = $value;
        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->tracking_number;
    }

    public function setTrackingNumber(?string $value): self
    {
        $this->tracking_number = $value;
        return $this;
    }

    public function getCarrierCode(): ?string
    {
        return $this->carrier_code;
    }

    public function setCarrierCode(?string $value): self
    {
        $this->carrier_code = $value;
        return $this;
    }

    public function getAdresseLivraison(): ?string
    {
        return $this->adresse_livraison;
    }

    public function setAdresseLivraison(?string $adresse_livraison): self
    {
        $this->adresse_livraison = $adresse_livraison;
        return $this;
    }

    public function getVilleLivraison(): ?string
    {
        return $this->ville_livraison;
    }

    public function setVilleLivraison(?string $ville_livraison): self
    {
        $this->ville_livraison = $ville_livraison;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->code_postal;
    }

    public function setCodePostal(?string $code_postal): self
    {
        $this->code_postal = $code_postal;
        return $this;
    }

    public function getTelephoneLivraison(): ?string
    {
        return $this->telephone_livraison;
    }

    public function setTelephoneLivraison(?string $telephone_livraison): self
    {
        $this->telephone_livraison = $telephone_livraison;
        return $this;
    }

    public function getLignes_commandes(): Collection
    {
        return $this->lignes_commandes;
    }

    public function addLignes_commande(Lignes_commande $ligne): self
    {
        if (!$this->lignes_commandes->contains($ligne)) {
            $this->lignes_commandes[] = $ligne;
            $ligne->setId_commande($this);
        }
        return $this;
    }

    public function removeLignes_commande(Lignes_commande $ligne): self
    {
        if ($this->lignes_commandes->removeElement($ligne)) {
            if ($ligne->getId_commande() === $this) {
                $ligne->setId_commande(null);
            }
        }
        return $this;
    }
}

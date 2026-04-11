<?php
 
namespace App\Entity;
 
use App\Repository\Lignes_commandeRepository;
use Doctrine\ORM\Mapping as ORM;
 
#[ORM\Entity(repositoryClass: Lignes_commandeRepository::class)]
#[ORM\Table(name: 'lignes_commande')]
class Lignes_commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_ligne', type: 'integer')]
    private ?int $id_ligne = null;
 
    #[ORM\ManyToOne(targetEntity: Commandes::class, inversedBy: 'lignes_commandes')]
    #[ORM\JoinColumn(name: 'id_commande', referencedColumnName: 'id_commande', onDelete: 'CASCADE')]
    private ?Commandes $id_commande = null;
 
    #[ORM\ManyToOne(targetEntity: Ressources::class)]
    #[ORM\JoinColumn(name: 'id_ressource', referencedColumnName: 'id_ressource', onDelete: 'CASCADE')]
    private ?Ressources $id_ressource = null;
 
    #[ORM\Column(type: "integer")]
    private int $quantite = 0;
 
    #[ORM\Column(type: "date")]
    private ?\DateTimeInterface $date_deb = null;
 
    #[ORM\Column(type: "date")]
    private ?\DateTimeInterface $date_fin = null;
 
    #[ORM\Column(type: "string", length: 255)]
    private string $prix_ligne = '0.0';
 
    public function getId_ligne(): ?int
    {
        return $this->id_ligne;
    }
 
    public function setId_ligne(int $id_ligne): self
    {
        $this->id_ligne = $id_ligne;
        return $this;
    }
 
    public function getId_commande(): ?Commandes
    {
        return $this->id_commande;
    }
 
    public function setId_commande(?Commandes $value): self
    {
        $this->id_commande = $value;
        return $this;
    }
 
    public function getId_ressource(): ?Ressources
    {
        return $this->id_ressource;
    }
 
    public function setId_ressource(?Ressources $value): self
    {
        $this->id_ressource = $value;
        return $this;
    }
 
    public function getQuantite(): int
    {
        return $this->quantite;
    }
 
    public function setQuantite(int $value): self
    {
        $this->quantite = $value;
        return $this;
    }
 
    public function getDate_deb(): ?\DateTimeInterface
    {
        return $this->date_deb;
    }
 
    public function setDate_deb(?\DateTimeInterface $value): self
    {
        $this->date_deb = $value;
        return $this;
    }
 
    public function getDate_fin(): ?\DateTimeInterface
    {
        return $this->date_fin;
    }
 
    public function setDate_fin(?\DateTimeInterface $value): self
    {
        $this->date_fin = $value;
        return $this;
    }
 
    public function getPrix_ligne(): string
    {
        return $this->prix_ligne;
    }
 
    public function setPrix_ligne(string $value): self
    {
        $this->prix_ligne = $value;
        return $this;
    }
}

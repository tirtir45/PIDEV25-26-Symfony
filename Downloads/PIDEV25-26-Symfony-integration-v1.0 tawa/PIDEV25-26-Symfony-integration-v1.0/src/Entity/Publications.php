<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Demande_emploi;

#[ORM\Entity]
class Publications
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "publication_id", type: "integer")]
    private ?int $publication_id = null;

    #[ORM\Column(type: "string", length: 150)]
    private string $titre;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_publication;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_expiration;

    #[ORM\Column(type: "string")]
    private string $type_contrat;

    #[ORM\Column(type: "string", length: 100)]
    private string $departement;

    #[ORM\Column(type: "string", length: 100)]
    private string $localisation;

    #[ORM\Column(type: "string")]
    private string $statut;

    public function getPublication_id()
    {
        return $this->publication_id;
    }

    public function setPublication_id($value)
    {
        $this->publication_id = $value;
    }

    public function getTitre()
    {
        return $this->titre;
    }

    public function setTitre($value)
    {
        $this->titre = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getDate_publication()
    {
        return $this->date_publication;
    }

    public function setDate_publication($value)
    {
        $this->date_publication = $value;
    }

    public function getDate_expiration()
    {
        return $this->date_expiration;
    }

    public function setDate_expiration($value)
    {
        $this->date_expiration = $value;
    }

    public function getType_contrat()
    {
        return $this->type_contrat;
    }

    public function setType_contrat($value)
    {
        $this->type_contrat = $value;
    }

    public function getDepartement()
    {
        return $this->departement;
    }

    public function setDepartement($value)
    {
        $this->departement = $value;
    }

    public function getLocalisation()
    {
        return $this->localisation;
    }

    public function setLocalisation($value)
    {
        $this->localisation = $value;
    }

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($value)
    {
        $this->statut = $value;
    }

    #[ORM\OneToMany(mappedBy: "publication_id", targetEntity: Demande_emplois::class)]
    private Collection $demande_emplois;
 
        public function getDemande_emplois(): Collection
        {
            return $this->demande_emplois;
        }
    
        public function addDemande_emploi(Demande_emplois $demande_emploi): self
        {
            if (!$this->demande_emplois->contains($demande_emploi)) {
                $this->demande_emplois[] = $demande_emploi;
                $demande_emploi->setPublication_id($this);
            }
    
            return $this;
        }
    
        public function removeDemande_emploi(Demande_emplois $demande_emploi): self
        {
            if ($this->demande_emplois->removeElement($demande_emploi)) {
                // set the owning side to null (unless already changed)
                if ($demande_emploi->getPublication_id() === $this) {
                    $demande_emploi->setPublication_id(null);
                }
            }
    
            return $this;
        }
}

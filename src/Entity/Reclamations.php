<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateur;
use Doctrine\Common\Collections\Collection;
use App\Entity\Reclamation_commentaires;

#[ORM\Entity]
class Reclamations
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_reclamation;

        #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "reclamationss")]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateur $id_utilisateur;

    #[ORM\Column(type: "string", length: 150)]
    private string $sujet;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "string", length: 20)]
    private string $statut;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_creation;

    #[ORM\Column(type: "string", length: 500)]
    private string $adresse;

    #[ORM\Column(type: "float")]
    private float $latitude;

    #[ORM\Column(type: "float")]
    private float $longitude;

    #[ORM\Column(type: "string", length: 100)]
    private string $emotion;

    #[ORM\Column(type: "text")]
    private string $reponse_admin;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_modification;

    public function getId_reclamation()
    {
        return $this->id_reclamation;
    }

    public function setId_reclamation($value)
    {
        $this->id_reclamation = $value;
    }

    public function getId_utilisateur()
    {
        return $this->id_utilisateur;
    }

    public function setId_utilisateur($value)
    {
        $this->id_utilisateur = $value;
    }

    public function getSujet()
    {
        return $this->sujet;
    }

    public function setSujet($value)
    {
        $this->sujet = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
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

    public function getAdresse()
    {
        return $this->adresse;
    }

    public function setAdresse($value)
    {
        $this->adresse = $value;
    }

    public function getLatitude()
    {
        return $this->latitude;
    }

    public function setLatitude($value)
    {
        $this->latitude = $value;
    }

    public function getLongitude()
    {
        return $this->longitude;
    }

    public function setLongitude($value)
    {
        $this->longitude = $value;
    }

    public function getEmotion()
    {
        return $this->emotion;
    }

    public function setEmotion($value)
    {
        $this->emotion = $value;
    }

    public function getReponse_admin()
    {
        return $this->reponse_admin;
    }

    public function setReponse_admin($value)
    {
        $this->reponse_admin = $value;
    }

    public function getDate_modification()
    {
        return $this->date_modification;
    }

    public function setDate_modification($value)
    {
        $this->date_modification = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_reclamation", targetEntity: Reclamation_commentaires::class)]
    private Collection $reclamation_commentairess;

        public function getReclamation_commentairess(): Collection
        {
            return $this->reclamation_commentairess;
        }
    
        public function addReclamation_commentaires(Reclamation_commentaires $reclamation_commentaires): self
        {
            if (!$this->reclamation_commentairess->contains($reclamation_commentaires)) {
                $this->reclamation_commentairess[] = $reclamation_commentaires;
                $reclamation_commentaires->setId_reclamation($this);
            }
    
            return $this;
        }
    
        public function removeReclamation_commentaires(Reclamation_commentaires $reclamation_commentaires): self
        {
            if ($this->reclamation_commentairess->removeElement($reclamation_commentaires)) {
                // set the owning side to null (unless already changed)
                if ($reclamation_commentaires->getId_reclamation() === $this) {
                    $reclamation_commentaires->setId_reclamation(null);
                }
            }
    
            return $this;
        }
}

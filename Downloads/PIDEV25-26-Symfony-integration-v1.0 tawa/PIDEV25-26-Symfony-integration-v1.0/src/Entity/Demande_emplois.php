<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;
use App\Entity\Publications;

#[ORM\Entity]
class Demande_emplois
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "demande_id", type: "integer")]
    private ?int $demande_id = null;

    #[ORM\ManyToOne(targetEntity: Publications::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'publication_id', onDelete: 'CASCADE')]
    private ?Publications $publication_id;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "demande_emplois")]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $candidat_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $cv_url;

    #[ORM\Column(type: "text")]
    private string $lettre_motivation;

    #[ORM\Column(type: "text")]
    private string $motivation_ciblee;

    #[ORM\Column(type: "string")]
    private string $statut_demande;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_demande;

    public function getDemande_id()
    {
        return $this->demande_id;
    }

    public function setDemande_id($value)
    {
        $this->demande_id = $value;
    }

    public function getPublication_id(): ?Publications
    {
        return $this->publication_id;
    }

    public function setPublication_id(?Publications $value)
    {
        $this->publication_id = $value;
    }

    public function getCandidat_id()
    {
        return $this->candidat_id;
    }

    public function setCandidat_id($value)
    {
        $this->candidat_id = $value;
    }

    public function getCv_url()
    {
        return $this->cv_url;
    }

    public function setCv_url($value)
    {
        $this->cv_url = $value;
    }

    public function getLettre_motivation()
    {
        return $this->lettre_motivation;
    }

    public function setLettre_motivation($value)
    {
        $this->lettre_motivation = $value;
    }

    public function getMotivation_ciblee()
    {
        return $this->motivation_ciblee;
    }

    public function setMotivation_ciblee($value)
    {
        $this->motivation_ciblee = $value;
    }

    public function getStatut_demande()
    {
        return $this->statut_demande;
    }

    public function setStatut_demande($value)
    {
        $this->statut_demande = $value;
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

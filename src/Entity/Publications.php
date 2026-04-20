<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\PublicationsRepository::class)]
#[ORM\Table(name: 'publications')]
class Publications
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "publication_id", type: "integer")]
    private ?int $publication_id = null;

    #[ORM\Column(type: "string", length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: "text")]
    private ?string $description = null;

    #[ORM\Column(type: "date")]
    private ?\DateTimeInterface $date_publication = null;

    #[ORM\Column(type: "date")]
    private ?\DateTimeInterface $date_expiration = null;

    #[ORM\Column(type: "string")]
    private ?string $type_contrat = null;

    #[ORM\Column(type: "string", length: 100)]
    private ?string $departement = null;

    #[ORM\Column(type: "string", length: 100)]
    private ?string $localisation = null;

    #[ORM\Column(type: "string")]
    private ?string $statut = null;

    #[ORM\OneToMany(mappedBy: "publication_id", targetEntity: Demande_emplois::class, cascade: ['remove'])]
    private Collection $demande_emplois;

    public function __construct()
    {
        $this->demande_emplois = new ArrayCollection();
    }

    // ── Primary key ──────────────────────────────────────────────────────────

    public function getPublication_id(): ?int { return $this->publication_id; }
    public function setPublication_id($value): self { $this->publication_id = $value; return $this; }

    /** camelCase alias */
    public function getPublicationId(): ?int { return $this->publication_id; }

    // ── Fields ───────────────────────────────────────────────────────────────

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $value): self { $this->titre = $value; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $value): self { $this->description = $value; return $this; }

    public function getDate_publication(): ?\DateTimeInterface { return $this->date_publication; }
    public function setDate_publication($value): self { $this->date_publication = $value; return $this; }

    public function getDate_expiration(): ?\DateTimeInterface { return $this->date_expiration; }
    public function setDate_expiration($value): self { $this->date_expiration = $value; return $this; }

    public function getType_contrat(): ?string { return $this->type_contrat; }
    public function setType_contrat(?string $value): self { $this->type_contrat = $value; return $this; }

    public function getDepartement(): ?string { return $this->departement; }
    public function setDepartement(?string $value): self { $this->departement = $value; return $this; }

    public function getLocalisation(): ?string { return $this->localisation; }
    public function setLocalisation(?string $value): self { $this->localisation = $value; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $value): self { $this->statut = $value; return $this; }

    // ── Relation: demande_emplois ─────────────────────────────────────────────

    public function getDemande_emplois(): Collection { return $this->demande_emplois; }

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
            if ($demande_emploi->getPublication_id() === $this) {
                $demande_emploi->setPublication_id(null);
            }
        }
        return $this;
    }
}

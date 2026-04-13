<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * L'annotation Callback est ici pour la validation croisée des dates.
 */
#[Assert\Callback(callback: 'validate')]
#[ORM\Entity]
class Publication
{
    // ===================================================================
    // = PROPERTIES (en snake_case)
    // ===================================================================

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $publication_id = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank(message: "Le titre ne peut pas être vide.")]
    #[Assert\Length(min: 5, max: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description ne peut pas être vide.")]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "La date de publication est obligatoire.")]
    private ?\DateTimeInterface $date_publication = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "La date d'expiration est obligatoire.")]
    private ?\DateTimeInterface $date_expiration = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: "Le type de contrat est obligatoire.")]
    private ?string $type_contrat = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    private ?string $departement = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    private ?string $localisation = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    private ?string $statut = 'active';

    /**
     * @var Collection<int, Demande_emploi>
     */
    #[ORM\OneToMany(mappedBy: "publication", targetEntity: Demande_emploi::class, cascade: ["remove"])]
    private Collection $demande_emplois;

    public function __construct()
    {
        $this->demande_emplois = new ArrayCollection();
    }

    // ===================================================================
    // = VALIDATION PERSONNALISÉE AVEC CALLBACK
    // ===================================================================
    
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        if ($this->date_publication === null || $this->date_expiration === null) {
            return;
        }

        if ($this->date_expiration <= $this->date_publication) {
            $context->buildViolation('La date d\'expiration doit être ultérieure à la date de publication.')
                ->atPath('date_expiration')
                ->addViolation();
        }
    }

    // ===================================================================
    // = GETTERS & SETTERS (en snake_case, avec les types `?` et `self`)
    // ===================================================================

    public function getPublication_id(): ?int
    {
        return $this->publication_id;
    }

    // ... autres getters et setters pour les propriétés simples ...

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $titre): self { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getDate_publication(): ?\DateTimeInterface { return $this->date_publication; }
    public function setDate_publication(?\DateTimeInterface $date_publication): self { $this->date_publication = $date_publication; return $this; }
    public function getDate_expiration(): ?\DateTimeInterface { return $this->date_expiration; }
    public function setDate_expiration(?\DateTimeInterface $date_expiration): self { $this->date_expiration = $date_expiration; return $this; }
    public function getType_contrat(): ?string { return $this->type_contrat; }
    public function setType_contrat(?string $type_contrat): self { $this->type_contrat = $type_contrat; return $this; }
    public function getDepartement(): ?string { return $this->departement; }
    public function setDepartement(?string $departement): self { $this->departement = $departement; return $this; }
    public function getLocalisation(): ?string { return $this->localisation; }
    public function setLocalisation(?string $localisation): self { $this->localisation = $localisation; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }

    // ===================================================================
    // = MÉTHODES POUR LA RELATION (PARTIE CORRIGÉE ET COMPLÈTE)
    // ===================================================================

    /**
     * @return Collection<int, Demande_emploi>
     */
    public function getDemande_emplois(): Collection
    {
        return $this->demande_emplois;
    }

    public function addDemande_emploi(Demande_emploi $demande_emploi): self
    {
        if (!$this->demande_emplois->contains($demande_emploi)) {
            $this->demande_emplois->add($demande_emploi);
            $demande_emploi->setPublication($this); // Assure la cohérence de la relation
        }

        return $this;
    }

    public function removeDemande_emploi(Demande_emploi $demande_emploi): self
    {
        if ($this->demande_emplois->removeElement($demande_emploi)) {
            // Met à jour l'autre côté de la relation pour éviter une demande "orpheline"
            if ($demande_emploi->getPublication() === $this) {
                $demande_emploi->setPublication(null);
            }
        }

        return $this;
    }
}
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Utilisateur;

#[ORM\Entity]
class Roles
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_role;

    #[ORM\Column(type: "string", length: 50)]
    private string $nom_role;

    public function getId_role()
    {
        return $this->id_role;
    }

    public function setId_role($value)
    {
        $this->id_role = $value;
    }

    public function getNom_role()
    {
        return $this->nom_role;
    }

    public function setNom_role($value)
    {
        $this->nom_role = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_role", targetEntity: Utilisateur::class)]
    private Collection $utilisateurss;

        public function getUtilisateurss(): Collection
        {
            return $this->utilisateurss;
        }
    
        public function addUtilisateurs(Utilisateur $utilisateurs): self
        {
            if (!$this->utilisateurss->contains($utilisateurs)) {
                $this->utilisateurss[] = $utilisateurs;
                $utilisateurs->setId_role($this);
            }
    
            return $this;
        }
    
        public function removeUtilisateurs(Utilisateur $utilisateurs): self
        {
            if ($this->utilisateurss->removeElement($utilisateurs)) {
                // set the owning side to null (unless already changed)
                if ($utilisateurs->getId_role() === $this) {
                    $utilisateurs->setId_role(null);
                }
            }
    
            return $this;
        }
}

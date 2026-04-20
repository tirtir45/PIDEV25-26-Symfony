<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;

#[ORM\Entity]
class Reclamation_commentaires
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_commentaire", type: "integer")]
    private ?int $id_commentaire = null;

        #[ORM\ManyToOne(targetEntity: Reclamations::class, inversedBy: "reclamation_commentairess")]
    #[ORM\JoinColumn(name: 'id_reclamation', referencedColumnName: 'id_reclamation', onDelete: 'CASCADE')]
    private Reclamations $id_reclamation;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "reclamation_commentairess")]
    #[ORM\JoinColumn(name: 'id_auteur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_auteur;

    #[ORM\Column(type: "text")]
    private string $commentaire;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_commentaire;

    public function getId_commentaire()
    {
        return $this->id_commentaire;
    }

    public function setId_commentaire($value)
    {
        $this->id_commentaire = $value;
    }

    public function getId_reclamation()
    {
        return $this->id_reclamation;
    }

    public function setId_reclamation($value)
    {
        $this->id_reclamation = $value;
    }

    public function getId_auteur()
    {
        return $this->id_auteur;
    }

    public function setId_auteur($value)
    {
        $this->id_auteur = $value;
    }

    public function getCommentaire()
    {
        return $this->commentaire;
    }

    public function setCommentaire($value)
    {
        $this->commentaire = $value;
    }

    public function getDate_commentaire()
    {
        return $this->date_commentaire;
    }

    public function setDate_commentaire($value)
    {
        $this->date_commentaire = $value;
    }
}

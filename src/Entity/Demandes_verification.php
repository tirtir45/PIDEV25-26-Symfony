<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;

#[ORM\Entity]
class Demandes_verification
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_demande", type: "integer")]
    private ?int $id_demande = null;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "demandes_verifications")]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_utilisateur;

    #[ORM\Column(type: "string", length: 50)]
    private string $type_role;

    #[ORM\Column(type: "string")]
    private string $statut;

    #[ORM\Column(type: "text")]
    private string $raison_demande;

    #[ORM\Column(type: "string", length: 255)]
    private string $document_justificatif;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_demande;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_traitement;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "demandes_verifications_traitees")]
    #[ORM\JoinColumn(name: 'id_admin_traitant', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_admin_traitant;

    #[ORM\Column(type: "text")]
    private string $commentaire_admin;

    public function getId_demande()
    {
        return $this->id_demande;
    }

    public function setId_demande($value)
    {
        $this->id_demande = $value;
    }

    public function getId_utilisateur()
    {
        return $this->id_utilisateur;
    }

    public function setId_utilisateur($value)
    {
        $this->id_utilisateur = $value;
    }

    public function getType_role()
    {
        return $this->type_role;
    }

    public function setType_role($value)
    {
        $this->type_role = $value;
    }

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($value)
    {
        $this->statut = $value;
    }

    public function getRaison_demande()
    {
        return $this->raison_demande;
    }

    public function setRaison_demande($value)
    {
        $this->raison_demande = $value;
    }

    public function getDocument_justificatif()
    {
        return $this->document_justificatif;
    }

    public function setDocument_justificatif($value)
    {
        $this->document_justificatif = $value;
    }

    public function getDate_demande()
    {
        return $this->date_demande;
    }

    public function setDate_demande($value)
    {
        $this->date_demande = $value;
    }

    public function getDate_traitement()
    {
        return $this->date_traitement;
    }

    public function setDate_traitement($value)
    {
        $this->date_traitement = $value;
    }

    public function getId_admin_traitant()
    {
        return $this->id_admin_traitant;
    }

    public function setId_admin_traitant($value)
    {
        $this->id_admin_traitant = $value;
    }

    public function getCommentaire_admin()
    {
        return $this->commentaire_admin;
    }

    public function setCommentaire_admin($value)
    {
        $this->commentaire_admin = $value;
    }
}

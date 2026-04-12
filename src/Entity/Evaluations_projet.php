<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;

#[ORM\Entity]
class Evaluations_projet
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_evaluation;

        #[ORM\ManyToOne(targetEntity: Projets::class, inversedBy: "evaluations_projets")]
    #[ORM\JoinColumn(name: 'id_projet', referencedColumnName: 'id_projet', onDelete: 'CASCADE')]
    private Projets $id_projet;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "evaluations_projets")]
    #[ORM\JoinColumn(name: 'id_admin', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_admin;

    #[ORM\Column(type: "integer")]
    private int $note_originalite;

    #[ORM\Column(type: "integer")]
    private int $note_faisabilite;

    #[ORM\Column(type: "integer")]
    private int $note_impact;

    #[ORM\Column(type: "integer")]
    private int $note_clarte;

    #[ORM\Column(type: "integer")]
    private int $note_budget;

    #[ORM\Column(type: "text")]
    private string $commentaire;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_evaluation;

    public function getId_evaluation()
    {
        return $this->id_evaluation;
    }

    public function setId_evaluation($value)
    {
        $this->id_evaluation = $value;
    }

    public function getId_projet()
    {
        return $this->id_projet;
    }

    public function setId_projet($value)
    {
        $this->id_projet = $value;
    }

    public function getId_admin()
    {
        return $this->id_admin;
    }

    public function setId_admin($value)
    {
        $this->id_admin = $value;
    }

    public function getNote_originalite()
    {
        return $this->note_originalite;
    }

    public function setNote_originalite($value)
    {
        $this->note_originalite = $value;
    }

    public function getNote_faisabilite()
    {
        return $this->note_faisabilite;
    }

    public function setNote_faisabilite($value)
    {
        $this->note_faisabilite = $value;
    }

    public function getNote_impact()
    {
        return $this->note_impact;
    }

    public function setNote_impact($value)
    {
        $this->note_impact = $value;
    }

    public function getNote_clarte()
    {
        return $this->note_clarte;
    }

    public function setNote_clarte($value)
    {
        $this->note_clarte = $value;
    }

    public function getNote_budget()
    {
        return $this->note_budget;
    }

    public function setNote_budget($value)
    {
        $this->note_budget = $value;
    }

    public function getCommentaire()
    {
        return $this->commentaire;
    }

    public function setCommentaire($value)
    {
        $this->commentaire = $value;
    }

    public function getDate_evaluation()
    {
        return $this->date_evaluation;
    }

    public function setDate_evaluation($value)
    {
        $this->date_evaluation = $value;
    }
}

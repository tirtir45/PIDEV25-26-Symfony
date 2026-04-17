<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateur;

#[ORM\Entity]
class Messages
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_message;

        #[ORM\ManyToOne(targetEntity: Conversations::class, inversedBy: "messagess")]
    #[ORM\JoinColumn(name: 'id_conversation', referencedColumnName: 'id_conversation', onDelete: 'CASCADE')]
    private Conversations $id_conversation;

        #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: "messagess")]
    #[ORM\JoinColumn(name: 'id_expediteur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateur $id_expediteur;

    #[ORM\Column(type: "text")]
    private string $contenu;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_envoi;

    #[ORM\Column(type: "boolean")]
    private bool $est_lu;

    #[ORM\Column(type: "boolean")]
    private bool $est_modifie;

    #[ORM\Column(type: "boolean")]
    private bool $est_supprime;

    #[ORM\Column(type: "string")]
    private string $type_contenu;

    #[ORM\Column(type: "string", length: 255)]
    private string $piece_jointe;

    public function getId_message()
    {
        return $this->id_message;
    }

    public function setId_message($value)
    {
        $this->id_message = $value;
    }

    public function getId_conversation()
    {
        return $this->id_conversation;
    }

    public function setId_conversation($value)
    {
        $this->id_conversation = $value;
    }

    public function getId_expediteur()
    {
        return $this->id_expediteur;
    }

    public function setId_expediteur($value)
    {
        $this->id_expediteur = $value;
    }

    public function getContenu()
    {
        return $this->contenu;
    }

    public function setContenu($value)
    {
        $this->contenu = $value;
    }

    public function getDate_envoi()
    {
        return $this->date_envoi;
    }

    public function setDate_envoi($value)
    {
        $this->date_envoi = $value;
    }

    public function getEst_lu()
    {
        return $this->est_lu;
    }

    public function setEst_lu($value)
    {
        $this->est_lu = $value;
    }

    public function getEst_modifie()
    {
        return $this->est_modifie;
    }

    public function setEst_modifie($value)
    {
        $this->est_modifie = $value;
    }

    public function getEst_supprime()
    {
        return $this->est_supprime;
    }

    public function setEst_supprime($value)
    {
        $this->est_supprime = $value;
    }

    public function getType_contenu()
    {
        return $this->type_contenu;
    }

    public function setType_contenu($value)
    {
        $this->type_contenu = $value;
    }

    public function getPiece_jointe()
    {
        return $this->piece_jointe;
    }

    public function setPiece_jointe($value)
    {
        $this->piece_jointe = $value;
    }
}

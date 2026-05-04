<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;

#[ORM\Entity]
class Conversation_participants
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_participation", type: "integer")]
    private ?int $id_participation = null;

        #[ORM\ManyToOne(targetEntity: Conversations::class, inversedBy: "conversation_participantss")]
    #[ORM\JoinColumn(name: 'id_conversation', referencedColumnName: 'id_conversation', onDelete: 'CASCADE')]
    private Conversations $id_conversation;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "conversation_participantss")]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_utilisateur;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_ajout;

    #[ORM\Column(type: "boolean")]
    private bool $est_archive;

    #[ORM\Column(type: "integer")]
    private int $dernier_message_lu;

    public function getId_participation()
    {
        return $this->id_participation;
    }

    public function setId_participation($value)
    {
        $this->id_participation = $value;
    }

    public function getId_conversation()
    {
        return $this->id_conversation;
    }

    public function setId_conversation($value)
    {
        $this->id_conversation = $value;
    }

    public function getId_utilisateur()
    {
        return $this->id_utilisateur;
    }

    public function setId_utilisateur($value)
    {
        $this->id_utilisateur = $value;
    }

    public function getDate_ajout()
    {
        return $this->date_ajout;
    }

    public function setDate_ajout($value)
    {
        $this->date_ajout = $value;
    }

    public function getEst_archive()
    {
        return $this->est_archive;
    }

    public function setEst_archive($value)
    {
        $this->est_archive = $value;
    }

    public function getDernier_message_lu()
    {
        return $this->dernier_message_lu;
    }

    public function setDernier_message_lu($value)
    {
        $this->dernier_message_lu = $value;
    }
}

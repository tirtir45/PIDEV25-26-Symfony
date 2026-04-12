<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Messages;

#[ORM\Entity]
class Conversations
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_conversation;

    #[ORM\Column(type: "string")]
    private string $type;

    #[ORM\Column(type: "string", length: 255)]
    private string $titre;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_creation;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $derniere_activite;

    public function getId_conversation()
    {
        return $this->id_conversation;
    }

    public function setId_conversation($value)
    {
        $this->id_conversation = $value;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($value)
    {
        $this->type = $value;
    }

    public function getTitre()
    {
        return $this->titre;
    }

    public function setTitre($value)
    {
        $this->titre = $value;
    }

    public function getDate_creation()
    {
        return $this->date_creation;
    }

    public function setDate_creation($value)
    {
        $this->date_creation = $value;
    }

    public function getDerniere_activite()
    {
        return $this->derniere_activite;
    }

    public function setDerniere_activite($value)
    {
        $this->derniere_activite = $value;
    }

    #[ORM\OneToMany(mappedBy: "id_conversation", targetEntity: Conversation_participants::class)]
    private Collection $conversation_participantss;

        public function getConversation_participantss(): Collection
        {
            return $this->conversation_participantss;
        }
    
        public function addConversation_participants(Conversation_participants $conversation_participants): self
        {
            if (!$this->conversation_participantss->contains($conversation_participants)) {
                $this->conversation_participantss[] = $conversation_participants;
                $conversation_participants->setId_conversation($this);
            }
    
            return $this;
        }
    
        public function removeConversation_participants(Conversation_participants $conversation_participants): self
        {
            if ($this->conversation_participantss->removeElement($conversation_participants)) {
                // set the owning side to null (unless already changed)
                if ($conversation_participants->getId_conversation() === $this) {
                    $conversation_participants->setId_conversation(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "id_conversation", targetEntity: Messages::class)]
    private Collection $messagess;
}

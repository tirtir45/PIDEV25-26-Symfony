<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Membres_equipe;

#[ORM\Entity]
class User_story_assignments
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_assignment;

        #[ORM\ManyToOne(targetEntity: User_stories::class, inversedBy: "user_story_assignmentss")]
    #[ORM\JoinColumn(name: 'id_user_story', referencedColumnName: 'id_user_story', onDelete: 'CASCADE')]
    private User_stories $id_user_story;

        #[ORM\ManyToOne(targetEntity: Membres_equipe::class, inversedBy: "user_story_assignmentss")]
    #[ORM\JoinColumn(name: 'id_membre_equipe', referencedColumnName: 'id_membre', onDelete: 'CASCADE')]
    private Membres_equipe $id_membre_equipe;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date_assignment;

    public function getId_assignment()
    {
        return $this->id_assignment;
    }

    public function setId_assignment($value)
    {
        $this->id_assignment = $value;
    }

    public function getId_user_story()
    {
        return $this->id_user_story;
    }

    public function setId_user_story($value)
    {
        $this->id_user_story = $value;
    }

    public function getId_membre_equipe()
    {
        return $this->id_membre_equipe;
    }

    public function setId_membre_equipe($value)
    {
        $this->id_membre_equipe = $value;
    }

    public function getDate_assignment()
    {
        return $this->date_assignment;
    }

    public function setDate_assignment($value)
    {
        $this->date_assignment = $value;
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Sprints;

#[ORM\Entity]
class Daily_scrums
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_daily;

        #[ORM\ManyToOne(targetEntity: Sprints::class, inversedBy: "daily_scrumss")]
    #[ORM\JoinColumn(name: 'id_sprint', referencedColumnName: 'id_sprint', onDelete: 'CASCADE')]
    private Sprints $id_sprint;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date;

    #[ORM\Column(type: "integer")]
    private int $points_restants;

    #[ORM\Column(type: "text")]
    private string $commentaires;

    public function getId_daily()
    {
        return $this->id_daily;
    }

    public function setId_daily($value)
    {
        $this->id_daily = $value;
    }

    public function getId_sprint()
    {
        return $this->id_sprint;
    }

    public function setId_sprint($value)
    {
        $this->id_sprint = $value;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($value)
    {
        $this->date = $value;
    }

    public function getPoints_restants()
    {
        return $this->points_restants;
    }

    public function setPoints_restants($value)
    {
        $this->points_restants = $value;
    }

    public function getCommentaires()
    {
        return $this->commentaires;
    }

    public function setCommentaires($value)
    {
        $this->commentaires = $value;
    }
}

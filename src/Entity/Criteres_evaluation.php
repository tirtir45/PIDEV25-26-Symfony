<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Criteres_evaluation
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id_critere;

    #[ORM\Column(type: "string", length: 100)]
    private string $nom_critere;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "integer")]
    private int $poids;

    #[ORM\Column(type: "boolean")]
    private bool $actif;

    public function getId_critere()
    {
        return $this->id_critere;
    }

    public function setId_critere($value)
    {
        $this->id_critere = $value;
    }

    public function getNom_critere()
    {
        return $this->nom_critere;
    }

    public function setNom_critere($value)
    {
        $this->nom_critere = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getPoids()
    {
        return $this->poids;
    }

    public function setPoids($value)
    {
        $this->poids = $value;
    }

    public function getActif()
    {
        return $this->actif;
    }

    public function setActif($value)
    {
        $this->actif = $value;
    }
}

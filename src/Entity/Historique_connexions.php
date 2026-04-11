<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Utilisateurs;

#[ORM\Entity]
class Historique_connexions
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_historique", type: "integer")]
    private ?int $id_historique = null;

        #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: "historique_connexionss")]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private Utilisateurs $id_utilisateur;

    #[ORM\Column(type: "string", length: 45)]
    private string $adresse_ip;

    #[ORM\Column(type: "string", length: 100)]
    private string $ville;

    #[ORM\Column(type: "string", length: 100)]
    private string $pays;

    #[ORM\Column(type: "string", length: 100)]
    private string $region;

    #[ORM\Column(type: "string", length: 20)]
    private string $code_postal;

    #[ORM\Column(type: "string", length: 50)]
    private string $timezone;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date_connexion;

    #[ORM\Column(type: "text")]
    private string $user_agent;

    public function getId_historique()
    {
        return $this->id_historique;
    }

    public function setId_historique($value)
    {
        $this->id_historique = $value;
    }

    public function getId_utilisateur()
    {
        return $this->id_utilisateur;
    }

    public function setId_utilisateur($value)
    {
        $this->id_utilisateur = $value;
    }

    public function getAdresse_ip()
    {
        return $this->adresse_ip;
    }

    public function setAdresse_ip($value)
    {
        $this->adresse_ip = $value;
    }

    public function getVille()
    {
        return $this->ville;
    }

    public function setVille($value)
    {
        $this->ville = $value;
    }

    public function getPays()
    {
        return $this->pays;
    }

    public function setPays($value)
    {
        $this->pays = $value;
    }

    public function getRegion()
    {
        return $this->region;
    }

    public function setRegion($value)
    {
        $this->region = $value;
    }

    public function getCode_postal()
    {
        return $this->code_postal;
    }

    public function setCode_postal($value)
    {
        $this->code_postal = $value;
    }

    public function getTimezone()
    {
        return $this->timezone;
    }

    public function setTimezone($value)
    {
        $this->timezone = $value;
    }

    public function getDate_connexion()
    {
        return $this->date_connexion;
    }

    public function setDate_connexion($value)
    {
        $this->date_connexion = $value;
    }

    public function getUser_agent()
    {
        return $this->user_agent;
    }

    public function setUser_agent($value)
    {
        $this->user_agent = $value;
    }
}

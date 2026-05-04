<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'projets')]
class Projets
{
    public const ETAT_EN_ATTENTE = 'En attente';
    public const ETAT_ACCEPTE = 'Accepté';
    public const ETAT_REFUSE = 'Refusé';
    public const ETAT_EN_COURS = 'En cours';
    public const ETAT_TERMINE = 'Terminé';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_projet', type: 'integer')]
    private ?int $id_projet = null;

    #[ORM\ManyToOne(targetEntity: Utilisateurs::class, inversedBy: 'projetss')]
    #[ORM\JoinColumn(name: 'id_entrepreneur', referencedColumnName: 'id_utilisateur', onDelete: 'CASCADE')]
    private ?Utilisateurs $id_entrepreneur = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $secteur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objectifs = null;

    #[ORM\Column(type: 'string', options: ['default' => 'En attente'])]
    private ?string $etat = self::ETAT_EN_ATTENTE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire_admin = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_soumission = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $note_moyenne = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_evaluation = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $evaluation_automatique = false;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $budget_estime = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $duree_estimee = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $statut_juridique = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $financement_actuel = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $site_web = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $experiences_anterieures = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $partenaires_potentiels = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $email_contact = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $telephone_contact = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $question1_answer = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $question2_answer = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $question3_answer = null;

    // ===== AI / MARKET / PATENT FIELDS =====

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $ai_score_global = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ai_analysis_data = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $ai_analyzed_at = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $market_score = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $market_trend = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $market_data = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $market_analyzed_at = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $patent_novelty_score = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $patent_total_count = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $patent_data = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $patent_analyzed_at = null;

    // ===== RELATIONS =====

    #[ORM\OneToMany(mappedBy: 'id_projet', targetEntity: Sprints::class)]
    private Collection $sprintss;

    #[ORM\OneToMany(mappedBy: 'id_projet', targetEntity: Evaluations_projet::class)]
    private Collection $evaluations_projets;

    #[ORM\OneToMany(mappedBy: 'id_projet', targetEntity: Membres_equipe::class)]
    private Collection $membres_equipes;

    #[ORM\OneToMany(mappedBy: 'id_projet', targetEntity: Userstory::class)]
    private Collection $userstorys;

    #[ORM\OneToMany(mappedBy: 'id_projet', targetEntity: Taches::class)]
    private Collection $tachess;

    #[ORM\OneToMany(mappedBy: 'id_projet', targetEntity: User_stories::class)]
    private Collection $user_storiess;

    // ===== CONSTRUCTOR =====

    public function __construct()
    {
        $this->date_soumission = new \DateTime();
        $this->sprintss = new ArrayCollection();
        $this->evaluations_projets = new ArrayCollection();
        $this->membres_equipes = new ArrayCollection();
        $this->userstorys = new ArrayCollection();
        $this->tachess = new ArrayCollection();
        $this->user_storiess = new ArrayCollection();
    }

    // ===== GETTERS & SETTERS =====

    public function getIdProjet(): ?int { return $this->id_projet; }
    public function getId_projet(): ?int { return $this->id_projet; }

    public function getIdEntrepreneur(): ?Utilisateurs { return $this->id_entrepreneur; }
    public function getId_entrepreneur(): ?Utilisateurs { return $this->id_entrepreneur; }
    public function setId_entrepreneur(?Utilisateurs $id_entrepreneur): static
    {
        $this->id_entrepreneur = $id_entrepreneur;
        return $this;
    }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $titre): static { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getSecteur(): ?string { return $this->secteur; }
    public function setSecteur(?string $secteur): static { $this->secteur = $secteur; return $this; }

    public function getObjectifs(): ?string { return $this->objectifs; }
    public function setObjectifs(?string $objectifs): static { $this->objectifs = $objectifs; return $this; }

    public function getEtat(): ?string { return $this->etat; }
    public function setEtat(string $etat): static { $this->etat = $etat; return $this; }

    public function getCommentaireAdmin(): ?string { return $this->commentaire_admin; }
    public function getCommentaire_admin(): ?string { return $this->commentaire_admin; }
    public function setCommentaire_admin(?string $v): static { $this->commentaire_admin = $v; return $this; }
    public function setCommentaireAdmin(?string $v): static { $this->commentaire_admin = $v; return $this; }

    public function getDateSoumission(): ?\DateTimeInterface { return $this->date_soumission; }
    public function getDate_soumission(): ?\DateTimeInterface { return $this->date_soumission; }
    public function setDate_soumission(?\DateTimeInterface $d): static { $this->date_soumission = $d; return $this; }
    public function setDateSoumission(?\DateTimeInterface $d): static { $this->date_soumission = $d; return $this; }

    public function getNoteMoyenne(): ?float { return $this->note_moyenne; }
    public function getNote_moyenne(): ?float { return $this->note_moyenne; }
    public function setNote_moyenne(?float $v): static { $this->note_moyenne = $v; return $this; }
    public function setNoteMoyenne(?float $v): static { $this->note_moyenne = $v; return $this; }

    public function getDateEvaluation(): ?\DateTimeInterface { return $this->date_evaluation; }
    public function getDate_evaluation(): ?\DateTimeInterface { return $this->date_evaluation; }
    public function setDate_evaluation(?\DateTimeInterface $d): static { $this->date_evaluation = $d; return $this; }
    public function setDateEvaluation(?\DateTimeInterface $d): static { $this->date_evaluation = $d; return $this; }

    public function isEvaluationAutomatique(): ?bool { return $this->evaluation_automatique; }
    public function getEvaluation_automatique(): ?bool { return $this->evaluation_automatique; }
    public function setEvaluation_automatique(?bool $v): static { $this->evaluation_automatique = $v; return $this; }
    public function setEvaluationAutomatique(?bool $v): static { $this->evaluation_automatique = $v; return $this; }

    public function getBudgetEstime(): ?float { return $this->budget_estime; }
    public function setBudgetEstime(?float $v): static { $this->budget_estime = $v; return $this; }

    public function getDureeEstimee(): ?int { return $this->duree_estimee; }
    public function setDureeEstimee(?int $v): static { $this->duree_estimee = $v; return $this; }

    public function getNbMembresEquipe(): int { return $this->membres_equipes->count(); }

    public function getStatutJuridique(): ?string { return $this->statut_juridique; }
    public function setStatutJuridique(?string $v): static { $this->statut_juridique = $v; return $this; }

    public function getFinancementActuel(): ?string { return $this->financement_actuel; }
    public function setFinancementActuel(?string $v): static { $this->financement_actuel = $v; return $this; }

    public function getSiteWeb(): ?string { return $this->site_web; }
    public function setSiteWeb(?string $v): static { $this->site_web = $v; return $this; }

    public function getExperiencesAnterieures(): ?string { return $this->experiences_anterieures; }
    public function setExperiencesAnterieures(?string $v): static { $this->experiences_anterieures = $v; return $this; }

    public function getPartenairesPotentiels(): ?string { return $this->partenaires_potentiels; }
    public function setPartenairesPotentiels(?string $v): static { $this->partenaires_potentiels = $v; return $this; }

    public function getEmailContact(): ?string { return $this->email_contact; }
    public function setEmailContact(?string $v): static { $this->email_contact = $v; return $this; }

    public function getTelephoneContact(): ?string { return $this->telephone_contact; }
    public function setTelephoneContact(?string $v): static { $this->telephone_contact = $v; return $this; }

    public function getQuestion1Answer(): ?string { return $this->question1_answer; }
    public function getQuestion1_answer(): ?string { return $this->question1_answer; }
    public function setQuestion1_answer(?string $v): static { $this->question1_answer = $v; return $this; }
    public function setQuestion1Answer(?string $v): static { $this->question1_answer = $v; return $this; }

    public function getQuestion2Answer(): ?string { return $this->question2_answer; }
    public function getQuestion2_answer(): ?string { return $this->question2_answer; }
    public function setQuestion2_answer(?string $v): static { $this->question2_answer = $v; return $this; }
    public function setQuestion2Answer(?string $v): static { $this->question2_answer = $v; return $this; }

    public function getQuestion3Answer(): ?string { return $this->question3_answer; }
    public function getQuestion3_answer(): ?string { return $this->question3_answer; }
    public function setQuestion3_answer(?string $v): static { $this->question3_answer = $v; return $this; }
    public function setQuestion3Answer(?string $v): static { $this->question3_answer = $v; return $this; }

    public function getAiScoreGlobal(): ?float { return $this->ai_score_global; }
    public function setAiScoreGlobal(?float $v): static { $this->ai_score_global = $v; return $this; }

    public function getAiAnalysisData(): array|null
    {
        if ($this->ai_analysis_data === null) return null;
        return json_decode($this->ai_analysis_data, true) ?? [];
    }
    public function setAiAnalysisData(array|string|null $v): static
    {
        $this->ai_analysis_data = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
        return $this;
    }

    public function getAiAnalyzedAt(): ?\DateTimeInterface { return $this->ai_analyzed_at; }
    public function setAiAnalyzedAt(?\DateTimeInterface $v): static { $this->ai_analyzed_at = $v; return $this; }

    public function getMarketScore(): ?float { return $this->market_score; }
    public function setMarketScore(?float $v): static { $this->market_score = $v; return $this; }

    public function getMarketTrend(): ?string { return $this->market_trend; }
    public function setMarketTrend(?string $v): static { $this->market_trend = $v; return $this; }

    public function getMarketData(): array|null
    {
        if ($this->market_data === null) return null;
        return json_decode($this->market_data, true) ?? [];
    }
    public function setMarketData(array|string|null $v): static
    {
        $this->market_data = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
        return $this;
    }

    public function getMarketAnalyzedAt(): ?\DateTimeInterface { return $this->market_analyzed_at; }
    public function setMarketAnalyzedAt(\DateTimeInterface|string|null $v): static
    {
        if (is_string($v)) $v = new \DateTime($v);
        $this->market_analyzed_at = $v;
        return $this;
    }

    public function getPatentNoveltyScore(): ?float { return $this->patent_novelty_score; }
    public function setPatentNoveltyScore(?float $v): static { $this->patent_novelty_score = $v; return $this; }

    public function getPatentTotalCount(): ?int { return $this->patent_total_count; }
    public function setPatentTotalCount(?int $v): static { $this->patent_total_count = $v; return $this; }

    public function getPatentData(): array|null
    {
        if ($this->patent_data === null) return null;
        return json_decode($this->patent_data, true) ?? [];
    }
    public function setPatentData(array|string|null $v): static
    {
        $this->patent_data = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
        return $this;
    }

    public function getPatentAnalyzedAt(): ?\DateTimeInterface { return $this->patent_analyzed_at; }
    public function setPatentAnalyzedAt(\DateTimeInterface|string|null $v): static
    {
        if (is_string($v)) $v = new \DateTime($v);
        $this->patent_analyzed_at = $v;
        return $this;
    }

    // ===== COLLECTIONS =====

    public function getSprintss(): Collection { return $this->sprintss; }
    public function getEvaluationsProjets(): Collection { return $this->evaluations_projets; }
    public function getMembresEquipes(): Collection { return $this->membres_equipes; }
    public function getUserstorys(): Collection { return $this->userstorys; }
    public function getTachess(): Collection { return $this->tachess; }
    public function getUserStoriess(): Collection { return $this->user_storiess; }

    public function addSprints(Sprints $sprints): static
    {
        if (!$this->sprintss->contains($sprints)) {
            $this->sprintss[] = $sprints;
            $sprints->setId_projet($this);
        }
        return $this;
    }

    public function removeSprints(Sprints $sprints): static
    {
        if ($this->sprintss->removeElement($sprints)) {
            if ($sprints->getId_projet() === $this) $sprints->setId_projet(null);
        }
        return $this;
    }

    public function addTaches(Taches $taches): static
    {
        if (!$this->tachess->contains($taches)) {
            $this->tachess[] = $taches;
            $taches->setId_projet($this);
        }
        return $this;
    }

    public function removeTaches(Taches $taches): static
    {
        if ($this->tachess->removeElement($taches)) {
            if ($taches->getId_projet() === $this) $taches->setId_projet(null);
        }
        return $this;
    }

    public function addMembresEquipe(Membres_equipe $m): static
    {
        if (!$this->membres_equipes->contains($m)) {
            $this->membres_equipes[] = $m;
            $m->setId_projet($this);
        }
        return $this;
    }

    public function removeMembresEquipe(Membres_equipe $m): static
    {
        if ($this->membres_equipes->removeElement($m)) {
            if ($m->getId_projet() === $this) $m->setId_projet(null);
        }
        return $this;
    }

    // ===== PROGRESSION =====

    public function getProgression(): int
    {
        $total = $this->tachess->count();
        if ($total === 0) return 0;

        $done = 0;
        foreach ($this->tachess as $tache) {
            if ($tache->getStatut() === Taches::STATUT_TERMINEE) {
                $done++;
            }
        }
        return (int) round(($done / $total) * 100);
    }
}
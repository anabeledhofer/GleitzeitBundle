<?php

namespace KimaiPlugin\GleitzeitBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\GleitzeitBundle\Repository\MonatsAbschlussRepository;

//DB ENTITY

/**
 * Ein abgeschlossener Monat eines Users (KimaiV1: zeit_konto-Tabelle)
 * abgeschlossener Monat ist eingefroren
 * TODO: wieder öffnen durch Admin
 */
#[ORM\Entity(repositoryClass: MonatsAbschlussRepository::class)]
#[ORM\Table(name: 'gleitzeit_abschluss')]

// pro User und Monat darf es nur EINEN Abschluss  - unique restraint
#[ORM\UniqueConstraint(name: 'gz_abschluss_unique', columns: ['user_id', 'jahr', 'monat'])]
class MonatsAbschluss
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // user
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'integer')]
    private int $jahr;

    #[ORM\Column(type: 'integer')]
    private int $monat;

    #[ORM\Column(type: 'integer')]
    private int $uebertragSekunden = 0;

    #[ORM\Column(type: 'integer')]
    private int $uebertragReiseSekunden = 0;
 
    #[ORM\Column(type: 'integer')] // Tage in diesem Monat + laufende Summe
    private int $urlaubTage = 0;

    #[ORM\Column(type: 'integer')]
    private int $urlaubLaufend = 0;

    #[ORM\Column(type: 'integer')]
    private int $krankenstandTage = 0;

    #[ORM\Column(type: 'integer')]
    private int $krankenstandLaufend = 0;

    #[ORM\Column(type: 'integer')]
    private int $sonderurlaubTage = 0;

    #[ORM\Column(type: 'integer')]
    private int $sonderurlaubLaufend = 0;

    // Zähler für die Diäten-/HO-Statistik des Monats
    #[ORM\Column(type: 'integer')]
    private int $hoTage = 0;

    #[ORM\Column(type: 'integer')]
    private int $reiseTage = 0;

    // Ausgezahlte Stunden (wurden vor dem Speichern vom Übertrag abgezogen)
    #[ORM\Column(type: 'integer')]
    private int $auszahlungSekunden = 0;

    #[ORM\Column(type: 'integer')]
    private int $auszahlungReiseSekunden = 0;

    // Wer hat wann abgeschlossen (Nachvollziehbarkeit)
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $abgeschlossenAm;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $abgeschlossenVon;

    public function __construct(User $user, int $jahr, int $monat, User $abgeschlossenVon)
    {
        $this->user = $user;
        $this->jahr = $jahr;
        $this->monat = $monat;
        $this->abgeschlossenVon = $abgeschlossenVon;
        $this->abgeschlossenAm = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getJahr(): int { return $this->jahr; }
    public function getMonat(): int { return $this->monat; }

    public function getUebertragSekunden(): int { return $this->uebertragSekunden; }
    public function setUebertragSekunden(int $s): self { $this->uebertragSekunden = $s; return $this; }

    public function getUebertragReiseSekunden(): int { return $this->uebertragReiseSekunden; }
    public function setUebertragReiseSekunden(int $s): self { $this->uebertragReiseSekunden = $s; return $this; }

    public function getUrlaubTage(): int { return $this->urlaubTage; }
    public function setUrlaubTage(int $t): self { $this->urlaubTage = $t; return $this; }
    public function getUrlaubLaufend(): int { return $this->urlaubLaufend; }
    public function setUrlaubLaufend(int $t): self { $this->urlaubLaufend = $t; return $this; }

    public function getKrankenstandTage(): int { return $this->krankenstandTage; }
    public function setKrankenstandTage(int $t): self { $this->krankenstandTage = $t; return $this; }
    public function getKrankenstandLaufend(): int { return $this->krankenstandLaufend; }
    public function setKrankenstandLaufend(int $t): self { $this->krankenstandLaufend = $t; return $this; }

    public function getSonderurlaubTage(): int { return $this->sonderurlaubTage; }
    public function setSonderurlaubTage(int $t): self { $this->sonderurlaubTage = $t; return $this; }
    public function getSonderurlaubLaufend(): int { return $this->sonderurlaubLaufend; }
    public function setSonderurlaubLaufend(int $t): self { $this->sonderurlaubLaufend = $t; return $this; }

    public function getHoTage(): int { return $this->hoTage; }
    public function setHoTage(int $t): self { $this->hoTage = $t; return $this; }
    public function getReiseTage(): int { return $this->reiseTage; }
    public function setReiseTage(int $t): self { $this->reiseTage = $t; return $this; }

    public function getAuszahlungSekunden(): int { return $this->auszahlungSekunden; }
    public function setAuszahlungSekunden(int $s): self { $this->auszahlungSekunden = $s; return $this; }
    public function getAuszahlungReiseSekunden(): int { return $this->auszahlungReiseSekunden; }
    public function setAuszahlungReiseSekunden(int $s): self { $this->auszahlungReiseSekunden = $s; return $this; }

    public function getAbgeschlossenAm(): \DateTimeImmutable { return $this->abgeschlossenAm; }
    public function getAbgeschlossenVon(): User { return $this->abgeschlossenVon; }
}
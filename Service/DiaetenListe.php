<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\TimesheetRepository;

/**
 * Baut die Diätenliste eines Monats - eine Zeile pro Reisetag
 * Einträge mit gesetztem Land Feld  plus HO-/Reisetage-zähler.
 *
 * Baut liste auf und enthält zuordnung zu den tagen - berechnung in diätenrechner
 */
final class DiaetenListe
{
    public function __construct(
        private readonly TimesheetRepository $timesheets,
        private readonly DiaetenRechner $rechner,
    ) {
    }

    public function berechne(User $user, int $jahr, int $monat): DiaetenErgebnis
    {
        $ergebnis = new DiaetenErgebnis();
        $tz = new \DateTimeZone($user->getTimezone());

        $monatsErster = new \DateTimeImmutable(sprintf('%d-%02d-01 00:00:00', $jahr, $monat), $tz);
        $naechsterMonat = $monatsErster->modify('+1 month');

        // alle einträge des monats mit gesetztem Land-feld laden rausnehmen
        $qb = $this->timesheets->createQueryBuilder('t');
        $qb->andWhere('t.user = :user')
            ->andWhere('t.begin >= :von')
            ->andWhere('t.begin < :bis')
            ->andWhere('t.end IS NOT NULL')
            ->orderBy('t.begin', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('von', $monatsErster)
            ->setParameter('bis', $naechsterMonat);

        /** @var Timesheet[] $eintraege */
        $eintraege = $qb->getQuery()->getResult();

        // einträge pro tag gruppieren (schlüssel = tag des monats)
        /** @var array<int, Timesheet[]> $proTag */
        $proTag = [];
        foreach ($eintraege as $eintrag) {
            $land = $this->meta($eintrag, 'gz_DiaetenLand');
            if ($land === null || $land === '') {
                continue;   // kein land = keine reise = eine diäten
            }
            $beginn = \DateTimeImmutable::createFromInterface($eintrag->getBegin())->setTimezone($tz);
            $proTag[(int) $beginn->format('j')][] = $eintrag;
        }

        // je tag EINE diäten-zeile (der letzte eintrag des tages bestimmt die art/das land)
        foreach ($proTag as $eintraegeDesTages) {
            $letzter = end($eintraegeDesTages);
            $land = (string) $this->meta($letzter, 'gz_DiaetenLand');

            // Homeoffice-tag? nur zählen, keine diäten (Land == "HO")
            if ($land === GleitzeitKonfiguration::LAND_HOMEOFFICE) {
                $ergebnis->hoTage++;
                continue;
            }

            // sonst reisetag
            $ergebnis->reiseTage++;

            $art = (string) ($this->meta($letzter, 'gz_diaetenArt') ?? DiaetenRechner::ART_NORMAL);
            $ort = (string) ($this->meta($letzter, 'gz_DiaetenOrt') ?? '');

            // spanne = erster beginn bis letztes ende des tages
            $ersterBeginn = \DateTimeImmutable::createFromInterface($eintraegeDesTages[0]->getBegin())->setTimezone($tz);
            $letztesEnde = \DateTimeImmutable::createFromInterface($letzter->getEnd())->setTimezone($tz);

            $stunden = $this->rechner->stunden($art, $ersterBeginn, $letztesEnde); //diätenRechner berechnet wie viele studnen relevant sind für die diäten hier je nachdem ob normaal verbleib ab/anreise
            $satz = DiaetenKonfiguration::satz($land); //tagessatz je nach land aus dem konfig file nehmen
            $betrag = $this->rechner->diaeten($stunden, $satz); //diaetenRechner objekt liefert den ausgerechneten betrag

            //neue zeile erstellen mit den daten 
            $zeile = new DiaetenZeile();
            $zeile->datum = $ersterBeginn;
            $zeile->ort = $ort;
            $zeile->land = $land;
            $zeile->art = $art;
            $zeile->stunden = $stunden;
            $zeile->satz = $satz;
            $zeile->betrag = $betrag;

            $ergebnis->zeilen[] = $zeile;
            $ergebnis->summe += $betrag;
        }

        return $ergebnis;
    }

    /** liest ein meta-feld eines timesheets, null wenn nicht gesetzt */
    private function meta(Timesheet $t, string $name): ?string
    {
        $feld = $t->getMetaField($name);
        return $feld?->getValue();
    }
}
<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\TimesheetRepository;
use App\WorkingTime\Calculator\WorkingTimeCalculator;
use App\WorkingTime\WorkingTimeService;

/**
 * Baut die Monatstabelle: ein MonatsAuswertungZeile-Objekt pro Kalendertag.
 *
 * Etappe 1: Ist / Reise / Soll / Feiertage.
 *
 * Ersetzt den Auswertungsteil von table.php (KimaiV1)
 */
final class MonatsAuswertung
{
    public function __construct(
        private readonly TimesheetRepository $timesheets,
        private readonly FeiertagService $feiertage,
        private readonly WorkingTimeService $workingTime, //native Kimaiv - Arbeitszeit soll wird daher genommen
        private readonly PausenRegel $pausenRegel,

    ) {
    }

    /**
     * @return MonatsAuswertungZeile[] Ein Eintrag je Kalendertag des Monats
     */
    
    public function berechne(User $user, int $jahr, int $monat, int $uebertragStart = 0, int $uebertragStartReise=0): MonatGesamt
    {
        $ergebnis = new MonatGesamt();
        $ergebnis->uebertragStartSekunden = $uebertragStart;
        $ergebnis->uebertragStartReiseSekunden = $uebertragStartReise;

        // Zeitzone des Users - alle Tagesgrenzen beziehen sich darauf
        $tz = new \DateTimeZone($user->getTimezone());

        $monatsErster = new \DateTimeImmutable(sprintf('%d-%02d-01 00:00:00', $jahr, $monat), $tz);
        $naechsterMonat = $monatsErster->modify('+1 month');

        //Arbeitszeit berechner (native kimai)
        $calculator = $this->workingTime->getContractMode($user)->getCalculator($user);

        // 1) Leeres Tagesraster aufbauen 
        /** @var MonatsAuswertungZeile[] $zeilen  Schlüssel = Tag des Monats (1-31) */
        $zeilen = [];
        for ($tag = $monatsErster; $tag < $naechsterMonat; $tag = $tag->modify('+1 day')) {
            $zeile = new MonatsAuswertungZeile();
            $zeile->datum = $tag;
            $zeile->feiertag = $this->feiertage->feiertag($tag); //abfrage aus FeiertagService.php, return null wenn normaler tag
            $zeile->halberFeiertag = $this->feiertage->istHalberFeiertag($tag); //abfrage aus FeiertagService.php, return null wenn normaler tag
            $zeile->sollSekunden = $this->sollFuerTag($user, $tag, $zeile, $calculator); //Arbeits-Soll
            $zeilen[(int) $tag->format('j')] = $zeile;
        }

        // 2) Alle abgeschlossenen Einträge des Monats laden (laufende ohne Ende ignorieren )
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
        $eintraege = $qb->getQuery()->getResult(); //get alle einträge des monats von der datenbank 


        // 3) Einträge auf die Tage verteilen
        
        //Laufende Konten starten mit den Überträgen aus dem Vormonat
        $gzLaufend = $uebertragStart; //laufendes GZ konto
        $reiseLaufend = $uebertragStartReise; //laufendes Reisekonto

        //einträge auf die tage verteilen
        foreach ($eintraege as $eintrag) {
            //beginn/ende in die user-zeitzone holen, damit der eintrag
            //dem richtigen kalendertag zugeordnet wird
            $beginn = \DateTimeImmutable::createFromInterface($eintrag->getBegin())->setTimezone($tz);
            $ende = \DateTimeImmutable::createFromInterface($eintrag->getEnd())->setTimezone($tz);

            //zeile des tages holen (schlüssel = tag des monats)
            $zeile = $zeilen[(int) $beginn->format('j')];

            //erster beginn / letztes ende des tages
            if ($zeile->beginn === null || $beginn < $zeile->beginn) {
                $zeile->beginn = $beginn;
            }
            if ($zeile->ende === null || $ende > $zeile->ende) {
                $zeile->ende = $ende;
            }

            //ist-zeit summieren
            $dauer = (int) $eintrag->getDuration();
            $zeile->istSekunden += $dauer;

            //kategorie anhand der aktivität (if Activit ID gleich einer "spezial" aktivität dnn spezielle Aktion)
            match ($eintrag->getActivity()?->getId()) {
                GleitzeitKonfiguration::ACTIVITY_REISE => //wenn aktivität reise -  zeit auf reisekonto hinzufügen
                    $zeile->reiseSekunden += $dauer,
                GleitzeitKonfiguration::ACTIVITY_URLAUB => //wenn aktivität urlaub - kategorie setzen
                    $zeile->kategorie = 'URLAUB',
                GleitzeitKonfiguration::ACTIVITY_KRANKENSTAND =>
                    $zeile->kategorie = 'KRANKENSTAND',
                GleitzeitKonfiguration::ACTIVITY_SONDERURLAUB =>
                    $zeile->kategorie = 'SONDERURLAUB',
                default => null,
            };
        }
        //Tag für Tag konten berechnen: Pause, Tagessaldo, Konten fortschreiben
        foreach ($zeilen as $zeile) {

            //abwesenheits-tageszähler 
            match ($zeile->kategorie) {
                'URLAUB' => $ergebnis->urlaubTage++,
                'KRANKENSTAND' => $ergebnis->krankenstandTage++,
                'SONDERURLAUB' => $ergebnis->sonderurlaubTage++,
                default => null,
            };
            //tag ohne einträge: versäumter arbeitstag => minus soll,

            if ($zeile->beginn === null) {
                
                if ($zeile->sollSekunden > 0) { //WE/FT ohne einträge => nichts
                    $zeile->zeitkontoSekunden = -$zeile->sollSekunden;
                    $gzLaufend += $zeile->zeitkontoSekunden;
                }
            } else {
                //abwesenheitstag (urlaub/krankenstand/sonderurlaub) = saldoneutral - ist wird aufs soll gesetzt egal welche dauer im eintrag steht
                if ($zeile->kategorie !== null) {
                    $zeile->istSekunden = $zeile->sollSekunden;
                    $zeile->reiseSekunden = 0;
                    $zeile->pauseSekunden = 0;
                    $zeile->zeitkontoSekunden = 0;      //tagessaldo 0
                    $zeile->reisekontoSekunden = 0;
                    //laufende konten unverändert in die zeile schreiben
                    $zeile->zeitkontoLaufendSekunden = $gzLaufend;
                    $zeile->reisekontoLaufendSekunden = $reiseLaufend;
                    continue;   //pausenregel, 12h-regel etc. überspringen
                }
                //pausenregel 
                $spanne = $zeile->ende->getTimestamp() - $zeile->beginn->getTimestamp();
                $zeile->pauseSekunden = $spanne - $zeile->istSekunden; //echte lücke
                $abzug = $this->pausenRegel->abzug(
                    $zeile->istSekunden,
                    $spanne,
                    $zeile->kategorie !== null
                );
                if ($abzug > 0) {
                    $zeile->istSekunden -= $abzug;
                    $zeile->pauseSekunden += $abzug; //anzeige inkl. zwangspause
                }

                //reise nie größer als das ist (KimaiV1-regel)
                if ($zeile->reiseSekunden > $zeile->istSekunden) {
                    $zeile->reiseSekunden = $zeile->istSekunden;
                }

                //tagessaldo aufteilen: reiseanteil ins reisekonto (höchstens der saldo), rest ins gleitzeitkonto
                $saldo = $zeile->istSekunden - $zeile->sollSekunden;
                if ($zeile->reiseSekunden > 0 && $saldo > 0) {
                    //nur der positive überschuss, höchstens die reisezeit
                    $zeile->reisekontoSekunden = min($saldo, $zeile->reiseSekunden);
                    $zeile->zeitkontoSekunden = $saldo - $zeile->reisekontoSekunden;
                } else {
                    $zeile->reisekontoSekunden = 0;
                    $zeile->zeitkontoSekunden = $saldo;
                }

                //max 12 h - sonst markieren
                $netto = $zeile->istSekunden - $zeile->reiseSekunden;
                if ($netto > GleitzeitKonfiguration::UEBERSCHREITUNG_AB_SEKUNDEN) {
                    $zeile->ueberschreitung = true;
                    $ergebnis->ueberschreitung = true;
                }

                $gzLaufend += $zeile->zeitkontoSekunden;
                $reiseLaufend += $zeile->reisekontoSekunden;
            }

            //laufende kontostände in die zeile schreiben
            $zeile->zeitkontoLaufendSekunden = $gzLaufend;
            $zeile->reisekontoLaufendSekunden = $reiseLaufend;
        }

        //4 ) Korrektur (wie KimaiV1)
        // Negatives GZ-Konto zuerst aus dem Reisekonto decken 
        if ($gzLaufend < 0) {
            $reiseLaufend += $gzLaufend;
            $gzLaufend = 0;
        }
        //  danach ein negatives Reisekonto zurück ins GZ-Konto
        if ($reiseLaufend < 0) {
            $gzLaufend += $reiseLaufend;
            $reiseLaufend = 0;
        }

        $ergebnis->gleitzeitEndeSekunden = $gzLaufend;
        $ergebnis->reisekontoEndeSekunden = $reiseLaufend;
        $ergebnis->zeilen = $zeilen;

        return $ergebnis;
    }
    

    /**
     * Soll-Zeit (Sek) für einen Tag:
     * Feiertag => 0
     * sonst: Wert aus dem Kimai-Arbeitsvertrag für diesen Wochentag 
     * halber Feiertag => halbes Soll / 2 (am WE ist das eh 0)
     */
    private function sollFuerTag(User $user, \DateTimeImmutable $tag, MonatsAuswertungZeile $zeile, WorkingTimeCalculator $calculator): int
    {

        // Vor dem ersten Arbeitstag (Eintritt) gibt es kein Soll - (kimai_usr.eintritt feld in KimaiV1)
        $eintritt = $user->getWorkStartingDay();
        if ($eintritt !== null && $tag < $eintritt) {
            return 0;
        }

        //wenn feiertag Soll = 0 
        if ($zeile->feiertag !== null) {
            return 0;
        }


        $soll = $calculator->getWorkHoursForDay($tag); //native kimai liefert die Soll-Arbeitsstunden für den tag 

        //wenn halber feiertag soll / 2 ( auf sek runden - intdivide statt /2)
        if ($zeile->halberFeiertag) {
            $soll = intdiv($soll, 2);
        }

        return $soll;
    }
}
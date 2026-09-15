<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

use App\Entity\User;
use KimaiPlugin\GleitzeitBundle\Entity\MonatsAbschluss;
use KimaiPlugin\GleitzeitBundle\Repository\MonatsAbschlussRepository;
use KimaiPlugin\GleitzeitBundle\Service\GleitzeitKonfiguration;
use KimaiPlugin\GleitzeitBundle\Service\MonatsAuswertung;

/**
 * Der Monatsabschluss: prüfen ob erlaubt, rechnen, einfrieren.
 *
 * Regeln 
 *  - nur möglich, wenn der Vormonat abgeschlossen ist
 *  - nur möglich, wenn der Monat nicht schon abgeschlossen ist
 *  - nicht möglich bei 12h-Überschreitung im Monat 
 *  - Auszahlung nur wenn Konto positiv = maximal der Kontostand - wird vor dem Speichern vom Übertrag abgezogen
 */
final class AbschlussService
{
    /**
     * erster mOnat im system hat keinen vormonats abschluss
     * TODO: start übertrage?
     */


    public function __construct(
        private readonly MonatsAbschlussRepository $repository,
        private readonly MonatsAuswertung $auswertung,
        private readonly UrlaubsRechner $urlaubsRechner,
    ) {
    }

    /**
     * Prüft alle Abschluss-Voraussetzungen.
     *
     * @return string[] Liste der Hindernisse leer = Abschluss erlaubt
     */
    public function pruefe(User $user, int $jahr, int $monat, MonatGesamt $ergebnis): array
    {
        $fehler = [];

        //schon abgeschlossen?
        if ($this->repository->findAbschluss($user, $jahr, $monat) !== null) {
            $fehler[] = 'Dieser Monat ist bereits abgeschlossen.';
        }

        //Vormonat abgeschlossen? (außer bei erstem monat)
        $istStartMonat = ($jahr === GleitzeitKonfiguration::START_JAHR && $monat === GleitzeitKonfiguration::START_MONAT);
        if (!$istStartMonat && $this->repository->findVormonat($user, $jahr, $monat) === null) {
            $fehler[] = 'Der Vormonat ist noch nicht abgeschlossen.';
        }

        // 12h-Regel
        if ($ergebnis->ueberschreitung) {
            $fehler[] = 'Es gibt Tage mit mehr als 12 Stunden Arbeitszeit - bitte zuerst korrigieren.';
        }
        //urlaub berechnen und prüfen ob eh nicht im minus
        $urlaubRest = $this->urlaubsRechner->rest($user, $jahr, $monat, $ergebnis->urlaubTage);
        if($urlaubRest <0){
            $fehler[] = sprintf(
                'Urlaubsanspruch überschritten (%s Tage im Minus) - bitte korrigieren',
                abs($urlaubRest)
            );
        } 


        return $fehler;
    }

    /**
     * Schließt den Monat ab und liefert den gespeicherten Abschluss.
     *
     * @param int $auszahlungSekunden       auszuzahlende GZ-Stunden (0 = keine)
     * @param int $auszahlungReiseSekunden  auszuzahlende Reise-Stunden (0 = keine)
     *
     * @throws \RuntimeException wenn eine Voraussetzung verletzt ist
     */
    public function schliesseAb(
        User $user,
        int $jahr,
        int $monat,
        User $abgeschlossenVon,
        int $auszahlungSekunden = 0,
        int $auszahlungReiseSekunden = 0
    ): MonatsAbschluss {
        // Startwerte aus dem Vormonat 
        $vormonat = $this->repository->findVormonat($user, $jahr, $monat);
        $laufend = $this->berechneMitVormonat($user, $jahr, $monat, $vormonat);

        // Voraussetzungen für abschluss prüfen (doppelte prüfung im controller)
        $fehler = $this->pruefe($user, $jahr, $monat, $laufend);
        if (\count($fehler) > 0) {
            throw new \RuntimeException(implode(' ', $fehler));
        }

        // Auszahlung validieren: nur positive Beträge, maximal der Kontostand
        if ($auszahlungSekunden < 0 || $auszahlungReiseSekunden < 0) {
            throw new \RuntimeException('Auszahlung kann nicht negativ sein.');
        }
        if ($auszahlungSekunden > 0 && $auszahlungSekunden > $laufend->gleitzeitEndeSekunden) {
            throw new \RuntimeException('Auszahlung ist höher als das Gleitzeitkonto.');
        }
        if ($auszahlungReiseSekunden > 0 && $auszahlungReiseSekunden > $laufend->reisekontoEndeSekunden) {
            throw new \RuntimeException('Auszahlung ist höher als das Reisekonto.');
        }


        //Abwesenheits-Laufwerte fortschreiben 
        //laufend = Stand Vormonat + dieser Monat
        $abschluss = new MonatsAbschluss($user, $jahr, $monat, $abgeschlossenVon);
        $abschluss
            // Überträge = Endstände nach Korrektur MINUS Auszahlung
            ->setUebertragSekunden($laufend->gleitzeitEndeSekunden - $auszahlungSekunden)
            ->setUebertragReiseSekunden($laufend->reisekontoEndeSekunden - $auszahlungReiseSekunden)
            ->setAuszahlungSekunden($auszahlungSekunden)
            ->setAuszahlungReiseSekunden($auszahlungReiseSekunden)
            ->setUrlaubTage($laufend->urlaubTage)
            ->setUrlaubLaufend(($vormonat?->getUrlaubLaufend() ?? 0) + $laufend->urlaubTage)
            ->setKrankenstandTage($laufend->krankenstandTage)
            ->setKrankenstandLaufend(($vormonat?->getKrankenstandLaufend() ?? 0) + $laufend->krankenstandTage)
            ->setSonderurlaubTage($laufend->sonderurlaubTage)
            ->setSonderurlaubLaufend(($vormonat?->getSonderurlaubLaufend() ?? 0) + $laufend->sonderurlaubTage);
        
            //TODO: HO Reiesetage

        $this->repository->speichern($abschluss);

        return $abschluss;
    }

    
    //Rechnet den Monat mit den Startwerten aus dem Vormonats-Abschluss
 
    public function berechneMitVormonat(
        User $user,
        int $jahr,
        int $monat,
        ?MonatsAbschluss $vormonat = null
    ): MonatGesamt {
        $vormonat ??= $this->repository->findVormonat($user, $jahr, $monat);

        return $this->auswertung->berechne(
            $user,
            $jahr,
            $monat,
            $vormonat?->getUebertragSekunden() ?? 0,
            $vormonat?->getUebertragReiseSekunden() ?? 0
        );
    }
}
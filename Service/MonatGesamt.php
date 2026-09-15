<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/**
 * Gesamtergebnis der Monatsauswertung: alle Tageszeilen plus die
 * Monatswerte, die später beim Abschluss gespeichert werden.
 */
final class MonatGesamt
{
    /** @var MonatsAuswertungZeile[] */
    public array $zeilen = [];

    //Startwerte = Überträge aus dem Vormonat (TODO aus Monatsabschluss)
    public int $uebertragStartSekunden = 0;
    public int $uebertragStartReiseSekunden = 0;

    //Endstände nach der Korrekturzeile - das sind die neuen Überträge */
    public int $gleitzeitEndeSekunden = 0;
    public int $reisekontoEndeSekunden = 0;

    //Tageszähler für die Zusammenfassung
    public int $urlaubTage = 0;
    public int $krankenstandTage = 0;
    public int $sonderurlaubTage = 0;

    //rue sobald ein Tag über 12 h => Abschluss-Sperre (TODO)*/
    public bool $ueberschreitung = false;


}
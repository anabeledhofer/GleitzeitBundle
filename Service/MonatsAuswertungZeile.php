<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/**
 * Eine Zeile der Monatsauswertung = ein Kalendertag.
 * 1 Tag = 1 Objekt (leer, wird von MA befüllt)
 * Anzeige durch table.html.twig
 */
final class MonatsAuswertungZeile
{
    public \DateTimeImmutable $datum;

    // Beginn /  Ende des Tages (null = keine Einträge)
    public ?\DateTimeImmutable $beginn = null;
    public ?\DateTimeImmutable $ende = null;

    //arbeitsstudnen gesamt
    public int $istSekunden = 0;
    //reise gesamt
    public int $reiseSekunden = 0;

    // Soll-Sekunden laut Arbeitsvertrag native Kimai
    public int $sollSekunden = 0;

    // Feiertagsname oder null halber Feiertag als eigenes Flag (Feiertagservice)
    public ?string $feiertag = null;
    public bool $halberFeiertag = false;

    //URLAUB / KRANKENSTAND / SONDERURLAUB oder null (normale Arbeit)
    public ?string $kategorie = null;
     /** krankenstand-stunden (stundenweise, z.B. arztbesuch) - nur für die statistik */
    public int $krankenstandStundeSekunden = 0;

    public int $pauseSekunden = 0;
    public int $zeitkontoSekunden = 0;          // Tagessaldo Gleitzeit
    public int $zeitkontoLaufendSekunden = 0;   // laufendes GZ-Konto
    public int $reisekontoSekunden = 0;         // Tagessaldo Reise
    public int $reisekontoLaufendSekunden = 0;  // laufendes Reisekonto
    public bool $ueberschreitung = false;       // 12h-Regel
    

}
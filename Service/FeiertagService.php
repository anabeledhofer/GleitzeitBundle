<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/**
 *  Feiertage - Formeld aus der alten feiertag()-Funktion (KimaiV1)
 *
 */


final class FeiertagService
{
    //Liefert den Feiertagsnamen für ein Datum oder null.
    // Ganze Feiertage => Arbeitssoll= 0 
    //Halbe feiertage seperat mit istHalberFeiertag funktion


    public function feiertag(\DateTimeInterface $datum): ?string
    {
        $jahr = (int) $datum->format('Y');
        $ostern = $this->osterSonntag($jahr);

        //--------------
        // bewegliche
        //nicht fixe feiertage werden mit den tagen die seit ostern vergangen sind berechnet
        $tageSeitOstern = (int) $ostern->diff($datum)->format('%r%a');


        $beweglich = [
            0  => 'Ostersonntag',
            1  => 'Ostermontag',
            39 => 'Christi Himmelfahrt',
            49 => 'Pfingstsonntag',
            50 => 'Pfingstmontag',
            60 => 'Fronleichnam',
        ];
        if (isset($beweglich[$tageSeitOstern])) {
            return $beweglich[$tageSeitOstern];
        }

        //-----------------
        //Statische Feiertage (Monat-Tag)
        $statisch = [
            '01-01' => 'Neujahr',
            '01-06' => 'Heilige 3 Könige',
            '05-01' => 'Staatsfeiertag',
            '08-15' => 'Maria Himmelfahrt',
            '10-26' => 'Nationalfeiertag',
            '11-01' => 'Allerheiligen',
            '12-08' => 'Maria Empfängnis',
            '12-25' => 'Christtag',
            '12-26' => 'Stephanitag',
        ];

        return $statisch[$datum->format('m-d')] ?? null;
    }

    
    //Halber Feiertag (24.12. / 31.12.)? => halbes Tages-Soll
    public function istHalberFeiertag(\DateTimeInterface $datum): bool
    {
        return \in_array($datum->format('m-d'), GleitzeitKonfiguration::HALBE_FEIERTAGE, true); //ist halber feiertag? ja nein
    }

    
    //Ostersonntag nach Formel - gültig für alle Jahre
    private function osterSonntag(int $jahr): \DateTimeImmutable
    {
        $a = $jahr % 19;
        $b = intdiv($jahr, 100);
        $c = $jahr % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $monat = intdiv($h + $l - 7 * $m + 114, 31);
        $tag = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%d-%02d-%02d', $jahr, $monat, $tag));
    }
}
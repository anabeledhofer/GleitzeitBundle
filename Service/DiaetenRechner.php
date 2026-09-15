<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/**
 * Diäten Berechnung
 *
 * Pro Reisetag Diäten pro Stunde (Zwölftel Regel) je nach Art (normal, anreise, abreise, verbleib)
 */
final class DiaetenRechner
{
    // die werte des Art-felds (gz_diaetenArt)
    public const ART_NORMAL = 'normal';
    public const ART_ANREISE = 'anreise';
    public const ART_ABREISE = 'abreise';
    public const ART_VERBLEIB = 'verbleib';

    /**
     * Stundenspanne eines Reisetags je nach Art :
     * Normal:   Beginn -> Ende (normale Arbeitszeit am tag)
     * Anreise:  Beginn -> 23:59 (rest des tages - auch nicht arbeitszeit)
     * Abreise:  00:00  -> Ende  (ab mitternacht bis Ende Arbeit bzw Reise)
     * Verbleib: ganzer tag = 24h
     *
     */

    public function stunden(
        string $art,
        \DateTimeImmutable $beginn,
        \DateTimeImmutable $ende
    ): float {
        // Verbleib = immer voller tag
        if ($art === self::ART_VERBLEIB) {
            return 24.0;
        }

        $tag = $beginn->format('Y-m-d');

        // je nach art die grenzen setzen
        $von = match ($art) {
            self::ART_ABREISE => new \DateTimeImmutable($tag . ' 00:00:00', $beginn->getTimezone()),
            default           => $beginn,
        };
        $bis = match ($art) {
            self::ART_ANREISE => new \DateTimeImmutable($tag . ' 23:59:00', $ende->getTimezone()),
            default           => $ende,
        };

        $stunden = ($bis->getTimestamp() - $von->getTimestamp()) / 3600;

        // kimaiV1 -verhalten: nicht-positive spanne => 24h
        return $stunden <= 0 ? 24.0 : $stunden;
    }

    /**
     * Zwölftel-Regel (wie KimaiV1: Zeile 1689-1690):
     *  >= 12h        => voller Satz
     *  >  3h         => Satz/12 * aufgerundete Stunden
     *  sonst (<= 3h) => 0
     */
    public function diaeten(float $stunden, float $satz): float
    {
        if ($stunden >= 12) {
            return $satz;
        }
        if ($stunden > 3) {
            return round($satz / 12 * ceil($stunden), 2);
        }

        return 0.0; //sonst = unter 3 h keine diäten 
    }
}
<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/**
 * Diätensätze je Land (voller Tagessatz in Euro).
 * Ländercode = ISO wie im Land-Meta-feld (z.B. "AT").
 * Neue Länder/Sätze müssen hier eintragenm werden
 */
final class DiaetenKonfiguration
{
    public const SAETZE = [
        'AT' => 26.40,
        'DE' => 27.90,
        // weitere Länder hier ...
    ];

    /** voller Tagessatz für ein Land, 0.0 wenn unbekannt*/
    public static function satz(string $land): float
    {
        return self::SAETZE[$land] ?? 0.0;
    }
}
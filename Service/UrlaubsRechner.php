<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

use App\Entity\User;
use KimaiPlugin\GleitzeitBundle\Repository\MonatsAbschlussRepository;

/**
 * resturlaub nach ARBEITSJAHR-logik:
 *
 *   rest = startguthaben
 *        + anspruch × (eintrittsjubiläen seit systemstart bis stichtag)
 *        - verbrauchte tage (abschlüsse + ggf. laufender monat)
 *
 * resturlaub verfällt nie (firmenregel). korrekturen über
 * startguthaben (einmalig) oder anspruch (dauerhaft).
 * ohne eintrittsdatum: anspruch wird je 1.1. gutgeschrieben (fallback).
 */
final class UrlaubsRechner
{
    public function __construct(private readonly MonatsAbschlussRepository $repository)
    {
    }

    /**
     * @param int $liveUrlaubTage urlaubstage des angezeigten monats, falls  noch offen (abgeschlossene in der summe)
     */
    public function rest(User $user, int $jahr, int $monat, int $liveUrlaubTage = 0): float
    {
        $startguthaben = (float) $user->getPreferenceValue(
            GleitzeitKonfiguration::PREF_URLAUB_STARTGUTHABEN, 0
        );
        $anspruch = (float) $user->getPreferenceValue(
            GleitzeitKonfiguration::PREF_URLAUBSANSPRUCH,
            GleitzeitKonfiguration::URLAUB_DEFAULT
        );

        $gutschriften = $anspruch * $this->jubilaeen($user, $jahr, $monat);
        $verbraucht = $this->repository->summeUrlaubGesamt($user) + $liveUrlaubTage;

        return $startguthaben + $gutschriften - $verbraucht;
    }

    /**
     * zählt die anspruchs-gutschriften zwischen systemstart und dem
     * ende des stichtag-monats. gutschrift-tag = monat/tag des eintritt ohne eintrittsdatum der 1.1. (kalenderjahr-fallback).
     */
    private function jubilaeen(User $user, int $jahr, int $monat): int
    {
        $eintritt = $user->getWorkStartingDay();
        $gMonat = $eintritt ? (int) $eintritt->format('n') : 1;
        $gTag = $eintritt ? (int) $eintritt->format('j') : 1;

        $systemstart = new \DateTimeImmutable(sprintf(
            '%d-%02d-01', GleitzeitKonfiguration::START_JAHR, GleitzeitKonfiguration::START_MONAT
        ));
        // stichtag = letzter tag des angezeigten monats
        $stichtag = (new \DateTimeImmutable(sprintf('%d-%02d-01', $jahr, $monat)))
            ->modify('last day of this month');

        $anzahl = 0;
        // kandidaten-jahre durchgehen und zählen, welche eintritts termine ins fenster (systemstart, stichtag] fallen
        for ($j = (int) $systemstart->format('Y'); $j <= (int) $stichtag->format('Y'); $j++) {
            $termin = \DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $j, $gMonat, $gTag))
                ?: new \DateTimeImmutable(sprintf('%d-01-01', $j)); // 29.2.-absicherung
            if ($termin >= $systemstart && $termin <= $stichtag) {
                $anzahl++;
            }
        }

        return $anzahl;
    }
}
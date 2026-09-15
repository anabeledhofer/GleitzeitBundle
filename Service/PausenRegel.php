<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/**
 * Die automatische Pausenregel aus dem KV:
 * Wer mehr als 6h gearbeitet hat und keine echte Pause von mindestens
 * 30 Minuten gemacht hat, dem werden 30 Minuten abgezogen.
 *
 * Entspricht dem Altsystem (dort: date('H:i:s',$arbeit_ist_tag) > "06:01"
 * und Lücke < "00:30" => minus 1800 Sekunden).
 */
final class PausenRegel
{
    /**
     * Liefert die abzuziehenden Sekunden (0 oder 1800).
     *
     * @param int  $istSekunden      gearbeitete Sekunden des Tages
     * @param int  $spanneSekunden   Ende minus Beginn des Tages
     * @param bool $istAbwesenheit   Urlaub/Krankenstand/Sonderurlaub => keine Pausenregel
     */
    public function abzug(int $istSekunden, int $spanneSekunden, bool $istAbwesenheit): int
    {
        // Abwesenheitstage sind ausgenommen (wie im Altsystem evtID 29/30/31)
        if ($istAbwesenheit) {
            return 0;
        }

        // Regel greift erst über 6h Arbeitszeit
        if ($istSekunden <= GleitzeitKonfiguration::PAUSE_AB_SEKUNDEN) {
            return 0;
        }

        // Tatsächliche Lücke am Tag = Anwesenheitsspanne minus Arbeitszeit.
        // War die echte Pause schon >= 30 min, wird nichts abgezogen.
        $echtePause = $spanneSekunden - $istSekunden;
        if ($echtePause >= GleitzeitKonfiguration::PAUSE_ABZUG_SEKUNDEN) {
            return 0;
        }

        return GleitzeitKonfiguration::PAUSE_ABZUG_SEKUNDEN;
    }
}

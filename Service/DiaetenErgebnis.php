<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/** gesamtergebnis der diätenliste eines monats 
 * speichert alle zeilen und die summe 
*/

final class DiaetenErgebnis
{
    /** @var DiaetenZeile[] */
    public array $zeilen = [];
    public float $summe = 0.0;
    public int $hoTage = 0;
    public int $reiseTage = 0;
}
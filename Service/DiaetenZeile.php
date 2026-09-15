<?php

namespace KimaiPlugin\GleitzeitBundle\Service;

/** eine zeile der diätenliste = ein reisetag = 1 Objekt
 * 
*/
final class DiaetenZeile
{
    public \DateTimeImmutable $datum;
    public string $ort = '';
    public string $land = '';
    public string $art = '';
    public float $stunden = 0.0;
    public float $satz = 0.0;
    public float $betrag = 0.0;
}
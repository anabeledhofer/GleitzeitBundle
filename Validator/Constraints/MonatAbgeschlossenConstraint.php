<?php

namespace KimaiPlugin\GleitzeitBundle\Validator\Constraints;

use App\Validator\Constraints\TimesheetConstraint;

/**
 * markiert timesheets, die in einem abgeschlossenen monat liegen.
 * die eigentliche prüfung macht der zugehörige Validator.
 */
final class MonatAbgeschlossenConstraint extends TimesheetConstraint
{
    public const MONAT_ABGESCHLOSSEN = 'gleitzeit-monat-abgeschlossen-01';

    protected const ERROR_NAMES = [
        self::MONAT_ABGESCHLOSSEN => 'Monat bereits abgeschlossen.',
    ];

    public string $message = 'Der Monat %monat% wurde bereits abgeschlossen - kann nicht mehr geändert werden.';

    // CLASS_CONSTRAINT: die prüfung gilt für das ganze timesheet-objekt,
    // nicht für ein einzelnes feld
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
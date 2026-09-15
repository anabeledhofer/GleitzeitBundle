<?php

namespace KimaiPlugin\GleitzeitBundle\Validator\Constraints;

use App\Entity\Timesheet;
use KimaiPlugin\GleitzeitBundle\Repository\MonatsAbschlussRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * verhindert das anlegen/ändern von zeiteinträgen in monaten,
 * die im gleitzeit-modul bereits abgeschlossen sind.
 *
 * die sperre ist automatisch wieder weg, sobald ein admin den
 * monat wieder öffnet (= abschluss-datensatz gelöscht wird).
 */
final class MonatAbgeschlossenConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly MonatsAbschlussRepository $abschluesse)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {

        if (!$constraint instanceof MonatAbgeschlossenConstraint) {
            return;
        }
        if (!$value instanceof Timesheet) {
            return;
        }

        $user = $value->getUser();
        $begin = $value->getBegin();
        if ($user === null || $begin === null) {
            return;   // unvollständig - andere validatoren meckern schon
        }

        $jahr = (int) $begin->format('Y');
        $monat = (int) $begin->format('n');

        if ($this->abschluesse->findAbschluss($user, $jahr, $monat) === null) {
            return;   // monat ist offen: alles erlaubt
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('%monat%', sprintf('%02d/%d', $monat, $jahr))
            ->setCode(MonatAbgeschlossenConstraint::MONAT_ABGESCHLOSSEN)
            ->addViolation();
    }
}
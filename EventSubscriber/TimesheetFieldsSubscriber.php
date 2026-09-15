<?php

//Dieser Eventsubscriber fügt Felder Land, Ort und Kommentar-Art unten zu Timesheet Eintrag hinzu 

namespace KimaiPlugin\GleitzeitBundle\EventSubscriber;

use App\Entity\EntityWithMetaFields;
use App\Entity\TimesheetMeta;
use App\Event\TimesheetMetaDefinitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;      
use Symfony\Component\Validator\Constraints\Length;


//Kimai feuert TimesheetMetaDefinitionEvent sobald irgendwo ein Timesheet-Formular gebaut oder ein Eintrag geladen wird
//=> dann wird das custom field angehängt
//speichern/laden der fields übernimmt Kimai selbst (in dem fall in kimai2_timesheet_meta DB Tabelle)


class TimesheetFieldsSubscriber implements EventSubscriberInterface
{

    //bestimmt auf welche Events wir hören 
    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetMetaDefinitionEvent::class => ['loadTimesheetMeta', 200], //[Funktionsname der aufgerufen wird ,prio 200 (bestimmt die reihenfolge bei mehreren subscribern)] 
        ];
    }

    //die Funktion wird aufgerufen sobald das TimesheetMetaDefinitionEvent feuert = Dispatcher
    public function loadTimesheetMeta(TimesheetMetaDefinitionEvent $event): void
    {
        $this->prepareEntity($event->getEntity()); 
    }

    //dispatcher ruft prepare Entitty auf - der aktuelle Timesheet eintrag wird übergeben an den die custom fields angehängt werden sollen 
    //   (ausgelagert wegen übersicht )
    private function prepareEntity(EntityWithMetaFields $entity): void
    {
        //Feld Land(Diaeten) hinzufügen
        $land = (new TimesheetMeta())
            //DB Schlüssel (gz präfix)
            ->setName('gz_DiaetenLand')
            //Beschriftung im Formular
            ->setLabel('Land (Diaeten)')
            //TextType = einfaches Textfeld
            ->setType(TextType::class)
            //maximale Länge 100 zeichen 
            ->addConstraint(new Length(['max' => 3]))
            //true= feld erscheint in Datentabellen/Exports als auswählbare Spalte nicht nur im Formular
            ->setIsVisible(true);

        //Fehld "Land (Diäeten)" an den Timesheet Eintrag anhängen 
        $entity->setMetaField($land);

        //Fehld "Ort" definieren 

        $ort = (new TimesheetMeta())
            //DB Schlüssel (gz präfix)
            ->setName('gz_DiaetenOrt')
            //Beschriftung im Formular
            ->setLabel('Ort')
            //TextType = einfaches Textfeld
            ->setType(TextType::class)
            //maximale Länge 100 zeichen 
            ->addConstraint(new Length(['max' => 100]))
            //true= feld erscheint in Datentabellen/Exports als auswählbare Spalte nicht nur im Formular
            ->setIsVisible(true);


        //Fehld "Ort" an den Timesheet Eintrag anhängen 
        $entity->setMetaField($ort);


        

 
        $kommentarArt = (new TimesheetMeta())
            //DB Schlüssel (gz präfix)
            ->setName('gz_diaetenArt')
            //Beschriftung im Formular
            ->setLabel('Reise-Art')
            //ChoiceType = dropdown
            ->setType(ChoiceType::class)
            //Optionen: Anzeige => gespeicherter wert
            ->setoptions([
                'choices' => [
                'Normal' => 'normal',
                'Anreise' => 'anreise',
                'Abreise' => 'abreise',
                'Verbleib' => 'verbleib'
                ],
                'required' => false    
            ]) 
            //true= feld erscheint in Datentabellen/Exports als auswählbare Spalte nicht nur im Formular
            ->setIsVisible(true);

        //Feld "Kommentar Art" an den Timesheet Eintrag anhängen 
        $entity->setMetaField($kommentarArt);
 
       
    }
}
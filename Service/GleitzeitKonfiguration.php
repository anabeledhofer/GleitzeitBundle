<?php

namespace KimaiPlugin\GleitzeitBundle\Service;


//Konfig file alle Variablen hardocded



final class GleitzeitKonfiguration
{
    //Start jahr und monat ab wann die gleitzeit zählt -  erster monat der abgeschlossen werden kann
    public const START_JAHR = 2026;
    public const START_MONAT = 7;

    //Tätigkeit IDs
    public const ACTIVITY_REISE = 10;         
    public const ACTIVITY_URLAUB = 12;        
    public const ACTIVITY_KRANKENSTAND = 13;  
    public const ACTIVITY_SONDERURLAUB = 14;  
    public const ACTIVITY_KRANKENSTAND_STUNDE = 19; 

    //Standard-Sollzeit in Stunden pro Tag, falls beim User nichts hinterlegt ist
    public const DEFAULT_SOLL_STUNDEN = 7.7;

    //Pausenregel - nach 6 h automatische pause in sek
    public const PAUSE_AB_SEKUNDEN = 6 * 3600;

    // Pausenregel - 30 min in sek pause automatisch nach 6 h arbeit
    public const PAUSE_ABZUG_SEKUNDEN = 1800;

    //Maximale Arbeitszeit in sekunden
    public const UEBERSCHREITUNG_AB_SEKUNDEN = 12 * 3600;

    //Halbe feiertage (Weihnachten und silvester)
    public const HALBE_FEIERTAGE = ['12-24', '12-31'];

    // Homeoffice Code
    public const LAND_HOMEOFFICE = 'HO';


    // name fürs Urlaubsanspruch speicher feld 
    public const PREF_URLAUBSANSPRUCH = 'gz_urlaubsanspruch';
    public const PREF_URLAUB_STARTGUTHABEN = 'gz_urlaub_startguthaben';
    

    //standard urlaubsanspruch
    public const URLAUB_DEFAULT = 25;


    
}
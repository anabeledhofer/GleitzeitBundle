<?php
namespace KimaiPlugin\GleitzeitBundle\Controller;
use App\Controller\AbstractController;
use KimaiPlugin\GleitzeitBundle\Repository\MonatsAbschlussRepository;
use KimaiPlugin\GleitzeitBundle\Service\AbschlussService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\GleitzeitBundle\Service\UrlaubsRechner;   
use KimaiPlugin\GleitzeitBundle\Service\DiaetenListe;

/**
 * zeigt Tabelle mit Gleitzeit-Monatsauswertung + Diäten liste
 * controlled Zeitliste und Diätenlsite
 */
//url /gleitzeit
#[Route(path: '/gleitzeit')]
//Berechtigung in der Rollenübersicht
#[IsGranted('view_own_timesheet')]
final class ZeitlisteController extends AbstractController
{
    //nimmt die werte aus den MonatsAuswertung funktionen und ruft das html twig mit den werten befüllt auf 
    #[Route(path: '/zeitliste', name: 'gleitzeit_zeitliste', methods: ['GET'])]
    public function zeitliste(
        Request $request,
        AbschlussService $abschlussService,
        MonatsAbschlussRepository $abschluesse,
        UserRepository $userRepository,
        UrlaubsRechner $urlaubsRechner
    ): Response {
        $user = $this->getUser();

        //speichert berechtigung ob eingeloggter user andere timesheets anschauen darf (admin)
        $viewOtherTimesheets = $this->isGranted('view_other_timesheet');

        $anzeigeUser = $user;
        $userId = (int) $request->query->get('user',0);

       //wenn user berechtigung hat dann aanzeigen
        if ($viewOtherTimesheets && $userId>0) {
            $anzeigeUser = $userRepository-> find($userId) ?? $user;
        }


        // Monat/Jahr aus der URL (?jahr=2026&monat=7), Default = aktueller Monat
        $jahr = (int) $request->query->get('jahr', date('Y'));
        $monat = (int) $request->query->get('monat', date('n'));
        if ($monat < 1 || $monat > 12) {
            $monat = (int) date('n');
        }

        // Auswertung rechnen - liefert MonatGesamt-Objekt (Zeilen + Endstände + Tageszähler)
        // Startüberträge kommen automatisch aus dem Vormonats-Abschluss
        $ergebnis = $abschlussService->berechneMitVormonat($anzeigeUser, $jahr, $monat);

        

        // null = offen, 1 = abgeschlossen
        $abschluss = $abschluesse->findAbschluss($anzeigeUser, $jahr, $monat);

        //urlaubsanspruch aus dem profil (unsere eigene preference)
        $urlaubAnspruch = (float) $anzeigeUser->getPreferenceValue(
            \KimaiPlugin\GleitzeitBundle\Service\GleitzeitKonfiguration::PREF_URLAUBSANSPRUCH,
            \KimaiPlugin\GleitzeitBundle\Service\GleitzeitKonfiguration::URLAUB_DEFAULT
        );

        // resturlaub nach arbeitsjahr-logik; laufenden monat nur live dazuzählen, wenn er noch offen ist
        $urlaubRest = $urlaubsRechner->rest(
            $anzeigeUser, $jahr, $monat,
            $abschluss === null ? $ergebnis->urlaubTage : 0
        );


        //offen -> hindernisliste für den abschluss ermitteln
        //leere hindernissliste -> abschluss-button wird angezeigt, sonst die gründe
        $hindernisse = $abschluss === null
            ? $abschlussService->pruefe($anzeigeUser, $jahr, $monat, $ergebnis)
            : [];

        //Vor-/Zurück-Navigation
        $aktuell = \DateTimeImmutable::createFromFormat('Y-n-j', "$jahr-$monat-1");
        return $this->render('@Gleitzeit/zeitliste.html.twig', [
            'alleUser' => $viewOtherTimesheets
                ? $userRepository->findBy(['enabled' => true], ['username' => 'ASC'])
                : [$anzeigeUser],            'anzeigeUser' => $anzeigeUser,  
            // untergrenze fürs jahr-dropdown (systemstart aus der konfig)
            'startJahr' => \KimaiPlugin\GleitzeitBundle\Service\GleitzeitKonfiguration::START_JAHR,
            // Gesamtobjekt fürs Template (Korrekturzeile, Endstände, Zähler)
            'ergebnis' => $ergebnis,
            // die Tageszeilen als Array 
            'zeilen' => $ergebnis->zeilen,
            // null oder MonatsAbschluss 
            'abschluss' => $abschluss,
            // string[] - abschluss hindernisse
            'hindernisse' => $hindernisse,
            'jahr' => $jahr,
            'monat' => $monat,
            'vorher' => $aktuell->modify('-1 month'),
            'nachher' => $aktuell->modify('+1 month'),
            'urlaubRest' => $urlaubRest
            
        ]);
    }

    //nimmt das abschluss-formular entgegen lässt den AbschlussService validieren + speichern und leitet zurück zur zeitliste
    #[Route(path: '/zeitliste/abschliessen', name: 'gleitzeit_abschliessen', methods: ['POST'])]
    public function abschliessen(Request $request, AbschlussService $abschlussService, UserRepository $userRepository): Response
    {
        $user = $this->getUser();

        $jahr = (int) $request->request->get('jahr');
        $monat = (int) $request->request->get('monat');

        //falls admin für wen anderen abschließt
        $zielUser = $user;
        $userId = (int) $request->request->get('user', 0);
        if ($userId > 0 && $userId !== $user->getId()) {
            // fremden monat abschließen nur mit berechtigung
            if (!$this->isGranted('view_other_timesheet')) {
                $this->flashError('Keine Berechtigung für diesen Benutzer.');
                return $this->redirectToRoute('gleitzeit_zeitliste', ['jahr' => $jahr, 'monat' => $monat]);
            }
            $zielUser = $userRepository->find($userId) ?? $user;
        }


        // CSRF-schutz: formular-token prüfen - damit man keine anderen user abfragen kann
        if (!$this->isCsrfTokenValid('gleitzeit.abschluss', $request->request->get('_token'))) {
            $this->flashError('Ungültiges Formular-Token, bitte erneut versuchen.');
            return $this->redirectToRoute('gleitzeit_zeitliste', ['jahr' => $jahr, 'monat' => $monat]);
        }

        // auszahlungen von h in s 
        $auszahlung = (float) $request->request->get('auszahlung', 0);
        $auszahlungReise = (float) $request->request->get('auszahlungReise', 0);

        try {
            $abschlussService->schliesseAb(
                $zielUser,
                $jahr,
                $monat,
                $user,   // abgeschlossen von = der user selbst TODO: Admin Abschlüsse 
                (int) round($auszahlung * 3600),
                (int) round($auszahlungReise * 3600)
            );
            $this->flashSuccess(sprintf('Monat %02d/%d wurde abgeschlossen.', $monat, $jahr));
        } catch (\RuntimeException $e) {
            // validierungsfehler aus dem service als meldung anzeigen
            $this->flashError($e->getMessage());
        }

        return $this->redirectToRoute('gleitzeit_zeitliste', ['jahr' => $jahr, 'monat' => $monat, 'user' => $zielUser->getID()]);
    }

    //löscht den abschluss eines monats => monat ist wieder offen und = neu abschließbar 
    #[Route(path: '/zeitliste/wiederoeffnen', name: 'gleitzeit_wiederoeffnen', methods: ['POST'])]
    //TODO: echte berechtigung in der rollenübersicht 
    #[IsGranted('ROLE_ADMIN')]
    public function wiederoeffnen(
        Request $request,
        MonatsAbschlussRepository $abschluesse,
        UserRepository $userRepository
    ): Response {
        $jahr = (int) $request->request->get('jahr');
        $monat = (int) $request->request->get('monat');
        $userId = (int) $request->request->get('user');

        // CSRF-schutz wie beim abschließen
        if (!$this->isCsrfTokenValid('gleitzeit.wiederoeffnen', $request->request->get('_token'))) {
            $this->flashError('Ungültiges Formular-Token, bitte erneut versuchen.');
            return $this->redirectToRoute('gleitzeit_zeitliste', ['jahr' => $jahr, 'monat' => $monat, 'user' => $userId]);
        }

        $zielUser = $userRepository->find($userId);
        $abschluss = $zielUser ? $abschluesse->findAbschluss($zielUser, $jahr, $monat) : null;

        if ($abschluss === null) {
            $this->flashError('Kein Abschluss für diesen Monat gefunden.');
        } else {
            $abschluesse->loeschen($abschluss);
            $this->flashSuccess(sprintf('Monat %02d/%d von %s wurde wieder geöffnet.', $monat, $jahr, $zielUser->getDisplayName()));
        }

        return $this->redirectToRoute('gleitzeit_zeitliste', ['jahr' => $jahr, 'monat' => $monat, 'user' => $userId]);
    }



    //------------------------------------DIÄTEN-------------------------------------
    #[Route(path: '/diaeten', name: 'gleitzeit_diaeten', methods: ['GET'])]
    public function diaeten(
        Request $request,
        DiaetenListe $diaetenListe,
        UserRepository $userRepository
    ): Response {
        $user = $this->getUser();

        $jahr = (int) $request->query->get('jahr', date('Y'));
        $monat = (int) $request->query->get('monat', date('n'));
        if ($monat < 1 || $monat > 12) {
            $monat = (int) date('n');
        }

        // gleiche user-auswahl-logik wie in der zeitliste
        $viewOther = $this->isGranted('view_other_timesheet');
        $anzeigeUser = $user;
        $userId = (int) $request->query->get('user', 0);
        if ($viewOther && $userId > 0) {
            $anzeigeUser = $userRepository->find($userId) ?? $user;
        }

        $ergebnis = $diaetenListe->berechne($anzeigeUser, $jahr, $monat);
        $aktuell = \DateTimeImmutable::createFromFormat('Y-n-j', "$jahr-$monat-1");

        return $this->render('@Gleitzeit/diaeten.html.twig', [
            'ergebnis' => $ergebnis,
            'anzeigeUser' => $anzeigeUser,
            'alleUser' => $viewOther ? $userRepository->findBy(['enabled' => true], ['username' => 'ASC']) : [$anzeigeUser],
            'jahr' => $jahr,
            'monat' => $monat,
            'vorher' => $aktuell->modify('-1 month'),
            'nachher' => $aktuell->modify('+1 month'),
        ]);
    }
}
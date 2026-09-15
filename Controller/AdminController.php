<?php

namespace KimaiPlugin\GleitzeitBundle\Controller;

use App\Controller\AbstractController;
use App\Repository\UserRepository;
use KimaiPlugin\GleitzeitBundle\Repository\MonatsAbschlussRepository;
use KimaiPlugin\GleitzeitBundle\Service\GleitzeitKonfiguration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * admin-übersicht: alle user mit konten, urlaub und arbeitsvertrag.
 * bearbeiten von urlaubsanspruch + h/tag in der tabelle.
 */
#[Route(path: '/gleitzeit/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route(path: '', name: 'gleitzeit_admin', methods: ['GET'])]
    public function uebersicht(UserRepository $userRepository, MonatsAbschlussRepository $abschluesse): Response
    {
        $jahr = (int) date('Y');
        $zeilen = [];

        foreach ($userRepository->findBy(['enabled' => true], ['username' => 'ASC']) as $user) {
            // letzter abschluss = aktuelle kontostände
            $letzter = $abschluesse->findLetzten($user);

            // sollstunden/tag aus dem vertrag (mo als referenz, in stunden)
            $stundenProTag = round(((int) $user->getPreferenceValue('work_monday', 0)) / 3600, 2);

            $anspruch = (float) $user->getPreferenceValue(GleitzeitKonfiguration::PREF_URLAUBSANSPRUCH, GleitzeitKonfiguration::URLAUB_DEFAULT);
            $verbraucht = $abschluesse->summeUrlaubImJahr($user, $jahr, 12);

            $zeilen[] = [
                'user' => $user,
                'abschluss' => $letzter,                       // null = noch nie abgeschlossen
                'stundenProTag' => $stundenProTag,
                'anspruch' => $anspruch,
                'urlaubRest' => $anspruch - $verbraucht,
                // eintritt + startwerte für die editierbaren felder
                'eintritt' => $user->getWorkStartingDay(),
                'startguthaben' => (float) $user->getPreferenceValue(GleitzeitKonfiguration::PREF_URLAUB_STARTGUTHABEN, 0),
                'startAbschluss' => $abschluesse->findAbschluss($user, ...$this->startMonatVor()), //startmonatvor - da liegt der startübertrag (startabschluss)
            ];
        }

        return $this->render('@Gleitzeit/admin.html.twig', ['zeilen' => $zeilen]);
    }


    //speichert urlaubsanspruch + sollstunden für EINEN user (zeilen-formular)
    #[Route(path: '/speichern', name: 'gleitzeit_admin_speichern', methods: ['POST'])]
    public function speichern(Request $request, UserRepository $userRepository, MonatsAbschlussRepository $abschluesse): Response
    {
        if (!$this->isCsrfTokenValid('gleitzeit.admin', $request->request->get('_token'))) {
            $this->flashError('Ungültiges Formular-Token.');
            return $this->redirectToRoute('gleitzeit_admin');
        }

        $user = $userRepository->find((int) $request->request->get('user'));
        if ($user === null) {
            $this->flashError('Benutzer nicht gefunden.');
            return $this->redirectToRoute('gleitzeit_admin');
        }

        // sollstunden/tag => in sekunden, für Mo-Fr setzen (Sa/So bleiben 0
        //gleiche preferences wie in native kimai vertrags-tab auch 
        $stunden = (float) $request->request->get('stundenProTag', 0);
        $sekunden = (int) round($stunden * 3600);
        foreach (['work_monday', 'work_tuesday', 'work_wednesday', 'work_thursday', 'work_friday'] as $pref) {
            $user->setPreferenceValue($pref, $sekunden);
        }

        // urlaubsanspruch (eigene preference)
        $user->setPreferenceValue(
            GleitzeitKonfiguration::PREF_URLAUBSANSPRUCH,
            (float) $request->request->get('anspruch', GleitzeitKonfiguration::URLAUB_DEFAULT)
        );
        // eintrittsdatum (leer lassen = nicht ändern)
        $eintrittInput = $request->request->get('eintritt', '');
        if ($eintrittInput !== '') {
            $user->setWorkStartingDay(new \DateTimeImmutable($eintrittInput));
        }

        // urlaubs-startguthaben (resturlaub zum systemstart)
        $user->setPreferenceValue(
            GleitzeitKonfiguration::PREF_URLAUB_STARTGUTHABEN,
            (float) $request->request->get('startguthaben', 0)
        );

        // start-überträge GZ/reise => als "startabschluss" im monat VOR
        // dem systemstart speichern, damit die normale vormonats-mechanik
        // greift (keine sonderlogik in der auswertung nötig)
        [$sj, $sm] = $this->startMonatVor();
        $startGz = (int) round((float) $request->request->get('startGz', 0) * 3600);
        $startReise = (int) round((float) $request->request->get('startReise', 0) * 3600);

        $start = $abschluesse->findAbschluss($user, $sj, $sm);
        if ($start === null) {
            // noch keiner da: anlegen (abgeschlossenVon = der admin)
            $start = new \KimaiPlugin\GleitzeitBundle\Entity\MonatsAbschluss($user, $sj, $sm, $this->getUser());
        }
        // upsert: werte setzen bzw. überschreiben. ACHTUNG: bereits
        // abgeschlossene echte monate rechnen dadurch NICHT neu
        // (grundsatz "abgeschlossen ist eingefroren") - startwerte
        // also VOR den ersten echten abschlüssen final setzen!
        $start->setUebertragSekunden($startGz);
        $start->setUebertragReiseSekunden($startReise);
        $abschluesse->speichern($start);

        $userRepository->saveUser($user);
        $this->flashSuccess(sprintf('%s gespeichert.', $user->getDisplayName()));

        return $this->redirectToRoute('gleitzeit_admin');
    }
    // monat vor dem systemstart als [jahr, monat] - dort liegt der startabschluss
    private function startMonatVor(): array
    {
        $j = GleitzeitKonfiguration::START_JAHR;
        $m = GleitzeitKonfiguration::START_MONAT;
        return $m === 1 ? [$j - 1, 12] : [$j, $m - 1];
    }
}
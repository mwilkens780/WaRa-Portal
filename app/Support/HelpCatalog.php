<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Inhalte von "Hilfe & FAQ": Anleitungen und häufige Fragen für die
 * Einführung des Portals. Jede Anleitung hat eine Zielgruppe – die Hilfe
 * zeigt zuerst, was für den angemeldeten Benutzer passt ("Für mich"),
 * auf Wunsch alles. "public" = auch ohne Anmeldung (Hilfe zur Anmeldung).
 *
 * Links im Text: [[route.name|Beschriftung]] – wird zu einem Link, wenn die
 * Route existiert, sonst bleibt nur die Beschriftung stehen.
 * Menüpunkte und Knöpfe: <span class="ui">…</span>.
 */
class HelpCatalog
{
    public const CATEGORIES = [
        'start'     => 'Erste Schritte',
        'kalender'  => 'Kalender & Einladungen',
        'sportler'  => 'Für Sportlerinnen und Sportler',
        'eltern'    => 'Für Eltern',
        'trainer'   => 'Für Trainerinnen und Trainer',
        'kampfrichter' => 'Kampfrichter',
        'verwaltung'   => 'Vorstand & Geschäftsstelle',
        'datenschutz'  => 'Datenschutz & Sicherheit',
    ];

    /** Zielgruppen: wer eine Anleitung "für mich" angezeigt bekommt */
    public static function audience(string $key, ?User $u): bool
    {
        if (!$u) return false;
        return match ($key) {
            'alle'         => true,
            'sportler'     => $u->role === 'schwimmer',
            'eltern'       => $u->role === 'elternteil' || $u->children()->exists(),
            'trainer'      => $u->canAccess('training', 'training_all'),
            'gruppen'      => $u->canAccess('training_groups', 'training_groups_all'),
            'wettkampf'    => $u->canAccess('competitions'),
            'termine'      => !empty(CalendarEvent::creatableTypesFor($u)),
            'kampfrichter' => $u->hasAnyRole('kampfrichter') || $u->canAccess('officials_own', 'officials'),
            'obmann'       => $u->canAccess('official_requests'),
            'verwaltung'   => $u->canAccess('users_all', 'training_groups_all', 'officials'),
            'benutzer'     => $u->canAccess('users_lite', 'users_all'),
            default        => false,
        };
    }

    /**
     * @return list<array{key: string, title: string, category: string, audience: string, public: bool, keywords: string, html: string}>
     */
    public static function articles(): array
    {
        return array_map(fn($a) => $a + ['public' => false, 'keywords' => ''], [

            // ── Erste Schritte ──────────────────────────────────────────────
            [
                'key' => 'erste-anmeldung', 'title' => 'Erste Anmeldung und Passwort', 'category' => 'start',
                'audience' => 'alle', 'public' => true,
                'keywords' => 'login einloggen willkommensmail einrichtungslink passwort setzen benutzername e-mail',
                'html' => <<<'HTML'
<p>Du bekommst vom Verein eine <strong>Willkommensmail</strong> mit dem Knopf <span class="ui">Passwort jetzt setzen</span>.</p>
<ol>
  <li>Knopf in der Mail antippen – es öffnet sich das Portal.</li>
  <li>Ein eigenes Passwort zweimal eingeben: <strong>mindestens 10 Zeichen, mit Buchstaben und Zahlen</strong>.</li>
  <li>Danach mit deiner <strong>E-Mail-Adresse</strong> (das ist dein Benutzername) und dem neuen Passwort anmelden.</li>
</ol>
<p class="tip">Der Link in der Willkommensmail gilt <strong>14 Tage</strong> und funktioniert nur einmal. Ist er abgelaufen, fordere auf der Anmeldeseite über <span class="ui">Passwort vergessen?</span> einfach einen neuen an.</p>
<p>Mit <span class="ui">Angemeldet bleiben</span> musst du dich auf deinem eigenen Gerät nicht jedes Mal neu anmelden. Auf fremden Geräten den Haken bitte weglassen und dich am Ende über das Konto-Menü abmelden.</p>
HTML,
            ],
            [
                'key' => 'passwort-vergessen', 'title' => 'Passwort vergessen oder ändern', 'category' => 'start',
                'audience' => 'alle', 'public' => true,
                'keywords' => 'passwort zurücksetzen reset link abgelaufen ändern',
                'html' => <<<'HTML'
<h4>Passwort vergessen</h4>
<ol>
  <li>Auf der Anmeldeseite <span class="ui">Passwort vergessen?</span> wählen.</li>
  <li>Deine E-Mail-Adresse eingeben – du bekommst eine Mail mit einem Link.</li>
  <li>Link öffnen und ein neues Passwort setzen. Der Link gilt <strong>60 Minuten</strong>.</li>
</ol>
<p>Keine Mail bekommen? Bitte im Spam-Ordner nachsehen und prüfen, ob du die E-Mail-Adresse verwendest, die beim Verein hinterlegt ist.</p>
<h4>Passwort ändern</h4>
<p>Angemeldet: [[profile.index|Mein Profil]] → <span class="ui">Passwort ändern</span>. Nach jeder Änderung bekommst du zur Sicherheit eine Bestätigungsmail.</p>
HTML,
            ],
            [
                'key' => 'web-app', 'title' => 'Portal als App auf den Startbildschirm legen', 'category' => 'start',
                'audience' => 'alle', 'public' => true,
                'keywords' => 'app installieren homescreen startbildschirm icon symbol iphone android verknüpfung pwa',
                'html' => <<<'HTML'
<p>Das Portal braucht keine App aus dem App Store. Du kannst es aber wie eine App mit dem Vereinslogo auf deinen Startbildschirm legen – es öffnet sich dann ohne Adresszeile, und du bleibst angemeldet.</p>
<h4>iPhone und iPad (Safari)</h4>
<ol>
  <li>Das Portal in <strong>Safari</strong> öffnen und anmelden.</li>
  <li>Unten (iPad: oben) auf das <strong>Teilen-Symbol</strong> tippen (Quadrat mit Pfeil nach oben).</li>
  <li>Nach unten scrollen und <span class="ui">Zum Home-Bildschirm</span> wählen.</li>
  <li>Name „WaRa“ übernehmen und <span class="ui">Hinzufügen</span> tippen.</li>
</ol>
<p class="tip">In anderen Browsern auf dem iPhone (z. B. Chrome) heißt es ebenfalls <span class="ui">Zum Home-Bildschirm</span> im Teilen-Menü. Fehlt der Eintrag, das Portal kurz in Safari öffnen.</p>
<h4>Android (Chrome)</h4>
<ol>
  <li>Das Portal in <strong>Chrome</strong> öffnen und anmelden.</li>
  <li>Oben rechts auf die <strong>drei Punkte</strong> tippen.</li>
  <li><span class="ui">App installieren</span> oder <span class="ui">Zum Startbildschirm hinzufügen</span> wählen und bestätigen.</li>
</ol>
<p>In Samsung Internet: Menü (drei Striche) → <span class="ui">Seite hinzufügen zu</span> → <span class="ui">Startbildschirm</span>.</p>
<h4>Computer (Chrome oder Edge)</h4>
<p>In der Adresszeile rechts erscheint ein kleines Symbol <span class="ui">App installieren</span> (Bildschirm mit Pfeil). Alternativ im Menü: Chrome → <span class="ui">Streamen, speichern und teilen</span> → <span class="ui">Seite als App installieren</span>; Edge → <span class="ui">Apps</span> → <span class="ui">Diese Website als App installieren</span>.</p>
<p class="warn">Die App ist eine Verknüpfung zum Portal: Ohne Internet funktioniert sie nicht, und Benachrichtigungen kommen weiterhin per E-Mail.</p>
HTML,
            ],
            [
                'key' => 'orientierung', 'title' => 'Aufbau des Portals: Menü und Startseite', 'category' => 'start',
                'audience' => 'alle',
                'keywords' => 'menü navigation dashboard startseite seitenleiste handy unten leiste',
                'html' => <<<'HTML'
<p>Nach der Anmeldung landest du auf deiner <strong>Startseite (Dashboard)</strong>. Sie zeigt, was gerade ansteht: nächste Trainings, offene Rückmeldungen, Einladungen und Hinweise.</p>
<ul>
  <li><strong>Computer:</strong> Das Menü steht links. Es zeigt nur die Bereiche, die du nutzen darfst.</li>
  <li><strong>Handy:</strong> Das Menü öffnest du über das Symbol oben links. Sportler und Eltern haben unten zusätzlich eine Leiste mit den wichtigsten Seiten.</li>
  <li><strong>Konto-Menü</strong> (unten im Menü): <span class="ui">Mein Profil</span>, <span class="ui">Gesundheitsdaten</span>, <span class="ui">Hilfe &amp; FAQ</span>, <span class="ui">Support</span>, <span class="ui">Passwort ändern</span> und <span class="ui">Abmelden</span>.</li>
</ul>
<p>Welche Bereiche du siehst, hängt von deiner Rolle im Verein ab (Sportler, Eltern, Trainer, Kampfrichter, Vorstand …). Fehlt dir etwas, das du brauchst, sprich bitte deinen Trainer oder die Geschäftsstelle an.</p>
HTML,
            ],
            [
                'key' => 'profil', 'title' => 'Profil, E-Mail-Benachrichtigungen und Einwilligungen', 'category' => 'start',
                'audience' => 'alle',
                'keywords' => 'profil adresse telefon mail benachrichtigungen abbestellen einwilligung ernährung sportmedizin',
                'html' => <<<'HTML'
<p>Unter [[profile.index|Mein Profil]] pflegst du deine Kontaktdaten (zweite E-Mail-Adresse, Telefon, Mobil, Anschrift). Bitte halte sie aktuell – Trainer und Verein erreichen dich darüber.</p>
<h4>E-Mail-Benachrichtigungen</h4>
<p>Im Abschnitt <span class="ui">E-Mail-Benachrichtigungen</span> wählst du, welche Mails du bekommen möchtest – z. B. Wettkampf-Einladungen, Erinnerungen, Trainingsausfall, Einladungen zu Terminen oder Rückmeldungen zu deinen Zielen. Mails zu Konto und Zugang (z. B. Passwort) kommen immer.</p>
<h4>Einwilligungen</h4>
<p>Für <strong>Ernährungsberatung</strong> und <strong>sportmedizinische Untersuchungen</strong> entscheidest du selbst, ob Ergebnisse im Portal abgelegt und von den zuständigen Personen eingesehen werden dürfen. Du kannst jede Einwilligung jederzeit widerrufen. Bei Minderjährigen entscheiden die Eltern – sie sehen dafür im eigenen Profil einen Abschnitt je Kind.</p>
HTML,
            ],

            // ── Kalender & Einladungen ──────────────────────────────────────
            [
                'key' => 'kalender', 'title' => 'Den Kalender nutzen', 'category' => 'kalender',
                'audience' => 'alle',
                'keywords' => 'kalender woche monat liste übersicht saison termine training wettkampf meldeschluss',
                'html' => <<<'HTML'
<p>Im <span class="ui">Kalender</span> stehen alle Termine, die dich betreffen: deine Trainingseinheiten, Wettkämpfe (mit Meldeschluss), Vereinstermine, Ehrungen, Meldefristen und Einladungen. Eltern sehen auch die Termine ihrer Kinder.</p>
<ul>
  <li>Oben wechselst du zwischen <span class="ui">Woche</span>, <span class="ui">Monat</span>, <span class="ui">Übersicht</span> (ganze Saison) und <span class="ui">Liste</span>.</li>
  <li>Ein Klick auf einen Eintrag öffnet die Details – mit <span class="ui">Öffnen</span> geht es zur Einheit, zum Wettkampf oder zum Termin.</li>
  <li>Ferien und Feiertage sind farbig hinterlegt.</li>
</ul>
<p class="tip">Du siehst nur Einheiten, denen du zugewiesen bist, und Termine deiner Gruppe. Fehlt etwas, bist du vermutlich (noch) nicht der richtigen Trainingsgruppe zugeordnet – sprich deinen Trainer an.</p>
HTML,
            ],
            [
                'key' => 'kalender-abo', 'title' => 'Termine in Outlook, iPhone- oder Google-Kalender übernehmen', 'category' => 'kalender',
                'audience' => 'alle', 'public' => true,
                'keywords' => 'abo abonnieren webcal ics outlook google android iphone ical synchronisieren export',
                'html' => <<<'HTML'
<p>Du kannst alle deine Portal-Termine in den Kalender auf deinem Handy oder Computer holen. Sie werden dort automatisch aktuell gehalten.</p>
<h4>Abo einrichten</h4>
<ol>
  <li>Im Portal: <span class="ui">Kalender</span> → <span class="ui">Abonnieren</span>.</li>
  <li><strong>iPhone/iPad:</strong> <span class="ui">Auf diesem Gerät abonnieren</span> antippen und bestätigen. Klappt das nicht: Link kopieren, dann Einstellungen → Apps → Kalender → Kalender-Accounts → Account hinzufügen → Andere → <span class="ui">Kalenderabo hinzufügen</span>.</li>
  <li><strong>Outlook:</strong> Link kopieren → im Outlook-Kalender <span class="ui">Kalender hinzufügen</span> → <span class="ui">Aus dem Internet abonnieren</span> → Link einfügen.</li>
  <li><strong>Android/Google:</strong> Link kopieren → am Computer calendar.google.com → bei <span class="ui">Weitere Kalender</span> auf „+“ → <span class="ui">Per URL</span>. Danach erscheint er auch in der Google-Kalender-App.</li>
</ol>
<h4>Gut zu wissen</h4>
<ul>
  <li>Das Abo ist eine Einbahnstraße: Änderungen machst du im Portal, nicht im Handy-Kalender.</li>
  <li>Abgesagte Einheiten bleiben als „ABGESAGT“ stehen.</li>
  <li>Wie oft aktualisiert wird, bestimmt dein Kalender: iPhone einstellbar (z. B. stündlich), Outlook alle paar Stunden, <strong>Google nur alle 8 bis 24 Stunden</strong>. Schneller auf Android: die kostenlose App <strong>ICSx⁵</strong>.</li>
  <li>Einzelne Termine übernimmst du auch ohne Abo: im Termin auf <span class="ui">In meinen Kalender</span>. Einladungs-Mails enthalten den Termin außerdem als Anhang.</li>
</ul>
<p class="warn">Der Abo-Link ist persönlich: Wer ihn hat, sieht deine Termine. Hast du ihn versehentlich weitergegeben, erzeuge auf der Abo-Seite einen neuen – der alte funktioniert dann nicht mehr.</p>
HTML,
            ],
            [
                'key' => 'einladungen', 'title' => 'Einladungen beantworten (Zusagen, Absagen)', 'category' => 'kalender',
                'audience' => 'alle',
                'keywords' => 'einladung zusage absage vielleicht rsvp elternabend team-event trainingslager vorstandssitzung agenda protokoll',
                'html' => <<<'HTML'
<p>Zu Elternabenden, Team-Events, Trainingslagern oder Sitzungen bekommst du eine Einladung per E-Mail und im Portal unter <span class="ui">Einladungen</span>.</p>
<ol>
  <li>Einladung öffnen (aus der Mail oder unter <span class="ui">Einladungen</span>).</li>
  <li><span class="ui">Zusagen</span>, <span class="ui">Vielleicht</span> oder <span class="ui">Absagen</span> wählen – optional mit Kommentar.</li>
</ol>
<ul>
  <li>Deine Antwort kannst du bis zum Anmeldeschluss ändern. Zwei Tage vorher erinnert das Portal alle, die noch nicht geantwortet haben.</li>
  <li>Agenda, Anhänge und Protokolle stehen auf der Terminseite – sichtbar nur für Eingeladene.</li>
  <li>Eltern antworten für ihre minderjährigen Kinder.</li>
  <li>Gäste ohne Portal-Zugang bekommen einen persönlichen Link und können ohne Anmeldung antworten.</li>
</ul>
HTML,
            ],
            [
                'key' => 'termine-anlegen', 'title' => 'Termine anlegen und einladen', 'category' => 'kalender',
                'audience' => 'termine',
                'keywords' => 'termin anlegen vereinstermin ehrung meldefrist gruppen für wen einladen gäste anhänge protokoll',
                'html' => <<<'HTML'
<p>Im <span class="ui">Kalender</span> über <span class="ui">Termin</span> (oder per Klick auf einen Tag) legst du einen neuen Termin an. Welche Arten du anlegen darfst, hängt von deiner Rolle ab.</p>
<h4>Termine ohne Einladung</h4>
<p>Vereinstermin, Ehrung, Meldefrist, Sonstiges. Unter <span class="ui">Für wen?</span> wählst du Gruppen aus – ohne Auswahl sehen alle Mitglieder den Termin, mit Auswahl nur die gewählten Gruppen (Sportler, Eltern, Trainer) und der Vorstand.</p>
<h4>Termine mit Einladung</h4>
<p>Vorstandssitzung, Elternabend, Team-Event. Nach dem Speichern wählst du die Eingeladenen (Gruppen, einzelne Personen, Gäste per E-Mail). Optional mit Anmeldung, Anmeldeschluss und Platzbegrenzung. Auf der Terminseite pflegst du Agenda, Anhänge und Protokolle; mit <span class="ui">Erinnerung senden</span> erinnerst du alle ohne Rückmeldung.</p>
HTML,
            ],

            // ── Sportler ────────────────────────────────────────────────────
            [
                'key' => 'mein-training', 'title' => 'Mein Training: absagen, anmelden, Selbsteinschätzung', 'category' => 'sportler',
                'audience' => 'sportler',
                'keywords' => 'training absagen abmelden krank serie dauerhafte absage gasttraining anmeldung selbsteinschätzung tagebuch trainingsplan',
                'html' => <<<'HTML'
<p>Unter <span class="ui">Mein Training</span> siehst du deine kommenden und vergangenen Einheiten.</p>
<h4>Absagen</h4>
<p>Kannst du nicht kommen, tippe bei der Einheit auf <span class="ui">Absagen</span> und schreib optional einen Grund dazu. Das geht bis zum Ende der Trainingszeit – auch noch am selben Tag. Eine Absage kannst du wieder zurücknehmen.</p>
<p>Fällt eine ganze Serie für dich weg (z. B. ein fester Wochentag passt nicht mehr), nutze <span class="ui">Dauerhafte Absage</span> bzw. <span class="ui">Ausblenden</span> bei der Serie.</p>
<h4>Zusätzliche Trainings</h4>
<p>Unter <span class="ui">Trainingsplanung</span> stehen offene Einheiten zum Anmelden und – wenn frei – <span class="ui">Gasttraining verfügbar</span> in anderen Gruppen. Ist eine Einheit voll, steht dort <span class="ui">Ausgebucht</span>.</p>
<h4>Nach dem Training</h4>
<p>Mit der <span class="ui">Selbsteinschätzung</span> sagst du deinem Trainer, wie anstrengend das Training für dich war. Den Trainingsplan einer Einheit siehst du nach dem Training in den Details der Einheit.</p>
<p class="tip">Fällt ein Training aus, bekommst du eine E-Mail (abbestellbar im Profil) und die Einheit steht im Kalender als ausgefallen.</p>
HTML,
            ],
            [
                'key' => 'wettkaempfe-sportler', 'title' => 'Wettkämpfe: Einladung beantworten, Bus und Fahrgemeinschaft', 'category' => 'sportler',
                'audience' => 'sportler',
                'keywords' => 'wettkampf anmeldung zusage absage übernachtung essen bus fahrgemeinschaft mitfahren meldeschluss ergebnisse',
                'html' => <<<'HTML'
<p>Möchte der Trainer dich für einen Wettkampf melden, bekommst du eine <strong>Einladung</strong> per Mail. Antworten kannst du unter <span class="ui">Meine Wettkämpfe</span> oder direkt auf der Startseite:</p>
<ol>
  <li><span class="ui">Zusagen</span> oder <span class="ui">Absagen</span> wählen, optional eine Notiz.</li>
  <li>Falls angeboten: Übernachtung mit dem Team, gemeinsames Essen, Platz im Bus.</li>
  <li>Bei Fahrgemeinschaften kannst du einen freien Platz bei anderen Familien buchen.</li>
</ol>
<p>Nach dem Wettkampf stehen deine Ergebnisse unter <span class="ui">Meine Wettkämpfe</span> und neue Bestzeiten automatisch unter <span class="ui">Meine Bestzeiten</span>.</p>
HTML,
            ],
            [
                'key' => 'bestzeiten', 'title' => 'Meine Bestzeiten, Rekorde und Punkte', 'category' => 'sportler',
                'audience' => 'sportler',
                'keywords' => 'bestzeit zeiten saison kurzbahn langbahn punkte rudolph wa rekorde bestenliste',
                'html' => <<<'HTML'
<p><span class="ui">Meine Bestzeiten</span> zeigt deine besten Zeiten je Strecke – aus Wettkämpfen und aus dem Training – getrennt nach Kurz- und Langbahn, für alle Zeit, das Jahr und die Saison.</p>
<ul>
  <li>Zu Wettkampfzeiten werden die Punkte nach World Aquatics angezeigt, für Langbahn-Zeiten optional auch die Rudolph-Punkte.</li>
  <li>Vereinsrekorde und Bestenlisten findest du unter <span class="ui">Rekorde &amp; Bestenlisten</span>.</li>
</ul>
<p>Fehlt ein Ergebnis oder ist eines falsch, gib deinem Trainer Bescheid.</p>
HTML,
            ],
            [
                'key' => 'ziele', 'title' => 'Ziele setzen und für den Trainer freigeben', 'category' => 'sportler',
                'audience' => 'sportler',
                'keywords' => 'ziele zielzeit qualifikation freies ziel freigabe privat trainer kommentar fortschritt leistungskriterien',
                'html' => <<<'HTML'
<p>Unter <span class="ui">Meine Ziele</span> legst du für die Saison eigene Ziele an: ein <strong>Zeit-Ziel</strong> (z. B. 100 m Freistil unter 1:05), eine <strong>Qualifikation</strong> oder ein <strong>freies Ziel</strong>.</p>
<ul>
  <li>Mit <span class="ui">Für meine Trainer sichtbar</span> entscheidest du, ob dein Trainer das Ziel sehen und kommentieren darf. Ohne Freigabe sieht er nur, <em>dass</em> du Ziele geplant hast – nicht welche. Das kannst du an jedem Ziel jederzeit ändern (<span class="ui">verbergen</span> / <span class="ui">für Trainer freigeben</span>).</li>
  <li>Zeit-Ziele erkennt das Portal automatisch als erreicht, sobald dein Trainer eine passende Trainingszeit einträgt. Sonst bewertest du das Ziel selbst.</li>
  <li>Freie Ziele bekommen einen Fortschrittsregler; am Ende bewertest du jedes Ziel selbst.</li>
</ul>
<h4>Leistungskriterien</h4>
<p>Unter <span class="ui">Leistungskriterien</span> stehen die Anforderungen deiner Trainingsgruppe. Du schätzt dich selbst ein, der Trainer bewertet ebenfalls.</p>
HTML,
            ],
            [
                'key' => 'motto', 'title' => 'Motto der Woche', 'category' => 'sportler',
                'audience' => 'sportler',
                'keywords' => 'motto woche spruch ki vorschlag',
                'html' => <<<'HTML'
<p>Ist das Motto der Woche für deine Gruppe aktiviert, bist du reihum dran, ein Motto für die Trainingswoche zu formulieren. Unter <span class="ui">Motto der Woche</span> siehst du das aktuelle Motto und deine Wochen; du kannst dir auch einen Vorschlag erzeugen lassen und ihn anpassen. Vor deiner Woche bekommst du eine Erinnerung.</p>
HTML,
            ],

            // ── Eltern ──────────────────────────────────────────────────────
            [
                'key' => 'eltern', 'title' => 'Eltern: Training, Wettkämpfe und Zeiten der Kinder', 'category' => 'eltern',
                'audience' => 'eltern',
                'keywords' => 'kind kinder eltern absagen anmeldung wettkampf zeiten mehrere kinder',
                'html' => <<<'HTML'
<p>Im Menü hat jedes Kind einen eigenen Abschnitt mit <span class="ui">Training</span>, <span class="ui">Wettkämpfe</span>, <span class="ui">Anmeldungen</span> und <span class="ui">Zeiten</span>. Auf dem Handy führt die untere Leiste direkt dorthin.</p>
<ul>
  <li><strong>Training absagen:</strong> beim Kind unter <span class="ui">Training</span> → <span class="ui">Absagen</span> (bis zum Ende der Trainingszeit).</li>
  <li><strong>Wettkampf-Einladungen:</strong> unter <span class="ui">Anmeldungen</span> für das Kind zu- oder absagen, ggf. mit Übernachtung, Essen, Bus.</li>
  <li><strong>Einladungen</strong> zu Team-Events und Trainingslagern beantwortest du für minderjährige Kinder unter <span class="ui">Einladungen</span>.</li>
  <li><strong>Einwilligungen</strong> (Ernährung, Sportmedizin) für minderjährige Kinder erteilst oder widerrufst du in deinem Profil.</li>
</ul>
<p class="tip">Ein Kalender-Abo reicht für alle Kinder – bei mehreren Kindern steht der Name im Titel des Termins.</p>
HTML,
            ],
            [
                'key' => 'fahrgemeinschaft', 'title' => 'Fahrgemeinschaften anbieten', 'category' => 'eltern',
                'audience' => 'eltern',
                'keywords' => 'fahrgemeinschaft mitfahren fahrer plätze handynummer telefonnummer anzeigen',
                'html' => <<<'HTML'
<p>Bei Wettkämpfen mit Fahrgemeinschaften kannst du in der Anmeldung deines Kindes freie Plätze in deinem Auto anbieten. Andere Familien buchen diese Plätze im Portal.</p>
<ul>
  <li>Ob Mitfahrer deine Handynummer sehen, legst du im Profil als Voreinstellung fest und kannst es beim einzelnen Angebot ändern.</li>
  <li>Ziehst du ein Angebot zurück, werden die Mitfahrer per E-Mail informiert.</li>
</ul>
HTML,
            ],

            // ── Trainer ─────────────────────────────────────────────────────
            [
                'key' => 'trainer-einheiten', 'title' => 'Trainingseinheiten und Serien planen', 'category' => 'trainer',
                'audience' => 'trainer',
                'keywords' => 'einheit serie anlegen wöchentlich ausfall absagen saison trainer gruppen bahnen gastgruppe plätze',
                'html' => <<<'HTML'
<p>Unter <span class="ui">Trainingseinheiten</span> siehst du die Einheiten deiner Gruppen, geordnet nach Gruppe und Serie.</p>
<ul>
  <li><strong>Neue Einheit:</strong> Titel, Typ, Ort, Datum, Zeit und Gruppen. Mit <span class="ui">Wiederholung</span> (wöchentlich, zweiwöchentlich, monatlich) entsteht eine Serie; Ferien werden übersprungen.</li>
  <li><strong>Serie bearbeiten:</strong> <span class="ui">Serie öffnen</span> – Änderungen gelten für die ganze Serie. Einzelne Termine änderst du mit <span class="ui">Nur diesen Termin ändern</span>.</li>
  <li><strong>Ausfall:</strong> bei der Einheit <span class="ui">Fällt aus</span> – auf Wunsch per E-Mail an alle Teilnehmer.</li>
  <li><strong>Plätze und Gäste:</strong> Teilnehmerzahl begrenzen, Anmeldung öffnen und eine Gastgruppe festlegen, die freie Plätze buchen darf.</li>
  <li><strong>Einzelne Schwimmer</strong> fügst du über <span class="ui">Individuelle Schwimmer-Zuweisung</span> hinzu – für einen Termin oder die ganze Serie.</li>
</ul>
<p>Der Vorstand kann Einheiten aller Gruppen verwalten; Trainingspläne sieht er dabei nicht.</p>
HTML,
            ],
            [
                'key' => 'trainer-durchfuehrung', 'title' => 'Anwesenheit, Zeiten und Live-Zeitnahme', 'category' => 'trainer',
                'audience' => 'trainer',
                'keywords' => 'anwesenheit zeiten eintragen live zeitnahme handy beckenrand stoppuhr abgesagt',
                'html' => <<<'HTML'
<ul>
  <li><strong>Anwesenheit:</strong> in der Einheit die Teilnehmer abhaken. Vorab abgesagte Sportler sind gekennzeichnet.</li>
  <li><strong>Zeiten:</strong> <span class="ui">Neue Zeit eintragen</span> (Schwimmer, Disziplin, Distanz, Zeit). Neue Bestzeiten erkennt das Portal, passende Zeit-Ziele der Sportler werden automatisch erreicht.</li>
  <li><strong>Live-Zeitnahme:</strong> Enthält der Trainingsplan Blöcke mit Zeitnahme, öffnest du am Beckenrand auf dem Handy <span class="ui">Live-Zeitnahme</span> und stoppst die Zeiten je Wiederholung.</li>
  <li><strong>Selbsteinschätzungen</strong> der Sportler siehst du in der Einheit und unter <span class="ui">Einschätzungen</span>.</li>
</ul>
HTML,
            ],
            [
                'key' => 'trainingsplan', 'title' => 'Trainingspläne erstellen – und wer sie sieht', 'category' => 'trainer',
                'audience' => 'trainer',
                'keywords' => 'trainingsplan blöcke editor anhang drucken pdf schutz wer sieht',
                'html' => <<<'HTML'
<p>In der Einheit über <span class="ui">Trainingsplan erstellen</span> bzw. <span class="ui">Bearbeiten</span> baust du den Plan aus Blöcken (Wiederholungen, Distanz, Lage, Abgangszeit, Pause, Material) und kannst einen Anhang (PDF/Bild) hochladen. <span class="ui">Drucken / PDF</span> erzeugt eine Version für den Beckenrand.</p>
<p class="tip"><strong>Schutz deiner Pläne:</strong> Einen Trainingsplan sehen nur die Trainer, die bei der Einheit eingetragen sind oder die Gruppe betreuen, sowie Administratoren. Andere Trainer und der Vorstand sehen ihn nicht. Sportler sehen den Plan ihrer Einheit erst nach dem Training.</p>
HTML,
            ],
            [
                'key' => 'trainer-gruppen', 'title' => 'Trainingsgruppen, Ziele und Leistungskriterien', 'category' => 'trainer',
                'audience' => 'gruppen',
                'keywords' => 'gruppe mitglieder hinzufügen entfernen csv kriterien bewertung ziele kommentieren motto',
                'html' => <<<'HTML'
<ul>
  <li><strong>Trainingsgruppen:</strong> Mitglieder hinzufügen oder entfernen, auch per CSV-Import. Die Geschäftsstelle kann alle Gruppen und Kurse verwalten und Trainer zuordnen.</li>
  <li><strong>Leistungskriterien:</strong> je Gruppe festlegen und je Sportler und Saison als erreicht/nicht erreicht bewerten (unter <span class="ui">Ziele &amp; Kriterien</span>).</li>
  <li><strong>Persönliche Ziele</strong> deiner Sportler siehst und kommentierst du unter <span class="ui">Ziele &amp; Kriterien</span> – aber nur, wenn der Sportler sie freigegeben hat. Sonst siehst du nur die Anzahl.</li>
  <li><strong>Motto der Woche:</strong> in der Gruppe aktivieren, Reihenfolge und Wochen festlegen.</li>
</ul>
HTML,
            ],
            [
                'key' => 'trainer-wettkaempfe', 'title' => 'Wettkämpfe, Anmeldeabfragen und Ergebnisse', 'category' => 'trainer',
                'audience' => 'wettkampf',
                'keywords' => 'wettkampf anmeldeabfrage einladen meldung meldedatei dsv lenex import ergebnisse ausschreibung',
                'html' => <<<'HTML'
<ol>
  <li><strong>Wettkampf anlegen</strong> oder aus der Ausschreibung (Lenex/DSV, PDF) übernehmen – Strecken und Zeitplan kommen mit.</li>
  <li><strong>Anmeldeabfrage</strong> starten: Sportler bzw. Eltern bekommen eine Einladung und sagen zu oder ab, inkl. Übernachtung, Essen, Bus und Fahrgemeinschaften.</li>
  <li><strong>Meldungen</strong> zusammenstellen und die <strong>Meldedatei</strong> (DSV) für den Ausrichter herunterladen.</li>
  <li><strong>Ergebnisse importieren</strong> (DSV/Lenex oder WebClub-CSV) im Tab <span class="ui">Import</span> oder über das <span class="ui">Import-Center</span> – Bestzeiten und Rekorde werden erkannt.</li>
</ol>
HTML,
            ],
            [
                'key' => 'benutzer-light', 'title' => 'Mitglieder anlegen und Zugänge verschicken', 'category' => 'trainer',
                'audience' => 'benutzer',
                'keywords' => 'benutzer anlegen mitglied eltern verknüpfen willkommensmail zugang',
                'html' => <<<'HTML'
<p>Unter <span class="ui">Benutzer</span> siehst du die Mitglieder deiner Gruppen (Vorstand und Geschäftsstelle: alle). Du kannst neue Sportler oder Eltern anlegen, Kontaktdaten pflegen und Mitglieder einer Gruppe zuordnen. Neue Mitglieder bekommen ihre Willkommensmail mit dem Link zum Passwort.</p>
HTML,
            ],

            // ── Kampfrichter ────────────────────────────────────────────────
            [
                'key' => 'kampfrichter', 'title' => 'Kampfrichter: Anfragen beantworten und Qualifikationen pflegen', 'category' => 'kampfrichter',
                'audience' => 'kampfrichter',
                'keywords' => 'kampfrichter anfrage einsatz verfügbar position lizenz qualifikation ablauf',
                'html' => <<<'HTML'
<h4>Anfragen</h4>
<p>Für einen Wettkampf fragt der Kampfrichterobmann per E-Mail an. Im Portal gibst du je Wettkampftag an, ob du kannst, optional mit Kommentar und Wunschposition. Die Einteilung siehst du nach der Freigabe auf deiner Startseite, ebenso deine letzten Einsätze.</p>
<h4>Qualifikationen</h4>
<p>Unter <span class="ui">Meine Qualifikationen</span> pflegst du deine Qualifikationen mit Erwerbsdatum, Lizenznummer und Ablaufdatum. Sechs Monate vor Ablauf erinnert dich die Startseite.</p>
HTML,
            ],

            // ── Vorstand & Geschäftsstelle ──────────────────────────────────
            [
                'key' => 'obmann', 'title' => 'Kampfgericht anfragen, besetzen und melden', 'category' => 'verwaltung',
                'audience' => 'obmann',
                'keywords' => 'obmann kampfgericht anfrage bedarf positionen besetzen freigabe karimeldung meldedatei',
                'html' => <<<'HTML'
<ol>
  <li>Im Wettkampf, Tab <span class="ui">Kampfgericht</span>: Anfrage an alle oder ausgewählte Kampfrichter senden.</li>
  <li><strong>Gesuchte Positionen</strong> je Abschnitt festlegen (z. B. 2 × Zeitnehmer).</li>
  <li>Aus den Zusagen besetzen – Wunschpositionen sind mit ★ markiert, Qualifikationen stehen am Namen.</li>
  <li>Sind alle Positionen besetzt, <strong>freigeben</strong>. Die Einteilung geht dann in die Meldedatei (KARIMELDUNG).</li>
</ol>
<p>Die Startseite zeigt je Wettkampf, wie viele Positionen noch offen sind.</p>
HTML,
            ],
            [
                'key' => 'verwaltung', 'title' => 'Mitglieder, Gruppen und Lizenzen verwalten', 'category' => 'verwaltung',
                'audience' => 'verwaltung',
                'keywords' => 'geschäftsstelle vorstand mitglieder gruppen kurse zuweisen lizenzen auslaufend',
                'html' => <<<'HTML'
<ul>
  <li><strong>Benutzer:</strong> alle Mitglieder bearbeiten und Gruppen zuordnen.</li>
  <li><strong>Trainingsgruppen &amp; Kurse:</strong> Mitglieder und Trainer zuweisen (Geschäftsstelle).</li>
  <li><strong>Kampfrichter &amp; Lizenzen:</strong> Qualifikationen aller Kampfrichter pflegen; der Filter <span class="ui">Läuft aus</span> zeigt Lizenzen, die in sechs Monaten ablaufen.</li>
  <li><strong>Termine:</strong> Vereinstermine anlegen, Einladungen verschicken und verwalten.</li>
</ul>
<p>Was welche Rolle darf, legt der Administrator in der Berechtigungs-Matrix fest.</p>
HTML,
            ],

            // ── Datenschutz ─────────────────────────────────────────────────
            [
                'key' => 'datenschutz', 'title' => 'Wer sieht meine Daten?', 'category' => 'datenschutz',
                'audience' => 'alle', 'public' => true,
                'keywords' => 'datenschutz dsgvo wer sieht daten trainer gesundheitsdaten ziele telefonnummer löschen auskunft',
                'html' => <<<'HTML'
<ul>
  <li><strong>Trainer</strong> sehen die Mitglieder ihrer Gruppen: Kontaktdaten, Anwesenheit, Zeiten, Selbsteinschätzungen und – nur wenn freigegeben – persönliche Ziele.</li>
  <li><strong>Gesundheitsdaten</strong> (Ernährungsberatung, Sportmedizin) nur mit deiner Einwilligung und nur für die zuständigen Personen. Du siehst deine Dokumente unter <span class="ui">Gesundheitsdaten</span>.</li>
  <li><strong>Trainingspläne</strong> sehen nur die Trainer der Einheit bzw. Gruppe.</li>
  <li><strong>Telefonnummern</strong> bei Fahrgemeinschaften nur, wenn du das Häkchen dafür setzt.</li>
  <li><strong>Termine mit Einladung</strong>: Agenda, Anhänge und Protokolle nur für Eingeladene.</li>
</ul>
<p>Auskunft, Berichtigung oder Löschung deiner Daten kannst du jederzeit beim Verein anfragen. Details stehen in der [[legal.datenschutz|Datenschutzerklärung]].</p>
HTML,
            ],
        ]);
    }

    /** @return list<array{q: string, a: string, audience: string, public: bool}> */
    public static function faq(): array
    {
        return array_map(fn($f) => $f + ['public' => false, 'audience' => 'alle'], [
            ['q' => 'Ich habe keine Willkommensmail bekommen.', 'public' => true,
             'a' => 'Bitte zuerst im Spam-Ordner nachsehen. Ansonsten auf der Anmeldeseite <span class="ui">Passwort vergessen?</span> mit deiner beim Verein hinterlegten E-Mail-Adresse nutzen – damit setzt du dein Passwort genauso. Kommt auch dann nichts an, ist vermutlich eine andere Adresse hinterlegt: bitte beim Trainer oder der Geschäftsstelle melden.'],
            ['q' => 'Der Link zum Passwort-Setzen funktioniert nicht mehr.', 'public' => true,
             'a' => 'Der Link aus der Willkommensmail gilt 14 Tage, der aus „Passwort vergessen“ 60 Minuten, und jeder Link funktioniert nur einmal. Fordere über <span class="ui">Passwort vergessen?</span> einfach einen neuen an.'],
            ['q' => 'Was ist mein Benutzername?', 'public' => true,
             'a' => 'Deine E-Mail-Adresse, an die auch die Willkommensmail ging.'],
            ['q' => 'Mein Passwort wird nicht angenommen.', 'public' => true,
             'a' => 'Ein Passwort braucht mindestens 10 Zeichen und muss Buchstaben und Zahlen enthalten. Beide Eingaben müssen gleich sein.'],
            ['q' => 'Wir sind mehrere Familienmitglieder – brauchen wir mehrere Zugänge?', 'public' => true,
             'a' => 'Eltern haben einen eigenen Zugang, über den sie alle ihre Kinder sehen. Jugendliche und erwachsene Sportler können einen eigenen Zugang mit eigener E-Mail-Adresse bekommen.'],
            ['q' => 'Wie lege ich das Portal als App auf mein Handy?', 'public' => true,
             'a' => 'iPhone: in Safari Teilen-Symbol → <span class="ui">Zum Home-Bildschirm</span>. Android: in Chrome Menü (drei Punkte) → <span class="ui">App installieren</span> bzw. <span class="ui">Zum Startbildschirm hinzufügen</span>. Ausführlich in der Anleitung „Portal als App auf den Startbildschirm legen“.'],
            ['q' => 'Warum sehe ich eine Trainingseinheit nicht?',
             'a' => 'Du siehst nur Einheiten, denen du zugeordnet bist – über deine Trainingsgruppe, eine Einzelzuweisung oder eine Anmeldung. Hast du eine Serie dauerhaft abgesagt, fehlen deren Termine ebenfalls. Fehlt eine Gruppe, sprich deinen Trainer an.'],
            ['q' => 'Bis wann kann ich ein Training absagen?', 'audience' => 'sportler',
             'a' => 'Bis zum Ende der Trainingszeit, also auch noch am selben Tag. Die Absage lässt sich wieder zurücknehmen.'],
            ['q' => 'Woher weiß ich, dass ein Training ausfällt?',
             'a' => 'Du bekommst eine E-Mail (Thema „Trainingsausfall“ im Profil), und die Einheit steht im Kalender und im Kalender-Abo als ausgefallen bzw. „ABGESAGT“.'],
            ['q' => 'Ich bekomme zu viele E-Mails.',
             'a' => 'Unter Mein Profil → <span class="ui">E-Mail-Benachrichtigungen</span> wählst du die Themen ab, die du nicht brauchst. Nur Mails zu Konto und Zugang lassen sich nicht abbestellen.'],
            ['q' => 'Mein Kalender-Abo zeigt neue Termine nicht an.',
             'a' => 'Kalender holen Abos nur in Abständen ab: iPhone je nach Einstellung, Outlook alle paar Stunden, Google nur alle 8 bis 24 Stunden. Im Portal ist der Termin sofort da. Auf Android geht es mit der App ICSx⁵ schneller.'],
            ['q' => 'Beim Abonnieren auf dem iPhone kommt eine Warnung oder „Überprüfung fehlgeschlagen“.',
             'a' => 'Bitte das fehlerhafte Abo löschen, die Abo-Seite im Portal neu laden und erneut <span class="ui">Auf diesem Gerät abonnieren</span> tippen. Alternativ den Link kopieren und über Einstellungen → Apps → Kalender → Kalender-Accounts → Andere → <span class="ui">Kalenderabo hinzufügen</span> einfügen.'],
            ['q' => 'Ich habe meinen Abo-Link versehentlich weitergegeben.',
             'a' => 'Auf der Abo-Seite <span class="ui">Neuen Link erzeugen</span>. Der alte Link funktioniert sofort nicht mehr; danach im eigenen Kalender neu abonnieren.'],
            ['q' => 'Kann ich Termine im Handy-Kalender ändern?',
             'a' => 'Nein, das Abo ist nur zum Anzeigen. Zu- und Absagen machst du im Portal – die Änderung erscheint dann beim nächsten Abgleich im Kalender.'],
            ['q' => 'Sieht mein Trainer meine Ziele?', 'audience' => 'sportler',
             'a' => 'Nur, wenn du das Ziel freigibst (<span class="ui">Für meine Trainer sichtbar</span>). Sonst sieht er nur, dass du Ziele geplant hast. Du kannst die Freigabe an jedem Ziel jederzeit ändern.'],
            ['q' => 'Warum sehe ich den Trainingsplan noch nicht?', 'audience' => 'sportler',
             'a' => 'Sportler sehen den Plan ihrer Einheit erst nach dem Training.'],
            ['q' => 'Wie sage ich für mein Kind ab?', 'audience' => 'eltern',
             'a' => 'Im Menü beim Kind → <span class="ui">Training</span> → bei der Einheit <span class="ui">Absagen</span>. Wettkampf-Einladungen beantwortest du beim Kind unter <span class="ui">Anmeldungen</span>.'],
            ['q' => 'Sehen andere Eltern meine Telefonnummer?', 'audience' => 'eltern',
             'a' => 'Nur bei deinen Fahrgemeinschafts-Angeboten und nur, wenn du das Häkchen dafür setzt (Voreinstellung im Profil, änderbar je Angebot).'],
            ['q' => 'Sieht der Vorstand meine Trainingspläne?', 'audience' => 'trainer',
             'a' => 'Nein. Trainingspläne sehen nur die Trainer der Einheit bzw. Gruppe und Administratoren – es sei denn, der Administrator schaltet das Recht „Trainingspläne aller Einheiten einsehen“ ausdrücklich frei.'],
            ['q' => 'Ein Mitglied fehlt in meiner Gruppe.', 'audience' => 'trainer',
             'a' => 'Unter Trainingsgruppen → Gruppe bearbeiten kannst du Mitglieder hinzufügen. Ist das Mitglied gar nicht im Portal, lege es unter <span class="ui">Benutzer</span> an oder wende dich an die Geschäftsstelle.'],
            ['q' => 'Etwas funktioniert nicht – wie melde ich das?',
             'a' => 'Über <span class="ui">Support</span> im Konto-Menü: „Fehler melden“ oder „Verbesserungsvorschlag“. Bitte kurz beschreiben, was du getan hast, was passiert ist und auf welchem Gerät.'],
        ]);
    }

    /** [[route|Text]] in Links umwandeln */
    public static function render(string $html): string
    {
        return preg_replace_callback('/\[\[([a-z0-9_.-]+)\|([^\]]+)\]\]/i', function ($m) {
            return Route::has($m[1])
                ? '<a href="' . e(route($m[1])) . '">' . e($m[2]) . '</a>'
                : e($m[2]);
        }, $html);
    }
}

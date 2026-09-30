# WaRa-Portal – Hinweise für Claude Code

Laravel 11 Portal für SG Wasserratten Norderstedt e.V.

## Deployment-Umgebung

- **Produktionsserver**: Shared Hosting all-inkl.com
- **PHP auf dem Server**: 8.3.29 (nicht 8.4!)
- **Deployment**: GitHub Action auf Push nach `main` → SSH-Skript führt `git pull`, `composer install`, `php artisan migrate`, Cache-Rebuild aus

## Wichtige Einschränkungen

### PHP-Plattform (composer.json)

`composer.json` enthält `"platform": { "php": "8.3.29" }`. Diese Einstellung ist bewusst gesetzt und darf **nicht entfernt werden**.

**Grund**: Diese Cloud-Umgebung läuft auf PHP 8.4.x. Ohne die Platform-Einschränkung zieht `composer update` Pakete wie `symfony/* v8.1.x`, die PHP ≥ 8.4.1 voraussetzen — das bricht das Deployment, weil der Server nur PHP 8.3.29 hat.

**Regel**: Nach jedem `composer update` in dieser Session prüfen, ob das `composer.lock` noch PHP-8.3-kompatible Versionen enthält. Im Zweifel das `composer.lock` aus dem letzten funktionierenden Deployment wiederherstellen (`git show HEAD~N:composer.lock`).

### composer.lock immer committen

Das `composer.lock` muss immer mit committet werden — der Server führt `composer install` (nicht `composer update`) aus.

## UI-Regeln (verbindlich) – vor jeder Änderung an Views lesen

Vollständig in **[docs/design-system.md](docs/design-system.md)**. Die Kurzfassung ist
Pflicht, auch wenn bestehende Seiten es noch anders machen (die sind kein Vorbild):

- **Baustein vor Handarbeit:** `x-ui.button` (primary | secondary | danger),
  `x-ui.field`, `x-ui.card`, `x-ui.dialog`, `x-ui.empty-state`, `x-ui.tabs`, `x-ui.menu`,
  `x-ui.page-header`, `x-ui.alert`, `x-ui.badge`, Import-Bausteine. Musterseite `/admin/ui`.
  Wer eine Seite anfasst, stellt die berührten Teile auf Bausteine um.
- **Farben:** Knöpfe nur `primary` (Hauptaktion, eine je Bereich), weiß/secondary, `accent`
  (nur Zerstörendes). Text mind. 4,5:1: Nebentext `text-gray-600`, nie `text-gray-200/300`
  für Text, `gray-400/500` nicht auf `gray-100`. Statusfarben als Text ab Stufe 700.
  Keine `opacity-*` auf Text, keine `text-…/70`.
- **Größen:** Text mind. 12 px (`text-xs`), Touch-Ziele regelt `app.css` global
  (`touch-exempt` nur wenn Größe Bedeutung hat).
- **Pop-ups:** nie `alert/confirm/prompt` → `$confirm`, `data-confirm`, `$prompt`, `$toast`,
  `x-ui.dialog`. Speichern per JS mit `api()`, ohne Neuladen.
- **Formulare:** jedes Feld beschriftet, Fehler am Feld (Übersicht macht das Layout).
- **Tabellen:** Muster aus design-system.md, Abschnitt 7 (`scope="col"`, `tabular-nums`,
  mobil nicht quetschen, Auswahl-Checkboxen mit `aria-label`).
- **Prüfen vor dem Commit:** `npm run lint:ui` (scheitert an neuen Verstößen) und – bei
  neuen Seiten – Testdaten im `E2eSeeder` ergänzen, damit die Browsertests sie abdecken.
  Nach einer Bereinigung: `npm run lint:ui -- --update` und die Ausgangsliste mit committen.

## Frontend-Build (Vite + Tailwind v3.4 + Alpine)

- CSS/JS kommen aus `resources/css/app.css` und `resources/js/app.js`, gebaut mit
  `npm run build` nach `public/build` (nicht im Repo). Lokal nach jeder
  View-Änderung neu bauen oder `npm run dev` laufen lassen – sonst fehlen neue
  Klassen bzw. es gibt „Vite manifest not found“.
- **Tailwind-Klassen nie zusammensetzen** (`bg-{{ $farbe }}-50`): der Build findet
  nur ausgeschriebene Klassen. Gilt auch für Klassen-Strings in PHP (app/ wird mit
  durchsucht).
- **iOS/iPadOS 15 ist Untergrenze**: deshalb Tailwind v3.4 (nicht v4), Alpine
  `x-trap` statt `<dialog>`, Polyfills in `resources/js/polyfills.js`. Nach
  Paket-Updates die Bundles auf `.at(`/`findLast`/`structuredClone` prüfen.
- Deploy: Die GitHub Action baut und lädt `public/build` per SCP hoch
  (`public/build-new` → Tausch auf dem Server vor `git pull`, vorheriger Stand in
  `public/build-old`).
- UI-Bausteine liegen unter `resources/views/components/ui/` (`x-ui.*`), Plan und
  Regeln in `docs/frontend-audit.md`.
- **Kein `@js(…)` in Attributen von `<x-…>`-Komponenten** (wird nicht übersetzt →
  JS-Fehler): `{{ \Illuminate\Support\Js::from($wert) }}` verwenden. Lint prüft das.
- **Keine `<x-…>`-Tags in Kommentaren innerhalb von `<script>`**: Blade kompiliert
  sie trotzdem als Komponente → PHP-Syntaxfehler (Fehler 500).

## Browsertests (Playwright + axe)

- GitHub Action `.github/workflows/e2e.yml` bei jedem Push/PR: frische MySQL,
  `migrate:fresh --seed --seeder=E2eSeeder`, `php artisan serve`, `npx playwright test`.
  Läuft neben dem Deploy und blockiert ihn nicht. Bei Fehlern: Artefakt „e2e-bericht“.
- Tests unter `tests/e2e/`: `pages.spec.js` prüft **jede Menüseite jeder Rolle**
  (Status, JS-Fehler, Überlauf, axe kritisch/ernst = 0), dazu Dialoge und Importe.
  Neue Seiten im Menü sind automatisch abgedeckt.
- Testkonten aus `database/seeders/E2eSeeder.php` (admin/trainer/schwimmer/eltern
  `@e2e.test`, Passwort `E2e-Test-2026`). Neue Bereiche mit Testdaten dort ergänzen.
- **Nur gegen eine Test-Datenbank**: Die Tests speichern und importieren. Sie
  verweigern den Start ohne `E2E_ALLOW_WRITES=1` und außerhalb von localhost.
- Lokal (Windows): eigene DB `wara_e2e`, Server mit `DB_DATABASE=wara_e2e php artisan
  serve --port=8766`, dann `E2E_BASE_URL=http://127.0.0.1:8766 E2E_ALLOW_WRITES=1
  E2E_LOCAL_ASSETS=1 npx playwright test`. `E2E_LOCAL_ASSETS=1` liefert `/build/assets`
  von der Platte, weil der PHP-Server unter Windows Verbindungen abbricht.

## Entwicklungsumgebung (diese Cloud-Session)

- PHP: 8.4.x (abweichend vom Server!)
- Node.js: 22.x unter `/opt/node22/bin/node`
- Playwright: 1.56.1 global unter `/opt/node22/lib/node_modules/playwright`
- Chromium für Playwright: `/opt/pw-browsers`

## Datenbankschema (Kurzübersicht)

- `users` – Rollen: admin, trainer, schwimmer, elternteil, kampfrichter, vorstand
- `competitions` + `competition_events` + `competition_results` – Wettkampfdaten
- `competition_entries` – Meldungen (vor dem Wettkampf)
- `import_log` – Log aller Crawler-Läufe (source: shsv/nsv/dsv/dsvdata/webclub_crawler/webclub_batch/manual)
- `settings` – Key-Value-Store für alle konfigurierbaren Parameter (Model: `Setting`)

## WebClub-Schnittstelle

- **Playwright-Script**: `scripts/webclub-crawler.js` (Node.js)
- **PHP-Service**: `app/Services/Crawler/WebClubCrawler.php`
- **Artisan-Befehl**: `php artisan webclub:crawl`
- **Konfiguration**: Admin → Einstellungen → WebClub-Schnittstelle (URL, Benutzername, Passwort verschlüsselt)
- **Scheduler**: Import-Log-Seite (Kachel "WebClub Crawler"), standardmäßig deaktiviert
- **Sync-Prinzip**: non-destruktiv — nur NULL-Felder befüllen, nie überschreiben, nie löschen
- WebClub-Benutzername: `martin.wilkens@itnweb.de`

## Crawler-Architektur

Alle automatischen Importe laufen als Laravel-Scheduled-Commands über `routes/console.php`.
Der Cron auf dem Server ruft minütlich `GET /cron/run/{token}` auf → `CronController` → `php artisan schedule:run`.
Konfiguration (aktiviert, Tage, Uhrzeit) per Crawler in der `settings`-Tabelle unter dem Schlüssel `crawler.{source}.*`.

## E-Mail-Versand

Ausführlich in [docs/mail-und-zugang.md](docs/mail-und-zugang.md). Das Wichtigste:

- **Nie direkt `Mail::to()` verwenden**, sondern `App\Services\Mailer`. Nur dort
  greifen Opt-in-Prüfung, Wartungsmodus-Umleitung und Protokoll.
- **Opt-in**: Außer Kontomails (`MailTopic::ACCOUNT`) geht nur raus, was im
  Profil eingeschaltet ist. Neue Themen gehören in `App\Support\MailTopic`.
  Einzige Ausnahme: Themen mit `'default' => true` (Trainingsausfall) sind an,
  bis sie abgewählt werden – neue Ausnahmen nur nach Rücksprache.
- **Ereignis-Mails** bauen auf `App\Services\EventMailer` auf; dort liegt auch
  die Auswahl der Empfänger (inkl. Eltern und Gruppentrainer).
- **Eine Vorlage für alle Ereignisse**: `App\Mail\NotificationMail` — keine
  neuen Mailables für jedes Ereignis anlegen.
- **Massenversand** immer über `Mailer::queue()`, nie synchron in einer Anfrage.
- **Keine Passwörter in Mails.** Zugang nur über Einmallinks aus
  `App\Services\AccountLinkService`.

## Wichtige Dateipfade

| Zweck | Pfad |
|---|---|
| Crawler | `app/Services/Crawler/` |
| Import-Services | `app/Services/Import/` |
| Admin-Controller | `app/Http/Controllers/Admin/` |
| Scheduler | `routes/console.php` |
| Settings-View | `resources/views/admin/settings/index.blade.php` |
| Import-Log-View | `resources/views/admin/import-log/index.blade.php` |
| Mailversand | `app/Services/Mailer.php`, `app/Services/EventMailer.php` |
| Mailvorlagen | `resources/views/emails/` |
| Mail-Themenkatalog | `app/Support/MailTopic.php` |
| Anmeldung und Passwort | `app/Http/Controllers/Auth/` |

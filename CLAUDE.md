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

## Wichtige Dateipfade

| Zweck | Pfad |
|---|---|
| Crawler | `app/Services/Crawler/` |
| Import-Services | `app/Services/Import/` |
| Admin-Controller | `app/Http/Controllers/Admin/` |
| Scheduler | `routes/console.php` |
| Settings-View | `resources/views/admin/settings/index.blade.php` |
| Import-Log-View | `resources/views/admin/import-log/index.blade.php` |

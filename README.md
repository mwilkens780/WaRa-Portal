# WaRa-Portal – SG Wasserratten Norderstedt e.V.

Trainings- und Wettkampfportal für den Schwimmverein SG Wasserratten Norderstedt e.V.

## Funktionen

- **Login** mit rollenbasiertem Zugriff (Admin, Trainer, Schwimmer, Elternteil)
- **Admin**: Benutzerverwaltung, Wettkampfverwaltung, alle Trainingsdaten einsehen
- **Trainer**: Trainingseinheiten anlegen, Anwesenheit erfassen, Zeiten dokumentieren
- **Schwimmer**: Eigene Trainingshistorie, Zeiten & Wettkampfergebnisse einsehen
- **Elternteil**: Daten der eigenen Kinder einsehen

## Voraussetzungen

- PHP 8.2 oder höher
- MySQL 8.0 oder höher
- Composer

## Installation (lokal / Entwicklung)

### 1. PHP und Composer installieren

**Empfohlen:** [Laragon](https://laragon.org/download/) — installiert PHP, MySQL und Composer in einem Schritt (Windows).

Oder separat:
- PHP: https://windows.php.net/download/ (Thread Safe, x64)
- Composer: https://getcomposer.org/Composer-Setup.exe

### 2. Abhängigkeiten installieren

```bash
cd Codeline
composer install
```

### 3. Umgebungsdatei anlegen

```bash
copy .env.example .env
php artisan key:generate
```

### 4. Datenbank konfigurieren

In `.env` anpassen:

```
DB_DATABASE=wara_portal
DB_USERNAME=dein_benutzer
DB_PASSWORD=dein_passwort
```

Datenbank in MySQL anlegen:

```sql
CREATE DATABASE wara_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Migrationen und Testdaten

```bash
php artisan migrate --seed
```

### 6. Lokalen Server starten

```bash
php artisan serve
```

Portal aufrufen: http://localhost:8000

---

## Deployment auf Shared Hosting

### Methode A: Document Root auf /public zeigen (empfohlen)

Im Hosting-Panel den Document Root des Domains auf das Verzeichnis `public/` zeigen lassen.

### Methode B: Wenn Document Root nicht änderbar ist

1. Inhalt von `public/` in das Web-Root-Verzeichnis (z.B. `public_html/`) kopieren
2. In `public_html/index.php` den Pfad anpassen:

```php
// Von:
require __DIR__.'/../vendor/autoload.php';
// Zu (Pfad zu deinem Laravel-Verzeichnis anpassen):
require __DIR__.'/../wara-portal/vendor/autoload.php';
```

### Schritte für Shared Hosting:

1. Alle Dateien per FTP/SFTP hochladen
2. Via SSH (oder phpMyAdmin) die Datenbank anlegen
3. `.env` mit Hosting-Datenbankdaten befüllen
4. Via SSH ausführen:
   ```bash
   php artisan migrate --seed
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

Falls kein SSH verfügbar: Migrationen lokal ausführen und fertigen SQL-Dump importieren.

---

## Testdaten (nach `php artisan migrate --seed`)

| Rolle       | E-Mail                    | Passwort      |
|-------------|---------------------------|---------------|
| Admin       | admin@wasserratten.de     | Admin1234     |
| Trainer     | trainer@wasserratten.de   | Trainer1234   |
| Schwimmer   | anna@example.de           | Schwimmer1234 |
| Elternteil  | eltern@example.de         | Eltern1234    |

**Wichtig:** Passwörter nach dem ersten Login ändern!

---

## Technologie

- **Backend**: Laravel 11 (PHP 8.2+)
- **Datenbank**: MySQL 8.0+
- **Frontend**: Tailwind CSS (CDN), Alpine.js (CDN)
- **Kein Build-Schritt erforderlich** (kein npm/webpack)

## Datenbankstruktur

```
users                  – Alle Benutzer (Rollen: admin, trainer, schwimmer, elternteil)
parent_swimmer         – Eltern-Kind-Zuordnung
training_sessions      – Trainingseinheiten
training_attendances   – Anwesenheit pro Einheit
swimming_times         – Trainingszeiten der Schwimmer
competitions           – Wettkämpfe
competition_results    – Wettkampfergebnisse
```

---

## Sicherheit

- Alle Passwörter werden mit bcrypt gehasht (Laravel-Standard)
- CSRF-Schutz auf allen Formularen
- Rollenbasierte Zugriffskontrolle via Middleware
- Inaktive Benutzer werden beim Login abgewiesen
- SQL-Injection-Schutz durch Eloquent ORM

---

Entwickelt für SG Wasserratten Norderstedt e.V. | © 2025

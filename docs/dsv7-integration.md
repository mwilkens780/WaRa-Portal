# DSV7-Integration: WaRa-Portal

**Stand:** Juni 2026
**Scope:** SHSV · Norddeutscher Schwimmverband (NSV) · DSV
**Stack:** Laravel 11 · PHP 8.2+ · MySQL · Shared Hosting

**Legende:**
- ✅ Bereits implementiert
- 🔄 ALTER TABLE / Migration nötig (bestehende Daten bleiben erhalten)
- 🆕 Neue Tabelle / neues Feature

---

## 1. Kontext & Datenquellen

### 1.1 Verbandsstruktur (Schleswig-Holstein)

```
DSV (Bundesebene)
└── NSV – Norddeutscher Schwimmverband (Regionalebene)
    └── SHSV – Schleswig-Holsteinischer Schwimmverband (Landesebene)
        └── Vereine (u.a. SG Wasserratten Norderstedt)
```

### 1.2 Wo liegen die DSV7-Ergebnisdateien?

| Ebene | Website | Dateityp | Zugang |
|---|---|---|---|
| **SHSV** | `shsv.de/vereinswettkaempfe` | `*-Pr.DSV7` Wettkampfergebnisliste | Öffentlich (HTML-Links) |
| **NSV** | `nsv-schwimmen.de` | Wettkampfergebnisliste | Öffentlich (je nach Veranstaltung) |
| **DSV** | `dsv.de` | Ergebnislisten DM, DJM, … | Öffentlich, direkte Dateilinks |

> **Hinweis SHSV:** Ausrichter müssen laut SHSV-Orghandbuch (Register 21) nach jedem Wettkampf die DSV-Ergebnisdatei einreichen. Die Dateien werden auf `shsv.de/vereinswettkaempfe` verlinkt — kein strukturierter API-Endpunkt, nur HTML-Scraping möglich.

### 1.3 Gibt es eine offizielle DSV-API für Bestenlisten?

**Nein.** Der DSV stellt keine maschinenlesbare API bereit.

**Alternativer Weg: swimrankings.net**
Die Plattform swimrankings.net ist die de-facto internationale Schwimm-Datenbank. Sie bietet keine offizielle API, aber stabile Athleten-URLs. Da PHP-Scraping nötig wäre, wird swimrankings nur als optionale Anreicherung betrachtet.

**Empfehlung:** Bestenlisten **selbst aus den importierten DSV7-Dateien berechnen** — eigene DB → eigene Bestenliste. Das Portal speichert ohnehin alle importierten Ergebnisse.

---

## 2. DSV7-Dateiformat (Kurzreferenz)

DSV7-Dateien sind UTF-8- oder Windows-1252-Textdateien. Jede Zeile ist ein Element, Felder durch `;` getrennt.

### 2.1 Relevante Elemente der Wettkampfergebnisliste (`*-Pr.DSV7`)

```
FORMAT;Wettkampfergebnisliste;7
ERZEUGER;EasyWk;3.2.1;info@easywk.de
VERANSTALTUNG;Frühjahrsmeisterschaft SH;Kiel;25;AUTOMATISCH;...
VERANSTALTUNGSORT;Olympiazentrum Kiel;...;Kiel;GER
WETTKAMPF;1;50;F;A;2026;...          ← Disziplin-Definition
EINZELERGEBNIS;1;Müller;Anna;2008;Wasserratten;...;GER;00:28,45;1;...
STAFFELERGEBNIS;...
DATEIENDE
```

### 2.2 Zeitformat und Speicherung im Portal

DSV7-Dateien speichern Zeiten als `HH:MM:SS,hh` (Stunden:Minuten:Sekunden,Hundertstel).

**Das Portal speichert Zeiten in `time_ms` (Millisekunden).** Umrechnungsformel:

```php
// "00:28:45,32" → DSV7: 2845 Hundertstel → Portal: 28453 Millisekunden
// Hundertstel × 10 = Millisekunden
// Beispiel: 28 Sek 45 Hundertstel = 2845 Hundertstel × 10 = 28450 ms

// Bereits in app/Services/Dsv7Parser.php implementiert:
private function parseTimeMs(string $zeit): int
{
    // "MM:SS,hh" oder "HH:MM:SS,hh"
    [$hms, $hundredths] = explode(',', $zeit);
    $parts = explode(':', $hms);
    $h = count($parts) === 3 ? (int)$parts[0] : 0;
    $m = (int)($parts[count($parts)-2] ?? 0);
    $s = (int)$parts[count($parts)-1];
    return (($h * 3600 + $m * 60 + $s) * 100 + (int)$hundredths) * 10;
}
```

> **Wichtig:** Die DSV7-Bestenliste vergleicht Hundertstel (`time_hundredths`), das Portal arbeitet mit Millisekunden (`time_ms`). Konversion: `hundredths = time_ms / 10`.

### 2.3 Disziplin-Mapping (DSV7 → Portal)

Das Portal verwendet deutsche Bezeichnungen intern (nicht die DSV7-Kürzel):

| DSV7 | Portal (`discipline`) |
|---|---|
| `F` | `freistil` |
| `B` | `brust` |
| `R` | `ruecken` |
| `S` | `schmetterling` |
| `L` | `lagen` |

Dieses Mapping ist in ✅ `app/Services/Dsv7Parser.php` (`STROKE_MAP`) implementiert.

### 2.4 Dateinamenskonvention

```
JJJJ-MM-TT-Ort-Pr.DSV7        ← Wettkampfergebnisliste
JJJJ-MM-TT-Ort-Wk.DSV7        ← Wettkampfdefinitionsliste
JJJJ-MM-TT-Ort-VereinsKuerzel-Pr.DSV7   ← Vereinsergebnisliste
```

---

## 3. Datenbankschema

### 3.1 Übersicht — aktueller Stand

```
competitions ──< competition_events
competitions ──< competition_results >── users (role='schwimmer')
competitions ──< competition_signup_requests ──< competition_signup_responses >── users
users ──< training_group_user >── training_groups
seasons ──< competitions (geplant)
records (Vereins- und Landesrekorde)
```

### 3.2 Übersicht — nach DSV7-Vollintegration

```
federations ──< competitions ──< competition_events
                competitions ──< competition_results >── users (eigene Schwimmer)
                competitions ──< ext_results >── athletes
                                  ext_results (Staffeln) ──< relay_members >── athletes
athletes ──(optional)── users  (bei eigenen Vereinsmitgliedern)
import_log
```

### 3.3 Bestehende Tabellen (✅ vorhanden)

#### `competitions`

```sql
-- Bestehende Felder (vereinfacht):
id, name, location, date, date_end, meldeschluss,
type ENUM('vereinsintern','regional','national','international',
          'meisterschaften','einladung','nop','dms','shsv'),
description, organizer,
course ENUM('LCM','SCM'),     -- LCM = 50m Langbahn, SCM = 25m Kurzbahn
season_id,
organisation_notes JSON,
created_at, updated_at
```

#### `competition_events`

Speichert die Disziplinen-Definitionen einer Veranstaltung (aus `WETTKAMPF`-Zeilen der DSV7-Datei).

```sql
id, competition_id, event_number, session_number, session_date,
session_name, discipline VARCHAR,  -- 'freistil','brust','ruecken','schmetterling','lagen'
distance INT, gender ENUM('M','F','X'),
age_min, age_max, age_group VARCHAR(50),
qualifying_time_ms INT,            -- Pflichtzeit in Millisekunden
meldegeld DECIMAL,
created_at, updated_at
```

#### `competition_results`

Speichert Ergebnisse **eigener Vereinsschwimmer** (verknüpft mit `users`).

```sql
id, competition_id,
user_id INT NOT NULL,              -- ← nur eigene Schwimmer (users.role='schwimmer')
discipline VARCHAR,
distance INT,
time_ms INT,                       -- Millisekunden (0 = DNS/DNF/DQ, notes enthält Status)
placement INT,
is_personal_best BOOLEAN,
age_group VARCHAR,
gender CHAR(1),
notes VARCHAR,                     -- z.B. 'DNS', 'DNF', 'DQ'
breaks_vereinsrekord BOOLEAN,
breaks_landesrekord BOOLEAN,
is_final BOOLEAN,
created_at, updated_at
```

> **Designentscheidung:** `competition_results` bleibt unverändert und wird **nur für eigene Vereinsschwimmer** genutzt. Für die Vollintegration (alle Clubs eines Wettkampfs) wird die neue Tabelle `ext_competition_results` verwendet (→ Abschnitt 3.5).

#### `users`

```sql
-- Relevante Felder für DSV7-Matching:
id, firstname, lastname,
birth_date DATE,                   -- Jahrgang ableitbar: YEAR(birth_date)
gender ENUM('M','F'),
dsv_id VARCHAR(20) UNIQUE,         -- DSV-Startnummer für Matching
role ENUM('admin','trainer','schwimmer','elternteil')
```

#### `records`

```sql
id, type ENUM('vereinsrekord','landesrekord'),
discipline VARCHAR, distance INT, gender ENUM('M','F'),
age_group VARCHAR, course ENUM('LCM','SCM'),
time_ms INT,
set_by_user_id, competition_id, set_at
```

### 3.4 Geplante Migrationen (🔄 ALTER TABLE — keine Daten gehen verloren)

```sql
-- Migration: 2026_XX_XX_add_import_fields_to_competitions
ALTER TABLE competitions
    ADD COLUMN federation_id    TINYINT UNSIGNED NULL
                                COMMENT 'shsv=1, nsv=2, dsv=3 — NULL für eigene Veranstaltungen'
                                AFTER season_id,
    ADD COLUMN level            VARCHAR(20) NULL DEFAULT NULL
                                COMMENT 'dsv_dm|dsv_djm|nsv|shsv_lm|shsv_open|vereins'
                                AFTER federation_id,
    ADD COLUMN source_file      VARCHAR(255) NULL AFTER level,
    ADD COLUMN source_url       VARCHAR(500) NULL AFTER source_file,
    ADD COLUMN import_hash      CHAR(64) NULL UNIQUE
                                COMMENT 'SHA-256 des DSV7-Dateiinhalts — verhindert Doppel-Import'
                                AFTER source_url;

-- Index für schnelle Hash-Lookups
ALTER TABLE competitions
    ADD INDEX idx_import_hash (import_hash);
```

> **Alle Felder sind nullable** — bestehende, manuell angelegte Wettkämpfe behalten `NULL` in diesen Spalten. Die bisherige Funktionalität (Detailansicht, Ergebnisse, Tabs) ist nicht betroffen.

### 3.5 Neue Tabellen (🆕)

```sql
-- Verbände
CREATE TABLE federations (
    id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug    VARCHAR(10) NOT NULL UNIQUE,   -- 'shsv', 'nsv', 'dsv'
    name    VARCHAR(100) NOT NULL
);

INSERT INTO federations (slug, name) VALUES
    ('shsv', 'Schleswig-Holsteinischer Schwimmverband'),
    ('nsv',  'Norddeutscher Schwimmverband'),
    ('dsv',  'Deutscher Schwimm-Verband');

-- Externe Athleten (alle Clubs aus DSV7-Importen)
-- Eigene Vereinsmitglieder können hier optional verknüpft werden (user_id)
CREATE TABLE athletes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lastname        VARCHAR(100) NOT NULL,
    firstname       VARCHAR(100) NOT NULL,
    birth_year      SMALLINT UNSIGNED NOT NULL,
    gender          ENUM('M','F','X') NOT NULL,
    nationality     CHAR(3) NULL,          -- FINA-Kürzel, z.B. 'GER'
    club_name       VARCHAR(200) NULL,     -- Freitext aus DSV7
    user_id         INT UNSIGNED NULL,     -- Link zu eigenem Vereinsmitglied (users.id)
    swimrankings_id INT UNSIGNED NULL,     -- optional: swimrankings.net ID
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_athlete (lastname, firstname, birth_year, gender),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Ergebnisse aller Athleten (alle Clubs) aus DSV7-Vollimporten
-- Ergänzt competition_results (eigene Schwimmer) um externe Teilnehmer
CREATE TABLE ext_competition_results (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id  INT UNSIGNED NOT NULL,
    athlete_id      INT UNSIGNED NOT NULL,
    discipline      VARCHAR(20) NOT NULL,  -- 'freistil','brust','ruecken','schmetterling','lagen'
    distance        SMALLINT UNSIGNED NOT NULL,
    time_ms         INT UNSIGNED NULL,     -- NULL = DNS/DNF/DQ
    status          ENUM('OK','DNS','DNF','DQ') NOT NULL DEFAULT 'OK',
    placement       SMALLINT UNSIGNED NULL,
    age_group       VARCHAR(50) NULL,
    gender          CHAR(1) NULL,
    is_final        BOOLEAN NOT NULL DEFAULT FALSE,
    dsv_points      SMALLINT UNSIGNED NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athletes(id),
    UNIQUE KEY uq_ext_result (competition_id, athlete_id, discipline, distance, age_group)
);

-- Staffelergebnisse
CREATE TABLE relay_results (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id  INT UNSIGNED NOT NULL,
    discipline      VARCHAR(20) NOT NULL,
    distance        SMALLINT UNSIGNED NOT NULL,
    club_name       VARCHAR(200) NOT NULL,
    time_ms         INT UNSIGNED NULL,
    status          ENUM('OK','DNS','DNF','DQ') NOT NULL DEFAULT 'OK',
    placement       SMALLINT UNSIGNED NULL,
    age_group       VARCHAR(50) NULL,
    gender          CHAR(1) NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    INDEX idx_relay_competition (competition_id)
);

-- Staffelmitglieder
CREATE TABLE relay_members (
    relay_result_id INT UNSIGNED NOT NULL,
    athlete_id      INT UNSIGNED NOT NULL,
    leg             TINYINT UNSIGNED NOT NULL,  -- Position in der Staffel (1–4)
    PRIMARY KEY (relay_result_id, leg),
    FOREIGN KEY (relay_result_id) REFERENCES relay_results(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athletes(id)
);

-- Import-Protokoll
CREATE TABLE import_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source          ENUM('shsv','nsv','dsv','webclub_batch','manual') NOT NULL,
    source_url      VARCHAR(500) NULL,
    filename        VARCHAR(255) NULL,
    status          ENUM('success','skipped','error') NOT NULL,
    competition_id  INT UNSIGNED NULL,
    message         TEXT NULL,
    imported_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE SET NULL
);

-- Score-Cache für Saison-Rankings (optional, für Performance bei Auswertungen)
CREATE TABLE season_scores (
    athlete_id      INT UNSIGNED NOT NULL,   -- NULL = externer Athlet
    user_id         INT UNSIGNED NULL,       -- gesetzt bei eigenem Vereinsmitglied
    season_year     SMALLINT UNSIGNED NOT NULL,
    total_score     INT UNSIGNED NOT NULL DEFAULT 0,
    podiums         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    finals          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    personal_bests  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    club_records    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    recalculated_at DATETIME NOT NULL,
    PRIMARY KEY (athlete_id, season_year),
    FOREIGN KEY (athlete_id) REFERENCES athletes(id) ON DELETE CASCADE
);
```

---

## 4. Architektur (Laravel-Implementierung)

### 4.1 Bereits vorhandene Services (✅)

```
app/Services/
├── Dsv7Parser.php              ✅ DSV7-Text → Domain-Objekte
│                                  Unterstützt: EasyWK 3.x/2.x, Vereins- und
│                                  Wettkampfergebnisliste, Staffeln, DNS/DNF/DQ,
│                                  Windows-1252 Encoding
├── DsvImportService.php        ✅ Orchestrierung: Parse → Deduplizierung → Persist
│                                  (aktuell: nur eigene Vereinsschwimmer via user_id-Matching)
├── RecordCheckService.php      ✅ Prüft ob Ergebnis Vereins- oder Landesrekord bricht
├── CompetitionResultGrouper.php ✅ Zusammenführung mehrerer Wertungsklassen (AK14 + Offene Klasse)
└── WebClubImportService.php    ✅ WebClub-CSV-Import (Wettkampf-Termine)
```

### 4.2 Neue Services (🆕)

```
app/Services/
├── Import/
│   ├── AthleteMatchingService.php    🆕 find-or-create für athletes-Tabelle
│   │                                    Matching: lastname + firstname + birth_year + gender
│   │                                    Optional: DSV-ID-Abgleich mit users.dsv_id
│   ├── FullCompetitionImporter.php   🆕 DSV7-Vollimport (alle Clubs) → ext_competition_results
│   │                                    Nutzt vorhandenen Dsv7Parser
│   └── BatchImporter.php             🆕 Verzeichnis oder ZIP → mehrere DSV7-Dateien
│
├── Crawler/
│   ├── CrawlerInterface.php          🆕 fetchFiles(): iterable<{content, filename, url}>
│   ├── ShsvCrawler.php               🆕 scrapet shsv.de/vereinswettkaempfe
│   ├── NsvCrawler.php                🆕 scrapet nsv-schwimmen.de (URL-Whitelist)
│   └── DsvCrawler.php                🆕 scrapet dsv.de (DM, DJM, …)
│
└── Ranking/
    ├── BestenlisteService.php        🆕 Rankings aus eigener DB (ext_competition_results)
    ├── SaisonAuswertungService.php   🆕 Score-Ranking für eine Saison
    └── WettkampfAuswertungService.php 🆕 Vereins-Bericht für einzelne Veranstaltung
```

### 4.3 Athleten-Matching: Eigene Schwimmer vs. externe Athleten

Das Portal unterscheidet zwei Athleten-Quellen:

**Eigene Schwimmer** (bestehend, unverändert):
- Gespeichert in `users` (role = `schwimmer`)
- Ergebnisse in `competition_results.user_id`
- Matching beim DSV7-Import: `users.dsv_id` → DSV7-Startnummer, Fallback: Name + Jahrgang

**Externe Athleten** (neu):
- Gespeichert in `athletes`
- Ergebnisse in `ext_competition_results.athlete_id`
- Eigene Vereinsmitglieder bekommen `athletes.user_id` gesetzt → Verlinkung beider Welten

```php
// app/Services/Import/AthleteMatchingService.php
class AthleteMatchingService
{
    public function findOrCreate(array $row): Athlete
    {
        // 1. Priorität: DSV-ID-Match mit eigenem Vereinsmitglied
        if (!empty($row['dsv_id'])) {
            $user = User::where('dsv_id', $row['dsv_id'])->first();
            if ($user) {
                return Athlete::firstOrCreate(
                    ['user_id' => $user->id],
                    ['lastname' => $user->lastname, 'firstname' => $user->firstname,
                     'birth_year' => $user->birthYear(), 'gender' => $user->gender]
                );
            }
        }

        // 2. Name + Jahrgang + Geschlecht als Unique-Key
        return Athlete::firstOrCreate(
            ['lastname' => $row['lastname'], 'firstname' => $row['firstname'],
             'birth_year' => $row['birth_year'], 'gender' => $row['gender']],
            ['club_name' => $row['club'], 'nationality' => $row['nationality'] ?? 'GER']
        );
    }
}
```

### 4.4 Deduplizierung: Hash-basierter Schutz

Jede DSV7-Datei erhält beim Import einen SHA-256-Hash. Bereits importierte Dateien werden übersprungen.

```php
// In FullCompetitionImporter.php
$hash = hash('sha256', file_get_contents($filePath));

if (Competition::where('import_hash', $hash)->exists()) {
    ImportLog::create(['source' => $source, 'filename' => $filename,
                       'status' => 'skipped', 'message' => 'Hash bereits vorhanden']);
    return;
}
```

### 4.5 Batch-Import (WebClub-History / manuelle DSV7-Uploads)

```php
// app/Services/Import/BatchImporter.php
class BatchImporter
{
    // Admin lädt ZIP oder mehrere DSV7-Dateien hoch → alle werden verarbeitet
    public function importFromZip(string $zipPath, string $source = 'webclub_batch'): ImportReport;
    public function importFromDirectory(string $path, string $source = 'manual'): ImportReport;
}
```

`ImportReport` enthält: `imported`, `skipped`, `errors[]` (Dateiname + Fehlermeldung).

> **Einmalige Historien-Arbeit:** Bei ~5 Jahren à 15 Wettkämpfen = ca. 75 DSV7-Dateien. Admin exportiert alle aus WebClub (`Veranstaltungen → DSV7-Datei erzeugen`), lädt als ZIP hoch, BatchImporter übernimmt den Rest.

---

## 5. Meisterschaftsebene (`competitions.level`)

Das neue Feld `competitions.level` ermöglicht gewichtete Saison-Rankings.

### 5.1 Werte und Gewichtung

```php
// config/competition_levels.php
return [
    'dsv_dm'    => ['label' => 'Deutsche Meisterschaften',              'weight' => 100],
    'dsv_djm'   => ['label' => 'Deutsche Jahrgangsmeisterschaften',     'weight' => 90],
    'nsv'       => ['label' => 'Norddeutsche Meisterschaften',          'weight' => 70],
    'shsv_lm'   => ['label' => 'Schleswig-Holsteinische LM',           'weight' => 50],
    'shsv_open' => ['label' => 'Offene SHSV-Wettkämpfe',               'weight' => 30],
    'vereins'   => ['label' => 'Vereinswettkampf',                     'weight' => 10],
];
```

### 5.2 Vergabe des Level-Werts

**Beim automatischen Crawl-Import:** Keyword-Erkennung aus dem Veranstaltungsnamen:

```php
private function detectLevel(string $name): string
{
    $n = mb_strtolower($name);
    if (str_contains($n, ' dm ')  || str_contains($n, 'deutsche meisterschaft'))  return 'dsv_dm';
    if (str_contains($n, ' djm ') || str_contains($n, 'jahrgangs'))               return 'dsv_djm';
    if (str_contains($n, 'ndm')   || str_contains($n, 'norddeutsch'))             return 'nsv';
    if (str_contains($n, ' lm ')  || str_contains($n, 'landesmeisterschaft'))     return 'shsv_lm';
    if (str_contains($n, 'shsv')  || str_contains($n, 'schleswig'))               return 'shsv_open';
    return 'vereins';
}
```

**Manuell:** Neues Dropdown-Feld im Admin-Bearbeitungsformular (`competitions.edit`).

> **Risiko:** Keyword-Erkennung ist fehleranfällig. Daher: Level nach automatischem Import im Admin prüfbar machen (Spalte in der Wettkampf-Liste mit schnellem Inline-Edit).

---

## 6. Crawler-Strategie pro Quelle

### 6.1 SHSV (`shsv.de/vereinswettkaempfe`)

- HTML-Seite parsen → alle Links auf `*-Pr.DSV7`-Dateien extrahieren
- Datei herunterladen, SHA-256-Hash prüfen, importieren
- **Taktung:** Laravel Scheduler — Mo + Di (nach Wettkampfwochenenden), Do (Masters)

```php
// routes/console.php oder app/Console/Kernel.php
$schedule->call(fn() => app(ShsvCrawler::class)->run())
         ->weeklyOn(1, '06:00')  // Montag
         ->weeklyOn(2, '06:00')  // Dienstag
         ->weeklyOn(4, '06:00'); // Donnerstag
```

### 6.2 NSV (`nsv-schwimmen.de`)

- Analog zu SHSV; URL-Struktur muss manuell gepflegt werden
- URL-Whitelist in `federations`-Tabelle oder separater Config

### 6.3 DSV (`dsv.de`)

- Strukturiertere Seiten (DM, DJM) — Links auf `*-Pr.DSV7` direkt bei Veranstaltungsseiten
- Crawling weniger häufig nötig (ca. 5–10 Dateien/Jahr)

---

## 7. Admin-Oberfläche (Erweiterungen)

Die bestehende Wettkampf-Verwaltung (`admin/wettkaempfe`) wird erweitert:

### 7.1 Bereits vorhanden (✅)

- Wettkampf anlegen / bearbeiten / löschen
- DSV7-Definitionsdatei importieren (Wettkampffolge, Pflichtzeiten, Meldegelder)
- DSV7-Ergebnisdatei importieren (3-Schritt-Workflow: Upload → Vorschau → Speichern)
- Tabs: Import, Wettkampffolge, Pflichtzeiten, Meldegelder, Ergebnisse, Auswertung, Gruppen, Anmeldungen, Organisation, Meldungen

### 7.2 Neu hinzuzufügen (🆕)

- **Feld `level`** im Bearbeitungsformular (Dropdown mit den 6 Ebenen)
- **Batch-Import-Upload** im Admin: ZIP mit mehreren DSV7-Dateien hochladen
- **Import-Log-Seite:** Zeigt `import_log` mit Status, Dateiname, Zeitstempel, ggf. Fehler
- **Vollständige Ergebnisse** (alle Clubs): neuer Tab "Alle Starts" in der Wettkampf-Detailansicht
  → zeigt `ext_competition_results` mit athleten aus allen Vereinen

---

## 8. Auswertungen und Ranking

### 8.1 Score-Formel (Saison-Ranking)

```
score = dsv_punkte
      + (platz_bonus × wettkampf_gewicht / 10)
      + pb_bonus

platz_bonus:  Platz 1 → 50 | Platz 2 → 30 | Platz 3 → 20
              Platz 4–8 → 10 (Finale) | sonst 0
pb_bonus: 15 Punkte (neue persönliche Bestzeit)
```

`wettkampf_gewicht`: aus `config/competition_levels.php` (10–100).

Die Formel ist konfigurierbar — nie hardcoded.

### 8.2 Bestenliste (intern)

Berechnet aus `ext_competition_results` JOIN `athletes`:

```php
// app/Services/Ranking/BestenlisteService.php
public function get(string $discipline, int $distance, string $gender,
                    string $course = null, int $year = null, int $limit = 25): Collection
{
    return ExtCompetitionResult::with(['athlete', 'competition'])
        ->where(['discipline' => $discipline, 'distance' => $distance, 'gender' => $gender])
        ->where('status', 'OK')
        ->when($course, fn($q) => $q->whereHas('competition', fn($q) => $q->where('course', $course)))
        ->when($year, fn($q) => $q->whereHas('competition', fn($q) => $q->whereYear('date', $year)))
        ->orderBy('time_ms')
        ->limit($limit)
        ->get();
}
```

### 8.3 KI-Textgenerierung (bereits im Portal vorhanden)

Die bestehende KI-Auswertung (`CompetitionController::generateAnalysis()`) nutzt die Claude API bereits. Sie kann direkt auf `ext_competition_results` + `athletes` erweitert werden.

---

## 9. Migrations-Reihenfolge (sichere Umsetzung)

Empfohlene Reihenfolge, um die bestehende DB nicht zu beschädigen:

```
Schritt 1: federations-Tabelle anlegen (neue Tabelle, kein Risiko)
Schritt 2: competitions um level, source_file, source_url, import_hash erweitern
           (alle nullable → bestehende Zeilen unberührt)
Schritt 3: athletes-Tabelle anlegen
Schritt 4: ext_competition_results anlegen
Schritt 5: relay_results + relay_members anlegen
Schritt 6: import_log anlegen
Schritt 7: season_scores anlegen (optional, nur wenn Ranking-Feature live)
```

Jeder Schritt kann einzeln ausgeführt und getestet werden. Die bestehende Funktionalität (Ergebnisse eigener Schwimmer, Tabs, Dashboard) ist nach jedem Schritt vollständig funktionsfähig.

---

## 10. Offene Fragen & Risiken

| Thema | Risiko | Empfehlung |
|---|---|---|
| **Athleten-Matching** | Gleicher Athlet unter verschiedenen Schreibweisen (Anna vs. Anne) | Zunächst `(lastname, firstname, birth_year, gender)` als UK; DSV-ID-Match priorisieren |
| **DSV7 Format 6** | Ältere Dateien noch im Umlauf | `FORMAT`-Zeile prüfen; `Dsv7Parser` erkennt EasyWK 2.x bereits |
| **Staffeln** | Mitglieder-Zuordnung fehleranfällig | Erst Einzelergebnisse vollständig testen; Staffeln im zweiten Schritt |
| **robots.txt / Scraping** | SHSV/NSV könnten Scraping einschränken | Terms prüfen; ggf. direkter Kontakt zu SHSV um Datei-Feed |
| **swimrankings.net** | Kein offizielles API, kann sich ändern | Nur optionale Anreicherung, nicht Primärquelle |
| **WebClub-Export Vollständigkeit** | Admin vergisst einzelne Veranstaltungen | Import-Log nach Batch prüfen; fehlende Saisons anhand Datumsreihen erkennen |
| **`competition.level`-Vergabe** | Keyword-Erkennung aus Veranstaltungsname fehleranfällig | Inline-Edit in der Admin-Wettkampfliste für schnelle Nachkorrektur |
| **Score-Formel** | Gewichtung ist subjektiv | Formel als Konfigurationsdatei — nicht hardcoded |
| **Shared Hosting** | Kein dauerhafter Hintergrundprozess für Crawler | Laravel Scheduler via Cron-Job auf dem Server (`* * * * * php artisan schedule:run`) |
| **`ext_competition_results` vs. `competition_results`** | Zwei parallele Systeme für Ergebnisse | Klar dokumentierte Zuständigkeit: `competition_results` = eigene Schwimmer (Cockpit, PBs, Rekorde); `ext_competition_results` = alle Athleten (Rankings, Vollansicht) |

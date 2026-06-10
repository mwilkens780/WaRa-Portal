# DSV7-Integration & Wettkampf-Lebenszyklus: WaRa-Portal

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

### 1.2 Wo liegen die DSV7-Dateien?

| Ebene | Website | Dateityp | Zugang |
|---|---|---|---|
| **SHSV** | `shsv.de/vereinswettkaempfe` | `*-Pr.DSV7` Ergebnisliste | Öffentlich (HTML-Links) |
| **NSV** | `nsv-schwimmen.de` | Ergebnisliste | Öffentlich (je nach Veranstaltung) |
| **DSV** | `dsv.de` | Ergebnislisten DM, DJM, … | Öffentlich, direkte Dateilinks |

> **Hinweis SHSV:** Ausrichter müssen nach jedem Wettkampf die DSV-Ergebnisdatei einreichen. Die Dateien werden auf `shsv.de/vereinswettkaempfe` verlinkt — kein API-Endpunkt, nur HTML-Scraping möglich.

### 1.3 Offizielle DSV-API für Bestenlisten?

**Nein.** Der DSV stellt keine maschinenlesbare API bereit. swimrankings.net bietet keine offizielle API, wird daher nur als optionale Datenanreicherung betrachtet.

**Empfehlung:** Bestenlisten **aus importierten DSV7-Dateien selbst berechnen** — eigene DB → eigene Bestenliste.

---

## 2. DSV7-Dateiformat (Kurzreferenz)

DSV7-Dateien sind UTF-8- oder Windows-1252-Textdateien. Jede Zeile ist ein Element, Felder durch `;` getrennt.

### 2.1 Dateitypen im Überblick

| Dateityp | Inhalt | Wann genutzt |
|---|---|---|
| `*-Pr.DSV7` | Wettkampfergebnisliste (alle Vereine) | Nach dem Wettkampf: Import |
| `*-VPr.DSV7` | Vereinsergebnisliste (nur eigener Verein) | Nach dem Wettkampf: Import |
| `*-Wk.DSV7` | Wettkampfdefinitionsdatei | Vor dem Wettkampf: Wettkampffolge, Pflichtzeiten, Meldegelder |
| `*-Vm.DSV7` | Vereinsmeldedatei | Vor dem Wettkampf: Meldung beim Ausrichter — **wird generiert** |

### 2.2 Trennzeichen — kritische Konvention

> ⚠️ **DSV Standard 7 verwendet `:` (Doppelpunkt) als Trenner zwischen Schlüsselwort und Feldern,
> dann `;` zwischen den Feldern.** Format: `SATZART:Feld1;Feld2;Feld3`
>
> Bestätigt durch `app/Services/Dsv7Parser.php`:
> ```php
> $colonPos = strpos($line, ':');
> if ($colonPos === false) continue;   // Zeilen ohne ':' werden übersprungen
> $keyword = trim(substr($line, 0, $colonPos));
> $rest    = substr($line, $colonPos + 1);
> $fields  = explode(';', $rest);
> ```
> Zeilen im Format `VERANSTALTUNG;Name;...` (nur Semikola) würden vom Parser **ignoriert**.

### 2.3 Struktur Wettkampfergebnisliste (`*-Pr.DSV7`)

```
FORMAT:Wettkampfergebnisliste;7
ERZEUGER:EasyWk;3.2.1;info@easywk.de
VERANSTALTUNG:NDM 2026;Braunschweig;50;AUTOMATISCH;08.05.2026;10.05.2026
VERANSTALTER:Norddeutscher Schwimmverband e.V.
ABSCHNITT:1;08.05.2026;1. Abschnitt        ← Session-Datum, parsed in ABSCHNITT-Handler
WETTKAMPF:1;V;1;1;200;B;E;W               ← nr;art;abschnitt;legs;strecke;technik;ausübung;geschlecht
WERTUNG:1;V;101;E;0;9999;W;Offene Klasse  ← wkNr;art;wertungsID;typ;ageMin;ageMax;geschl;name
WERTUNG:1;V;102;E;2008;2008;W;Jahrgang 2008
PNERGEBNIS:1;V;101;1;;Müller, Anna;DSV123456;VID;W;2008;OK;SG Wasserratten;VNR;02:28,45;742;
STAFFELERGEBNIS:...
STAFFELMITGLIED:...
DATEIENDE
```

**WETTKAMPF-Felder (new format — Standard ab EasyWk 3.x):**

| Pos | Inhalt | Beispiel |
|---|---|---|
| 0 | WK-Nummer | `1` |
| 1 | Art (V=Vorlauf, F=Finale, E=Entscheidung) | `V` |
| 2 | Abschnitt-Nummer | `1` |
| 3 | Staffel-Legs (1=Einzel) | `1` |
| 4 | Streckenlänge (bei Staffel: pro Lage) | `200` |
| 5 | Technik-Code (F/B/R/S/L) | `B` |
| 6 | Ausübungsart (E=Einzel) | `E` |
| 7 | Geschlecht (M/W/X) | `W` |

**WERTUNG-Felder (new format):**

| Pos | Inhalt | Beispiel |
|---|---|---|
| 0 | WK-Nummer (Referenz zu WETTKAMPF) | `1` |
| 1 | Art | `V` |
| 2 | WertungsID (eindeutig, referenziert PNERGEBNIS) | `101` |
| 3 | Typ | `E` |
| 4 | Jahrgang Min (0 = kein Limit) | `0` |
| 5 | Jahrgang Max (9999 = kein Limit) | `9999` |
| 6 | Geschlecht | `W` |
| 7 | Name der Wertungsklasse | `Offene Klasse` |

### 2.4 Struktur Wettkampfdefinitionsdatei (`*-Wk.DSV7`)

```
FORMAT:Wettkampfdefinitionsliste;7
ERZEUGER:EasyWk;3.2.1;info@easywk.de
VERANSTALTUNG:NDM 2026;Braunschweig;50;AUTOMATISCH;08.05.2026;10.05.2026
VERANSTALTER:Norddeutscher Schwimmverband e.V.
KAMPFGERICHT:Schiedsrichter;Max Mustermann;LSV Niedersachsen
ABSCHNITT:1;08.05.2026;1. Abschnitt
WETTKAMPF:1;E;1;1;200;B;E;W
WERTUNG:1;E;101;E;0;9999;W;Offene Klasse
WERTUNG:1;E;102;E;2008;2008;W;Jahrgang 2008
PFLICHTZEIT:1;E;101;;02:47,32              ← wkNr;art;wertungsID;(meldeschluss);zeit
PFLICHTZEIT:1;E;102;;02:50,20
MELDEGELD:1;E;101;11,00;EUR               ← wkNr;art;wertungsID;betrag;währung
DATEIENDE
```

> Der PFLICHTZEIT-Parser (`Dsv7Parser.php`) erwartet im new format:
> `wkNr;art;wertungsID;meldeschluss;zeit` — das `meldeschluss`-Feld kann leer sein.

### 2.5 Struktur Vereinsmeldedatei (`*-Vm.DSV7`) — wird vom Portal generiert

> ⚠️ **Die exakte Feldstruktur von `ANMELDUNG` und `MELDUNG` folgt DSV Standard 7 Formular 101
> und muss vor dem ersten produktiven Einsatz gegen die offizielle Spezifikation verifiziert werden.**
> Der Dsv7Parser überspringt diese Satzarten (`SKIP_KEYWORDS`), weshalb kein direkter Abgleich
> aus dem bestehenden Code möglich ist.

```
FORMAT:Vereinsmeldung;7
ERZEUGER:WaRa-Portal;1.0;portal@wasserratten.de
VERANSTALTUNG:NDM 2026;Braunschweig;50;AUTOMATISCH;08.05.2026;10.05.2026
VEREIN:SG Wasserratten Norderstedt;WARA-SH;SHSV;GER
ANMELDUNG:Müller;Anna;2008;W;DSV123456;SG Wasserratten Norderstedt;SHSV;GER
MELDUNG:DSV123456;101;00:28,45    ← DSV-ID;wertungsID;meldezeit (⚠ Format zu verifizieren)
MELDUNG:DSV123456;102;00:28,45    ← selbe Zeit, andere Wertungsklasse (Jahrgang)
STAFFELANMELDUNG:4x100F;W;OK;SG Wasserratten Norderstedt   (⚠ Format zu verifizieren)
STAFFELMELDUNG:DSV123456;1
STAFFELMELDUNG:DSV789012;2
STAFFELMELDUNG:DSV345678;3
STAFFELMELDUNG:DSV901234;4
DATEIENDE
```

**Wichtige Unterschiede zur alten Dokumentation:**
- `MELDUNG` referenziert die **WertungsID** (z.B. `101`), nicht die WK-Nummer (`1`)
- Für jede Wertungsklasse (OK + Jahrgang) ist eine separate `MELDUNG`-Zeile nötig

### 2.6 Zeitformat und Speicherung im Portal

DSV7 speichert Zeiten im Format `MM:SS,hh` (Komma als Dezimaltrenner vor Hundertstel).
**Das Portal speichert intern in `time_ms` (Millisekunden).**

```php
// Konversion DSV7 → Portal (in Dsv7Parser.php::parseTime / secCentiToMs):
// "28,45"   → Komma wird zu Punkt → sec=28, centi=45 → 28×1000 + 45×10 = 28 450 ms
// "02:28,45" → min=2, sec=28, centi=45 → (2×60 000) + 28 000 + 450 = 148 450 ms
// "1:02:28,45" → h=1, min=2, sec=28, centi=45 = 3 748 450 ms

// ✅ Implementiert in app/Services/Dsv7Parser.php::parseTime() + secCentiToMs()

// Rückkonversion Portal → DSV7 (für Generator):
public static function msToDs7(int $ms): string
{
    $h  = intdiv($ms, 3_600_000);
    $m  = intdiv($ms % 3_600_000, 60_000);
    $s  = intdiv($ms % 60_000, 1_000);
    $hh = intdiv($ms % 1_000, 10);   // Hundertstel (nicht Millisekunden!)
    return $h > 0
        ? sprintf('%02d:%02d:%02d,%02d', $h, $m, $s, $hh)
        : sprintf('%02d:%02d,%02d', $m, $s, $hh);
}

// Beispiele:
// msToDs7(28_450)   → "00:28,45"
// msToDs7(148_450)  → "02:28,45"
// msToDs7(3_748_450) → "01:02:28,45"
```

> **Geschlecht-Konversion:** DSV7 verwendet `W` (weiblich) und `M` (männlich). Das Portal
> speichert intern `F` (female). Beim Generieren von DSV7-Dateien muss `F` → `W` konvertiert
> werden. Im Parser ist dies in `normalizeGender()` umgekehrt implementiert (`W` → `F`).

### 2.6 Disziplin-Mapping (DSV7 ↔ Portal)

| DSV7 | Portal (`discipline`) | Bezeichnung |
|---|---|---|
| `F` | `freistil` | Freistil |
| `B` | `brust` | Brust |
| `R` | `ruecken` | Rücken |
| `S` | `schmetterling` | Schmetterling |
| `L` | `lagen` | Lagen |

✅ Implementiert in `app/Services/Dsv7Parser.php::STROKE_MAP`.

### 2.7 WertungsID speichern (🔄 ALTER TABLE nötig für Meldedatei-Generator)

Der `MeldedateiGenerator` braucht die **WertungsID** (aus dem `WERTUNG`-Record der importierten
`*-Wk.DSV7`), nicht die WK-Nummer. Diese muss beim Import gespeichert werden:

```sql
ALTER TABLE competition_events
    ADD COLUMN dsv_wertungs_id INT UNSIGNED NULL
        COMMENT 'WertungsID aus DSV7 WERTUNG-Record — für Meldedatei-Generator'
        AFTER event_number;
```

Beim Import einer `*-Wk.DSV7` über `parseMeetDefinition()` muss `$wertungMap[$wid]` die
`$wertungsId` in `dsv_wertungs_id` speichern. Ohne dieses Feld fällt der Generator auf die
WK-Nummer zurück (Fallback, möglicherweise nicht kompatibel mit EasyWk/WebClub).

---

## 3. Kompletter Wettkampf-Lebenszyklus

```
┌──────────────────────────────────────────────────────────────────────────┐
│                    WETTKAMPF-LEBENSZYKLUS IM PORTAL                       │
└──────────────────────────────────────────────────────────────────────────┘

VERGANGENE VERANSTALTUNGEN (Nacherfassung)
──────────────────────────────────────────
  Ausschreibung (PDF)      ──► Tab: Ausschreibung (Ansicht / Upload)
  DSV7 *-Wk.DSV7          ──► Tab: Wettkampffolge, Pflichtzeiten, Meldegelder
  DSV7 *-Pr.DSV7 / *-VPr  ──► Tab: Ergebnisse (Import + Filterung Wasserratten)
  → Ergebnisse, PBs, Rekorde in DB

ZUKÜNFTIGE VERANSTALTUNGEN (Extern — Meisterschaften / Einladung)
──────────────────────────────────────────────────────────────────
  1. Ausschreibung einlesen   Tab: Ausschreibung
     DSV7 *-Wk.DSV7 importieren → Wettkampffolge, Pflichtzeiten, Meldegelder, Kampfgericht
  2. Interne Abfrage          Tab: Anmeldungen (merged mit Gruppen)
     → Welche Schwimmer nehmen teil? Gruppen + Einzelpersonen definieren
     → Abfragetext + Anhang → Schwimmer-Cockpit (Zu-/Absage)
  3. Meldung erstellen        Tab: Meldungen
     → Trainer wählt Strecken pro Schwimmer (mit Validierung)
     → Staffelmeldungen definieren
     → DSV7 Vereinsmeldedatei (*-Vm.DSV7) generieren → per E-Mail an Ausrichter
  4. Wettkampf               ggf. Bearbeitung vor Ort
  5. Ergebnisse importieren   Tab: Ergebnisse
     DSV7 *-Pr.DSV7 oder *-VPr.DSV7 → Import, Filterung auf Verein

ZUKÜNFTIGE VERANSTALTUNGEN (Eigen — Vereinswettkampf / Einladung)
──────────────────────────────────────────────────────────────────
  1. Wettkampf anlegen        Formular mit Stammdaten + Wettkampffolge
  2. Ausschreibung generieren Tab: Ausschreibung → PDF-Generator
     → DSV7 *-Wk.DSV7 generieren → an Verbände / WebClub
  3. Meldungen empfangen      Tab: Meldungen (eingehende DSV7 Vereinsmeldungen)
  4. Wettkampf durchführen    (extern in WebClub / EasyWk)
  5. Ergebnisse importieren   Tab: Ergebnisse

AUSWERTUNGEN (beide Typen)
──────────────────────────
  → Tab: Auswertung (KI-generierter Wettkampfbericht)
  → Bestenlisten, Saisonranking, Athletenprofil
```

### 3.1 Tab-Struktur der Wettkampf-Detailansicht (Zielzustand)

| Tab | Status | Inhalt |
|---|---|---|
| Ausschreibung | 🆕 | PDF-Upload/Anzeige; strukturierte Metadaten aus PDF; Generator für eigene Veranstaltungen |
| Wettkampffolge | ✅ | DSV7 *-Wk.DSV7 Import; Abschnitte, Disziplinen |
| Pflichtzeiten | ✅ | Aus *-Wk.DSV7; Tabelle pro Disziplin/Jahrgang |
| Meldegelder | ✅ | Aus *-Wk.DSV7; Einzel- und Staffelgebühren |
| Kampfgericht | 🆕 | Schiedsrichter, Kampfrichter, Ausrichter-Kontakt |
| Anmeldungen | ✅🔄 | **Zusammengelegt aus „Gruppen" + bisherigem „Anmeldungen"-Tab**; Gruppen/Schwimmer definieren; interne Abfrage starten, überwachen, erinnern |
| Meldungen | ✅🔄 | Bisheriger Teilnehmerstatus **erweitert um**: Streckenauswahl pro Schwimmer; Staffelmeldungen; Validierung; DSV7 Meldedatei-Generator |
| Ergebnisse | ✅ | DSV7 *-Pr / *-VPr Import; automatische Vereinsfilterung; PBs, Rekorde |
| Auswertung | ✅ | KI-Bericht (Claude API); Ergebnisübersicht |
| Organisation | ✅ | Freie Notizen (Anreise, Hotel, Kontakte) |
| Import | ✅ | DSV7 Ergebnisdatei-Upload (3-Schritt-Workflow) |

> **Hinweis Zusammenlegung:** Der bisherige „Gruppen"-Tab und „Anmeldungen"-Tab werden zu **einem Tab „Anmeldungen"** zusammengeführt. Oben: Gruppen/Schwimmer-Definition. Darunter: Abfrage-Workflow (Draft → Aktivieren → Überwachen → Schließen).

---

## 4. Datenbankschema

### 4.1 Übersicht — nach Vollausbau

```
federations ──< competitions ──< competition_events (Wettkampffolge)
                competitions ──< competition_results       >── users (eigene Schwimmer)
                competitions ──< ext_competition_results   >── athletes (alle Athleten)
                competitions ──< competition_signup_requests ──< competition_signup_responses >── users
                competitions ──< competition_entries       >── users (Einzelmeldungen)
                competitions ──< competition_relay_entries      (Staffelmeldungen)
                competition_relay_entries ──< competition_relay_entry_members >── users
                athletes ──(optional)── users
                import_log
```

### 4.2 Bestehende Tabellen (✅ vorhanden)

#### `competitions`
```sql
id, name, location, date, date_end, meldeschluss,
type ENUM('vereinsintern','regional','national','international',
          'meisterschaften','einladung','nop','dms','shsv'),
description, organizer,
course ENUM('LCM','SCM'),       -- LCM = 50m Langbahn, SCM = 25m Kurzbahn
season_id,
organisation_notes JSON,
created_at, updated_at
```

#### `competition_events` (Wettkampffolge / DSV7 WETTKAMPF-Zeilen)
```sql
id, competition_id,
event_number SMALLINT,          -- WK-Nummer (z.B. 1, 2, 3 …)
session_number, session_date, session_name,
discipline VARCHAR,             -- 'freistil','brust','ruecken','schmetterling','lagen'
distance INT,
gender ENUM('M','F','X'),
age_min, age_max,
age_group VARCHAR(50),          -- 'OK','Junioren','2009','mixed' …
qualifying_time_ms INT,         -- Pflichtzeit in ms (0/NULL = keine)
meldegeld DECIMAL,
created_at, updated_at
```

#### `competition_results` (Ergebnisse eigener Schwimmer)
```sql
id, competition_id,
user_id INT NOT NULL,           -- nur eigene Schwimmer
discipline VARCHAR, distance INT,
time_ms INT,                    -- 0 = DNS/DNF/DQ (notes enthält Status-Kürzel)
placement INT, is_personal_best BOOLEAN,
age_group VARCHAR, gender CHAR(1), notes VARCHAR,
breaks_vereinsrekord BOOLEAN, breaks_landesrekord BOOLEAN, is_final BOOLEAN
```

#### `competition_signup_requests` / `competition_signup_responses`
✅ Anmeldeworkflow: Draft → Aktiv → Geschlossen; Schwimmer-Cockpit-Benachrichtigung

#### `users`
```sql
-- DSV7-relevante Felder:
id, firstname, lastname, birth_date,
gender ENUM('M','F'),
dsv_id VARCHAR(20) UNIQUE,      -- DSV-Startnummer
role ENUM('admin','trainer','schwimmer','elternteil')
```

### 4.3 Geplante Migrationen (🔄 ALTER TABLE — keine Daten gehen verloren)

```sql
-- Migration: add_import_and_competition_fields_to_competitions
ALTER TABLE competitions
    ADD COLUMN federation_id        TINYINT UNSIGNED NULL
        COMMENT 'shsv=1, nsv=2, dsv=3 — NULL für eigene Veranstaltungen'
        AFTER season_id,
    ADD COLUMN level                VARCHAR(20) NULL
        COMMENT 'dsv_dm|dsv_djm|nsv|shsv_lm|shsv_open|vereins'
        AFTER federation_id,
    ADD COLUMN ausrichter           VARCHAR(255) NULL
        AFTER level,
    ADD COLUMN venue_details        JSON NULL
        COMMENT '{"pool_length":50,"lanes":8,"depth":"2-3.8m","temp":25}'
        AFTER ausrichter,
    ADD COLUMN kampfgericht         JSON NULL
        COMMENT '[{"role":"Schiedsrichter","name":"Max Müller","club":"..."}]'
        AFTER venue_details,
    ADD COLUMN contact_info         JSON NULL
        COMMENT '{"melde_email":"...","melde_name":"...","meldeschluss_note":"..."}'
        AFTER kampfgericht,
    ADD COLUMN announcement_pdf_path VARCHAR(255) NULL
        AFTER contact_info,
    ADD COLUMN source_file          VARCHAR(255) NULL AFTER announcement_pdf_path,
    ADD COLUMN source_url           VARCHAR(500) NULL AFTER source_file,
    ADD COLUMN import_hash          CHAR(64) NULL UNIQUE
        COMMENT 'SHA-256 des DSV7-Dateiinhalts'
        AFTER source_url;

ALTER TABLE competitions ADD INDEX idx_import_hash (import_hash);
```

> Alle neuen Felder sind nullable — bestehende Zeilen bleiben unberührt.

### 4.4 Neue Tabellen (🆕)

```sql
-- Verbände
CREATE TABLE federations (
    id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug    VARCHAR(10) NOT NULL UNIQUE,
    name    VARCHAR(100) NOT NULL,
    url     VARCHAR(255) NULL
);
INSERT INTO federations (slug, name, url) VALUES
    ('shsv', 'Schleswig-Holsteinischer Schwimmverband', 'https://shsv.de'),
    ('nsv',  'Norddeutscher Schwimmverband',            'https://nsv-schwimmen.de'),
    ('dsv',  'Deutscher Schwimm-Verband',               'https://dsv.de');

-- Externe Athleten (alle Clubs aus DSV7-Importen)
CREATE TABLE athletes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lastname        VARCHAR(100) NOT NULL,
    firstname       VARCHAR(100) NOT NULL,
    birth_year      SMALLINT UNSIGNED NOT NULL,
    gender          ENUM('M','F','X') NOT NULL,
    nationality     CHAR(3) NULL,
    club_name       VARCHAR(200) NULL,
    user_id         INT UNSIGNED NULL,  -- Link zu eigenem Vereinsmitglied
    swimrankings_id INT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_athlete (lastname, firstname, birth_year, gender),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Vollständige Ergebnisse aus DSV7 (alle Clubs)
CREATE TABLE ext_competition_results (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id  INT UNSIGNED NOT NULL,
    athlete_id      INT UNSIGNED NOT NULL,
    discipline      VARCHAR(20) NOT NULL,
    distance        SMALLINT UNSIGNED NOT NULL,
    time_ms         INT UNSIGNED NULL,
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

-- Einzelmeldungen (Trainer wählt Strecken für Schwimmer)
CREATE TABLE competition_entries (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id        INT UNSIGNED NOT NULL,
    user_id               INT UNSIGNED NOT NULL,
    competition_event_id  INT UNSIGNED NULL,        -- Link zur competition_events-Zeile
    discipline            VARCHAR(20) NOT NULL,
    distance              SMALLINT UNSIGNED NOT NULL,
    gender                CHAR(1) NOT NULL,
    age_group             VARCHAR(50) NULL,          -- Wertungsklasse (z.B. 'OK','2009')
    entry_time_ms         INT UNSIGNED NULL,         -- beste Vereinszeit als Meldezeit
    status                ENUM('entered','scratched') NOT NULL DEFAULT 'entered',
    created_by_id         INT UNSIGNED NULL,         -- Trainer der die Meldung erstellt hat
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_entry (competition_id, user_id, discipline, distance, age_group),
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (competition_event_id) REFERENCES competition_events(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Staffelmeldungen
CREATE TABLE competition_relay_entries (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id        INT UNSIGNED NOT NULL,
    competition_event_id  INT UNSIGNED NULL,
    discipline            VARCHAR(20) NOT NULL,
    distance              SMALLINT UNSIGNED NOT NULL,
    gender                ENUM('M','F','mixed') NOT NULL,
    age_group             VARCHAR(50) NULL,
    entry_time_ms         INT UNSIGNED NULL,
    status                ENUM('entered','scratched') NOT NULL DEFAULT 'entered',
    notes                 VARCHAR(255) NULL,
    created_by_id         INT UNSIGNED NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_event_id) REFERENCES competition_events(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Staffelmitglieder (Meldung, nicht Ergebnis)
CREATE TABLE competition_relay_entry_members (
    relay_entry_id  INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    position        TINYINT UNSIGNED NOT NULL,  -- Position 1–4
    PRIMARY KEY (relay_entry_id, position),
    FOREIGN KEY (relay_entry_id) REFERENCES competition_relay_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Staffelergebnisse (Import aus DSV7)
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
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
);

-- Staffelmitglieder (Ergebnis)
CREATE TABLE relay_members (
    relay_result_id INT UNSIGNED NOT NULL,
    athlete_id      INT UNSIGNED NOT NULL,
    leg             TINYINT UNSIGNED NOT NULL,
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

-- Score-Cache für Saison-Rankings
CREATE TABLE season_scores (
    athlete_id      INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
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

## 5. Architektur (Laravel-Implementierung)

### 5.1 Bestehende Services (✅)

```
app/Services/
├── Dsv7Parser.php               ✅ DSV7-Text → Domain-Objekte
│                                   Unterstützt: EasyWK 3.x/2.x, Ergebnis-,
│                                   Definitions- und Vereinsergebnislisten,
│                                   Staffeln, DNS/DNF/DQ, Windows-1252
├── DsvImportService.php         ✅ Orchestrierung: Parse → Deduplizierung → Persist
│                                   (aktuell: nur eigene Schwimmer via user_id-Matching)
├── RecordCheckService.php       ✅ Prüft ob Ergebnis Vereins- oder Landesrekord bricht
├── CompetitionResultGrouper.php ✅ Zusammenführung mehrerer Wertungsklassen
└── WebClubImportService.php     ✅ WebClub-CSV-Import (Wettkampf-Termine)
```

### 5.2 Neue Services (🆕)

```
app/Services/
│
├── Import/
│   ├── AthleteMatchingService.php      🆕 find-or-create athletes; DSV-ID-Match mit users
│   ├── FullCompetitionImporter.php     🆕 DSV7-Vollimport (alle Clubs) → ext_competition_results
│   └── BatchImporter.php              🆕 ZIP / Verzeichnis → mehrere DSV7-Dateien
│
├── Crawler/
│   ├── CrawlerInterface.php            🆕 fetchFiles(): iterable<{content, filename, url}>
│   ├── ShsvCrawler.php                 🆕 scrapet shsv.de/vereinswettkaempfe
│   ├── NsvCrawler.php                  🆕 scrapet nsv-schwimmen.de (URL-Whitelist)
│   └── DsvCrawler.php                  🆕 scrapet dsv.de (DM, DJM, …)
│
├── Competition/
│   ├── EntryService.php               🆕 Einzelmeldungen + Staffeln speichern/aktualisieren
│   ├── EntryValidationService.php     🆕 Kollisionsprüfung, Altersklassen-Validierung
│   ├── MeldedateiGenerator.php        🆕 competition_entries → DSV7 *-Vm.DSV7
│   ├── DefinitionsdateiGenerator.php  🆕 competition_events → DSV7 *-Wk.DSV7
│   └── AusschreibungGenerator.php     🆕 Stammdaten + events → Ausschreibungs-PDF
│
└── Ranking/
    ├── BestenlisteService.php         🆕 Rankings aus ext_competition_results
    ├── SaisonAuswertungService.php    🆕 Score-Ranking für eine Saison
    └── WettkampfAuswertungService.php 🆕 Vereins-Bericht für einzelne Veranstaltung
```

### 5.3 Athleten-Matching

```php
// app/Services/Import/AthleteMatchingService.php
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
```

### 5.4 Deduplizierung: SHA-256-Hash

```php
$hash = hash('sha256', file_get_contents($filePath));
if (Competition::where('import_hash', $hash)->exists()) {
    ImportLog::create(['status' => 'skipped', ...]);
    return;
}
```

---

## 6. Vergangene Veranstaltungen (Nacherfassung)

### 6.1 Ziel

Alle historischen Wettkämpfe (Vereins- und Verbandsebene) sollen in der DB erfasst sein, damit:
- Bestzeiten und Entwicklungen für jeden Schwimmer abrufbar sind
- Vereinsrekorde lückenlos dokumentiert sind
- Saison-Auswertungen erstellt werden können

### 6.2 Import-Workflows

**Manueller Upload (✅ bereits im Portal)**

1. Admin öffnet Wettkampf → Tab „Import"
2. DSV7 `*-Pr.DSV7` oder `*-VPr.DSV7` hochladen
3. 3-Schritt-Workflow: Datei parsen → Vorschau (Club-Matching) → Speichern
4. System filtert automatisch auf SG Wasserratten Norderstedt

**Batch-Import aus WebClub-History (🆕)**

WebClub bietet **kein „Alles exportieren"** — jede Veranstaltung muss einzeln exportiert werden:
```
WebClub: Veranstaltungen → Veranstaltung öffnen
→ DSV7-Datei erzeugen → Typ: Wettkampfergebnisliste → Download
```
Bei ~5 Jahren à 15 Wettkämpfen ≈ 75 Dateien. Einmalige Arbeit, danach Cronjob.

```php
// app/Services/Import/BatchImporter.php
public function importFromZip(string $zipPath, string $source = 'webclub_batch'): ImportReport;
public function importFromDirectory(string $path, string $source = 'manual'): ImportReport;
```

**Automatischer Scraper-Import (🆕)**

```php
// Laravel Scheduler (routes/console.php)
Schedule::call(fn() => app(ShsvCrawler::class)->run())
         ->weeklyOn(1, '06:00')   // Montag
         ->weeklyOn(2, '06:00')   // Dienstag
         ->weeklyOn(4, '06:00');  // Donnerstag (Masters)
```

SHSV-Crawler: HTML-Seite parsen → alle `*-Pr.DSV7`-Links extrahieren → Hash-Prüfung → Import.

### 6.3 Filterung auf Vereinsergebnisse

Beim Import werden **automatisch nur Ergebnisse der SG Wasserratten Norderstedt** in `competition_results` (eigene Schwimmer) gespeichert. Erkennungslogik:

```php
// In DsvImportService.php:
private const OWN_CLUB_NAMES = [
    'SG Wasserratten', 'Wasserratten Norderstedt', 'SGW Norderstedt', 'SG Wasserratten Norderstedt'
];
```

Alle Athleten (alle Clubs) werden ggf. in `ext_competition_results` gespeichert.

---

## 7. Externe Wettkämpfe — Teilnahme-Workflow

Vollständiger Workflow vom „Wettkampf gefunden" bis „Ergebnisse importiert".

### 7.1 Schritt 1: Ausschreibung erfassen (Tab „Ausschreibung")

**Tab-Inhalt:**
- PDF-Upload: Ausschreibungs-PDF hochladen → Speicherung unter `competitions.announcement_pdf_path`
- PDF-Anzeige: eingebetteter PDF-Viewer im Tab
- Strukturierte Metadaten aus der Ausschreibung erfassen (Formularfelder):
  - Veranstalter, Ausrichter
  - Kontaktdaten Meldeanschrift (E-Mail, Name)
  - Meldeschluss-Datum (→ `competitions.meldeschluss`)
  - Maximale Teilnehmerzahl (falls begrenzt)
  - Zahlungsdaten für Meldegeld
  - Besondere Hinweise / Allgemeine Bestimmungen

**DSV7 Definitionsdatei importieren:**

Parallel zum PDF kann eine `*-Wk.DSV7`-Datei importiert werden. Diese befüllt automatisch:
- Tab „Wettkampffolge" (competition_events)
- Tab „Pflichtzeiten" (qualifying_time_ms)
- Tab „Meldegelder" (meldegeld)
- Tab „Kampfgericht" (kampfgericht JSON)

```
Quelle für *-Wk.DSV7:
  nsv-schwimmen.de → Wettkampffolge als DSV-Datei zum Download (laut NDM-Ausschreibung)
  shsv.de/vereinswettkaempfe → verlinkt Definitionsdateien
```

### 7.2 Schritt 2: Kampfgericht (Tab „Kampfgericht")

Enthält aus der DSV7 Definitionsdatei oder manuell:
- Schiedsrichter (Name, Verband)
- Kampfrichter-Obmann
- Ausrichter-Kontakt
- Technische Details: Bahnlänge, Bahnen, Zeitmessanlage, Wassertemperatur

Gespeichert in `competitions.kampfgericht JSON` und `competitions.venue_details JSON`.

Beispiel aus NDM 2026:
```json
{
  "venue": {
    "name": "Sportbad Heidberg",
    "address": "Sachsendamm 10, 38124 Braunschweig",
    "pool_length": 50,
    "lanes": 8,
    "depth": "2.00-3.80m",
    "temp": 25,
    "timing": "elektronisch"
  },
  "officials": [
    { "role": "Schiedsrichter", "name": "...", "club": "LSV Niedersachsen" }
  ]
}
```

### 7.3 Schritt 3: Interne Abfrage (Tab „Anmeldungen")

**Tab ist die Zusammenlegung des bisherigen „Gruppen"- und „Anmeldungen"-Tabs.**

**Oberer Bereich — Teilnehmerkreis definieren:**
- Trainingsgruppen auswählen (Multi-Checkbox, wie bisher im Gruppen-Tab)
- Individuelle Schwimmer zusätzlich auswählen
- Trainer/Admin definiert so den Kreis der potenziell startenden Schwimmer

**Unterer Bereich — Interne Abfrage:**

Phasen des ✅ bereits implementierten Signup-Workflows:

| Phase | Aktion | Wer | Status |
|---|---|---|---|
| **Entwurf** | Nachricht + Anhang verfassen, Frist setzen | Trainer | `draft` |
| **Aktiv** | Abfrage starten → Schwimmer-Cockpit zeigt Aufforderung | Trainer | `active` |
| **Monitoring** | Antwortstatus überwachen; Erinnerung an nicht Geantwortet | Trainer | `active` |
| **Geschlossen** | Abfrage schließen → Teilnehmerliste fest | Trainer | `closed` |

Geschlossene Abfrage: Liste der Zusagen → Grundlage für Tab „Meldungen".

### 7.4 Schritt 4: Meldungen erstellen (Tab „Meldungen" — erweitert)

**Tab-Struktur:**

```
┌─ Übersicht ──────────────────────────────────────────────────────────────┐
│  X Schwimmer zugesagt  ·  Y Einzelmeldungen  ·  Z Staffeln              │
│  [ DSV7 Meldedatei generieren ]  [ Herunterladen ]                       │
└──────────────────────────────────────────────────────────────────────────┘

┌─ Schwimmer: Anna Müller (2008, W) ──────────────────────────────────────┐
│  Offene Klasse:                                                           │
│  ☑ WK 01 · 200m Brust     Meldezeit: 02:28,45 [aus DB] [Pflichtzeit: 02:47,32 ✓] │
│  ☑ WK 05 · 50m Rücken     Meldezeit: 00:33,85 [aus DB] [Pflichtzeit: 00:33,32 ✗ fehlt 0,53s] │
│  ☐ WK 07 · 100m Freistil  Meldezeit: [keine Vereinszeit] ──────────────── │
│  Jahrgangs-Wertung (2008):                                                │
│  ☑ WK 01 · 200m Brust     (automatisch aus OK-Meldung)                   │
│                                                                           │
│  ⚠ WK 01 (Vorlauf Sa 09:30) und WK 05 (Vorlauf Sa 09:35) — kein Zeitpuffer │
└──────────────────────────────────────────────────────────────────────────┘

┌─ Schwimmer: Ben Schmidt (2010, M) ─────────────────────────────────────┐
│  ...                                                                      │
└──────────────────────────────────────────────────────────────────────────┘

┌─ Staffeln ──────────────────────────────────────────────────────────────┐
│  WK 22 · 4×100m Freistil weiblich                                       │
│  Position 1: Anna Müller   Position 2: Lisa Weber                        │
│  Position 3: Sara Braun    Position 4: [nicht besetzt]                   │
│  Meldezeit: [automatisch aus Einzelzeiten berechnen]                     │
└──────────────────────────────────────────────────────────────────────────┘
```

**Meldezeit-Logik:**

1. Beste Vereinszeit aus `competition_results` und `swimming_times` für diese Disziplin/Distanz
2. Falls keine Vereinszeit vorhanden: Feld leer, Trainer kann manuell eintragen
3. Pflichtzeit-Check: Meldezeit < Pflichtzeit → grünes Häkchen; Meldezeit ≥ Pflichtzeit → rote Warnung mit Differenz

**Wertungsklassen-Logik:**

```php
// Für jeden Schwimmer werden die relevanten Wertungsklassen aus competition_events ermittelt:
// 1. Offene Klasse (OK) — immer anzeigen wenn vorhanden
// 2. Juniorenklasse — wenn birth_year in [2006-2008 M] oder [2007-2008 W]
// 3. Jahrgangsklasse — exakter Jahrgang des Schwimmers (z.B. '2009')
// Duplikate beim Generieren der Meldedatei: OK-Meldung impliziert Jahrgangs-Wertung
```

### 7.5 Validierung und Warnungen

```php
// app/Services/Competition/EntryValidationService.php

public function validate(int $userId, int $competitionId): array
{
    $warnings = [];
    $entries = CompetitionEntry::with('competitionEvent')
        ->where(['competition_id' => $competitionId, 'user_id' => $userId])
        ->get();

    foreach ($entries as $entry) {
        // 1. Pflichtzeit nicht erreicht
        if ($entry->competitionEvent?->qualifying_time_ms > 0
            && $entry->entry_time_ms >= $entry->competitionEvent->qualifying_time_ms) {
            $warnings[] = [
                'type'    => 'qualifying_time',
                'entry'   => $entry,
                'message' => "Pflichtzeit für WK {$entry->competitionEvent->event_number} nicht erreicht",
            ];
        }

        // 2. Zeitkollision: zwei Starts innerhalb desselben Abschnitts mit <30 Min Abstand
        foreach ($entries as $other) {
            if ($other->id === $entry->id) continue;
            if ($entry->competitionEvent?->session_number === $other->competitionEvent?->session_number) {
                $warnings[] = [
                    'type'    => 'session_conflict',
                    'entries' => [$entry, $other],
                    'message' => "WK {$entry->competitionEvent->event_number} und WK {$other->competitionEvent->event_number} im selben Abschnitt",
                ];
            }
        }

        // 3. Falsche Altersklasse
        $user = User::find($userId);
        if ($user && $entry->age_group && is_numeric($entry->age_group)) {
            $swimmerYear = $user->birth_date?->year;
            if ($swimmerYear && (int)$entry->age_group !== $swimmerYear) {
                $warnings[] = [
                    'type'    => 'age_class_mismatch',
                    'entry'   => $entry,
                    'message' => "Jahrgang {$swimmerYear} passt nicht zu Wertungsklasse {$entry->age_group}",
                ];
            }
        }
    }

    return $warnings;
}
```

### 7.6 DSV7 Meldedatei-Generator

```php
// app/Services/Competition/MeldedateiGenerator.php

public function generate(Competition $competition): string
{
    $entries = CompetitionEntry::with(['user', 'competitionEvent'])
        ->where('competition_id', $competition->id)
        ->where('status', 'entered')
        ->get()
        ->groupBy('user_id');

    $relayEntries = CompetitionRelayEntry::with(['members.user'])
        ->where('competition_id', $competition->id)
        ->where('status', 'entered')
        ->get();

    // DSV7 erwartet W (weiblich); Portal speichert F (female) → Konversion
    $genderDs7 = fn(string $g): string => $g === 'F' ? 'W' : $g;

    // Korrekte DSV7-Trenner: SATZART:Feld1;Feld2;...
    $lines = [];
    $lines[] = 'FORMAT:Vereinsmeldung;7';
    $lines[] = 'ERZEUGER:WaRa-Portal;1.0;portal@wasserratten.de';
    $lines[] = 'VERANSTALTUNG:' . implode(';', [
        $competition->name,
        $competition->location,
        $competition->course === 'LCM' ? '50' : '25',
        'AUTOMATISCH',
        $competition->date->format('d.m.Y'),
        ($competition->date_end ?? $competition->date)->format('d.m.Y'),
    ]);
    $lines[] = 'VEREIN:SG Wasserratten Norderstedt;WARA-SH;SHSV;GER';

    foreach ($entries as $userId => $userEntries) {
        $user = $userEntries->first()->user;
        // ⚠ ANMELDUNG-Felder nach DSV7 Formular 101 verifizieren
        $lines[] = 'ANMELDUNG:' . implode(';', [
            $user->lastname, $user->firstname,
            $user->birth_date?->year ?? '',
            $genderDs7($user->gender),   // F → W
            $user->dsv_id ?? '',
            'SG Wasserratten Norderstedt', 'SHSV', 'GER',
        ]);
        foreach ($userEntries as $entry) {
            $zeit = $entry->entry_time_ms
                ? self::msToDs7($entry->entry_time_ms)
                : '99:99,99';
            // MELDUNG referenziert WertungsID (nicht WK-Nummer).
            // dsv_wertungs_id muss beim Import aus *-Wk.DSV7 in competition_events gespeichert werden.
            // ⚠ Feldstruktur nach DSV7 Formular 101 verifizieren
            $wertungsId = $entry->competitionEvent?->dsv_wertungs_id
                          ?? $entry->competitionEvent?->event_number;  // Fallback
            $lines[] = "MELDUNG:{$user->dsv_id};{$wertungsId};{$zeit}";
        }
    }

    foreach ($relayEntries as $relay) {
        $strokeCode = strtoupper(
            array_flip(Dsv7Parser::STROKE_MAP)[$relay->discipline] ?? 'F'
        );
        $gDs7 = $relay->gender === 'mixed' ? 'X' : $genderDs7($relay->gender);
        // ⚠ STAFFELANMELDUNG-Felder nach DSV7 Formular 102 verifizieren
        $wkNr = $relay->competitionEvent?->event_number ?? '';
        $lines[] = "STAFFELANMELDUNG:{$wkNr};{$strokeCode};{$gDs7};SG Wasserratten Norderstedt";
        foreach ($relay->members->sortBy('position') as $member) {
            $lines[] = "STAFFELMELDUNG:{$member->user->dsv_id};{$member->position}";
        }
    }

    $lines[] = 'DATEIENDE';
    return implode("\r\n", $lines);
}
```

Die generierte Datei wird als `NDM-2026-Wasserratten-Vm.DSV7` zum Download angeboten und per E-Mail an die Meldeanschrift verschickt.

### 7.7 Schritt 5: Ergebnisse importieren (Tab „Ergebnisse")

Nach dem Wettkampf:
1. DSV7 `*-VPr.DSV7` (Vereinsergebnisliste) **oder** `*-Pr.DSV7` (Vollständig) hochladen
2. Automatische Filterung auf SG Wasserratten Norderstedt (bereits implementiert ✅)
3. Schwimmer werden per DSV-ID oder Name+Jahrgang gematcht
4. PBs, Vereinsrekorde, Landesrekorde werden automatisch erkannt ✅

---

## 8. Eigene Wettkämpfe organisieren

### 8.1 Workflow

```
1. Wettkampf anlegen (admin/wettkaempfe/neu)
   → Stammdaten: Name, Datum, Ort, Typ (vereinsintern/einladung), Bahnlänge

2. Wettkampffolge definieren (Tab Wettkampffolge)
   → Abschnitte + Disziplinen anlegen (Formular, je Zeile ein Wettkampf)
   → Alternativ: DSV7 *-Wk.DSV7 einer Vorjahresveranstaltung als Vorlage importieren

3. Pflichtzeiten festlegen (Tab Pflichtzeiten, optional)
   → Pro Disziplin/Wertungsklasse eine Pflichtzeit eintragen

4. Meldegelder festlegen (Tab Meldegelder, optional)

5. Kampfgericht eintragen (Tab Kampfgericht)
   → Schiedsrichter, Kampfrichter, Ausrichter-Kontakt

6. Ausschreibung generieren (Tab Ausschreibung)
   → PDF aus allen eingetragenen Daten generieren
   → DSV7 *-Wk.DSV7 generieren → an WebClub / Verbände senden

7. Meldungen empfangen (Tab Meldungen — eingehende *-Vm.DSV7)
   → Meldedateien der teilnehmenden Vereine importieren (wird in Abschnitt 8.4 beschrieben)

8. Ergebnisse nacherfassen (Tab Ergebnisse)
   → Nach dem Wettkampf DSV7 *-Pr.DSV7 importieren
```

### 8.2 Ausschreibungs-Generator (PDF)

Basiert auf den strukturierten Daten der Wettkampf-Detailansicht. Das folgende Beispiel orientiert sich an der NDM-2026-Ausschreibung.

**Erforderliche Felder:**

| Feld | DB-Spalte | Anmerkung |
|---|---|---|
| Wettkampfname | `competitions.name` | z.B. „Vereinsmeisterschaften 2026" |
| Datum | `date`, `date_end` | |
| Ort | `location` | |
| Veranstaltungsort | `venue_details.name` | Hallenname |
| Adresse | `venue_details.address` | |
| Bahnlänge | `course` | LCM=50m / SCM=25m |
| Bahnen / Tiefe / Temp | `venue_details` | JSON |
| Veranstalter | `organizer` | |
| Ausrichter | `ausrichter` | |
| Kampfgericht | `kampfgericht` | JSON |
| Wettkampffolge | `competition_events` | Abschnitte, Wochentage, Uhrzeiten |
| Pflichtzeiten | `competition_events.qualifying_time_ms` | pro Disziplin/Jahrgang |
| Meldegelder | `competition_events.meldegeld` | Einzel + Staffel |
| Meldeanschrift | `contact_info.melde_email/name` | |
| Meldeschluss | `meldeschluss` | |
| Allg. Bestimmungen | `competitions.description` | Markdown/Text |

```php
// app/Services/Competition/AusschreibungGenerator.php
class AusschreibungGenerator
{
    // Nutzt Laravel Blade-Template + DomPDF / wkhtmltopdf
    public function generate(Competition $competition): string  // returns file path
    {
        $pdf = Pdf::loadView('competitions.ausschreibung-pdf', [
            'competition' => $competition,
            'events'      => $competition->events,
            'sessions'    => $competition->events->groupBy('session_number'),
            'pflichtzeiten' => $competition->events->where('qualifying_time_ms', '>', 0),
        ]);
        $path = storage_path("app/ausschreibungen/{$competition->id}-ausschreibung.pdf");
        $pdf->save($path);
        return $path;
    }
}
```

**Template-Struktur** (`resources/views/competitions/ausschreibung-pdf.blade.php`):
1. Kopfzeile: Logo + Titel + Veranstaltungsbezeichnung
2. Eckdaten: Datum, Ort, Veranstaltungsort, Veranstalter, Ausrichter
3. Wettkampffolge (nach Abschnitten gruppiert, mit Wochentag + Uhrzeit)
4. Allgemeine Bestimmungen (§1–§15, aus Template-Bausteinen + variablen Feldern)
5. Meldeanschrift + Meldeschluss
6. Meldegeld-Tabelle
7. Pflichtzeiten-Tabellen (Frauen / Männer nach Strecken + Jahrgängen)
8. Unterschriftenfeld

### 8.3 DSV7 Definitionsdatei-Generator

Generiert eine `*-Wk.DSV7`-Datei aus den Wettkampf-Daten für WebClub / EasyWk:

```php
// app/Services/Competition/DefinitionsdateiGenerator.php
public function generate(Competition $competition): string
{
    // DSV7 Trenner: SATZART:Feld1;Feld2;...  (Doppelpunkt nach Satzart!)
    $gDs7 = fn(string $g): string => $g === 'F' ? 'W' : $g;  // F → W

    $lines = ['FORMAT:Wettkampfdefinitionsliste;7'];
    $lines[] = 'ERZEUGER:WaRa-Portal;1.0;portal@wasserratten.de';
    $lines[] = 'VERANSTALTUNG:' . implode(';', [
        $competition->name,
        $competition->location,
        $competition->course === 'LCM' ? '50' : '25',
        'AUTOMATISCH',
        $competition->date->format('d.m.Y'),
        ($competition->date_end ?? $competition->date)->format('d.m.Y'),
    ]);

    if ($competition->organizer) {
        $lines[] = "VERANSTALTER:{$competition->organizer}";
    }

    foreach ($competition->kampfgericht ?? [] as $official) {
        $lines[] = "KAMPFGERICHT:{$official['role']};{$official['name']};{$official['club'] ?? ''}";
    }

    // ABSCHNITT-Records für alle Sitzungsdaten
    $sessions = $competition->events->groupBy('session_number');
    foreach ($sessions as $sessionNum => $sessionEvents) {
        $firstEvt = $sessionEvents->first();
        $dateStr  = $firstEvt->session_date?->format('d.m.Y') ?? '';
        $name     = $firstEvt->session_name ?? ('Abschnitt ' . $sessionNum);
        $lines[] = "ABSCHNITT:{$sessionNum};{$dateStr};{$name}";
    }

    // WETTKAMPF + WERTUNG + PFLICHTZEIT + MELDEGELD
    // Neue Format-Feldfolge: nr;art;abschnitt;legs;strecke;technik;ausübung;geschlecht
    $wertungsIdCounter = 1;

    foreach ($competition->events->groupBy('event_number') as $eventNum => $wertungen) {
        $base = $wertungen->first();
        $isRelay  = str_contains($base->age_group ?? '', 'Staffel')
                    || ($base->distance > 400 && str_contains($base->discipline, 'freistil'));
        $legs = $isRelay ? 4 : 1;
        $perLegDist = $isRelay ? intdiv($base->distance, $legs) : $base->distance;
        $strokeCode = strtoupper(array_flip(Dsv7Parser::STROKE_MAP)[$base->discipline] ?? 'F');

        $lines[] = implode(';', [
            "WETTKAMPF:{$eventNum}",   // nr
            'E',                        // art: E=Entscheidung (vereinfacht; V/F bei Vorläufen)
            $base->session_number,      // abschnitt
            $legs,                      // legs (1=Einzel, 4=Staffel)
            $perLegDist,                // strecke
            $strokeCode,                // technik
            'E',                        // ausübung
            $gDs7($base->gender),       // geschlecht (W/M/X)
        ]);

        // Eine WERTUNG pro Altersklasse in diesem WK
        foreach ($wertungen as $wertung) {
            $ageMin = $wertung->age_min ?? 0;
            $ageMax = $wertung->age_max ?? 9999;
            $label  = $wertung->age_group ?: 'Offene Klasse';
            $lines[] = implode(';', [
                "WERTUNG:{$eventNum}",
                'E',
                $wertungsIdCounter,
                'E',
                $ageMin,
                $ageMax,
                $gDs7($wertung->gender),
                $label,
            ]);
            if ($wertung->qualifying_time_ms > 0) {
                $zeit = self::msToDs7($wertung->qualifying_time_ms);
                // new format: wkNr;art;wertungsID;(meldeschluss leer);zeit
                $lines[] = "PFLICHTZEIT:{$eventNum};E;{$wertungsIdCounter};;{$zeit}";
            }
            if ($wertung->meldegeld > 0) {
                $betrag = number_format($wertung->meldegeld, 2, ',', '');
                $lines[] = "MELDEGELD:{$eventNum};E;{$wertungsIdCounter};{$betrag};EUR";
            }
            $wertungsIdCounter++;
        }
    }

    $lines[] = 'DATEIENDE';
    return implode("\r\n", $lines);
}

// Hilfsmethode (auch in MeldedateiGenerator verwenden)
public static function msToDs7(int $ms): string
{
    $h  = intdiv($ms, 3_600_000);
    $m  = intdiv($ms % 3_600_000, 60_000);
    $s  = intdiv($ms % 60_000, 1_000);
    $hh = intdiv($ms % 1_000, 10);
    return $h > 0
        ? sprintf('%02d:%02d:%02d,%02d', $h, $m, $s, $hh)
        : sprintf('%02d:%02d,%02d', $m, $s, $hh);
}
```

### 8.4 Eingehende Vereinsmeldungen (🆕)

Wenn eigene Veranstaltung: andere Vereine schicken ihre `*-Vm.DSV7` per E-Mail. Diese sollen importiert werden:

- Neuer Upload im Tab „Meldungen" (Rolle: Ausrichter)
- Importiert `ANMELDUNG` + `MELDUNG`-Zeilen in eine neue Tabelle `incoming_entries`
- Auswertung: Meldeliste, Startliste, Lauf- und Bahneinteilung (Grundlage für WebClub/EasyWk)

Separate Tabelle `incoming_entries` (🆕):
```sql
CREATE TABLE incoming_entries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id  INT UNSIGNED NOT NULL,
    club_name       VARCHAR(200) NOT NULL,
    athlete_lastname  VARCHAR(100) NOT NULL,
    athlete_firstname VARCHAR(100) NOT NULL,
    birth_year      SMALLINT NOT NULL,
    gender          CHAR(1) NOT NULL,
    dsv_id          VARCHAR(20) NULL,
    event_number    SMALLINT UNSIGNED NOT NULL,
    entry_time_ms   INT UNSIGNED NULL,
    imported_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
);
```

---

## 9. Meisterschaftsebene (`competitions.level`)

### 9.1 Werte und Gewichtung (konfigurierbar)

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

### 9.2 Automatische Keyword-Erkennung beim Import

```php
private function detectLevel(string $name): string
{
    $n = mb_strtolower($name);
    if (str_contains($n, ' dm ')  || str_contains($n, 'deutsche meisterschaft'))   return 'dsv_dm';
    if (str_contains($n, ' djm ') || str_contains($n, 'jahrgangs'))                return 'dsv_djm';
    if (str_contains($n, 'ndm')   || str_contains($n, 'norddeutsch'))              return 'nsv';
    if (str_contains($n, ' lm ')  || str_contains($n, 'landesmeisterschaft'))      return 'shsv_lm';
    if (str_contains($n, 'shsv')  || str_contains($n, 'schleswig'))                return 'shsv_open';
    return 'vereins';
}
```

Inline-Edit in der Admin-Wettkampfliste ermöglicht schnelle Nachkorrektur.

---

## 10. Crawler-Strategie pro Quelle

### 10.1 SHSV (`shsv.de/vereinswettkaempfe`)

- HTML-Seite parsen → alle Links auf `*-Pr.DSV7` extrahieren
- Datei herunterladen, SHA-256-Hash prüfen, importieren
- **Taktung:** Mo + Di (nach Wettkampfwochenenden), Do (Masters)

### 10.2 NSV (`nsv-schwimmen.de`)

- Analog SHSV; URL-Whitelist in `federations`-Tabelle pflegen
- Weniger strukturiert — ggf. nur manuelle URL-Listen

### 10.3 DSV (`dsv.de`)

- DM, DJM: Links auf `*-Pr.DSV7` direkt bei Veranstaltungsseiten
- Ca. 5–10 Dateien/Jahr → wöchentlicher Cronjob ausreichend

---

## 11. Admin-Oberfläche (Gesamtübersicht)

### 11.1 Bestehende Funktionen (✅)

- Wettkampf anlegen/bearbeiten/löschen
- DSV7 Ergebnisdatei importieren (3-Schritt: Upload → Vorschau → Speichern)
- Wettkampffolge, Pflichtzeiten, Meldegelder aus DSV7 *-Wk.DSV7
- Tabs: Import, Wettkampffolge, Pflichtzeiten, Meldegelder, Ergebnisse, Auswertung
- Anmeldeworkflow (Signup-Request): Draft → Aktivieren → Erinnern → Schließen ✅
- Organisation-Tab (freie Notizen) ✅
- Meldungen-Tab (Teilnehmerliste) ✅

### 11.2 Neue Funktionen (🆕)

- **Tab „Ausschreibung":** PDF-Upload/Anzeige; strukturierte Metadaten; Ausschreibungs-Generator
- **Tab „Kampfgericht":** Schiedsrichter, Kampfrichter, Ausrichter-Kontakt
- **Tab „Anmeldungen" (merged):** Gruppen + interne Abfrage in einem Tab
- **Tab „Meldungen" (erweitert):** Streckenauswahl je Schwimmer; Staffelmeldungen; Validierung; DSV7-Generator
- **Batch-Import-Upload:** ZIP mit mehreren DSV7-Dateien
- **Import-Log-Seite:** `import_log` mit Status, Dateiname, Fehler, Zeitstempel
- **Vollständige Ergebnisse:** Neuer Abschnitt in Tab „Ergebnisse" für `ext_competition_results` (alle Clubs)
- **Level-Feld:** Dropdown in Bearbeitungsformular (dsv_dm / nsv / shsv_lm / …)

---

## 12. Auswertungen und Ranking

### 12.1 Score-Formel (Saison-Ranking)

```
score = dsv_punkte
      + (platz_bonus × wettkampf_gewicht / 10)
      + pb_bonus

platz_bonus:  Platz 1 → 50 | Platz 2 → 30 | Platz 3 → 20 | Platz 4–8 → 10 | sonst 0
pb_bonus: 15 Punkte
```

### 12.2 Bestenliste (intern)

Berechnet aus `ext_competition_results` JOIN `athletes`:

```php
// app/Services/Ranking/BestenlisteService.php
public function get(string $discipline, int $distance, string $gender,
                    ?string $course = null, ?int $year = null, int $limit = 25): Collection
{
    return ExtCompetitionResult::with(['athlete', 'competition'])
        ->where(['discipline' => $discipline, 'distance' => $distance,
                 'gender' => $gender, 'status' => 'OK'])
        ->when($course, fn($q) => $q->whereHas('competition', fn($q) => $q->where('course', $course)))
        ->when($year,   fn($q) => $q->whereHas('competition', fn($q) => $q->whereYear('date', $year)))
        ->orderBy('time_ms')
        ->limit($limit)
        ->get();
}
```

### 12.3 KI-Textgenerierung (✅ bereits vorhanden)

Die bestehende KI-Auswertung (`CompetitionController::generateAnalysis()`) nutzt die Claude Haiku API. Erweiterbar auf `ext_competition_results` für umfassendere Berichte.

---

## 13. Migrations-Reihenfolge (sichere Umsetzung)

Jeder Schritt ist isoliert testbar. Die bestehende Funktionalität bleibt nach jedem Schritt vollständig funktionsfähig.

```
Schritt 1:  federations anlegen (neue Tabelle, kein Risiko)
Schritt 2:  competitions erweitern (level, ausrichter, venue_details, kampfgericht,
            contact_info, announcement_pdf_path, source_file, source_url, import_hash)
            → alle nullable, bestehende Zeilen unberührt
Schritt 3:  athletes anlegen
Schritt 4:  ext_competition_results anlegen
Schritt 5:  competition_entries anlegen
Schritt 6:  competition_relay_entries + competition_relay_entry_members anlegen
Schritt 7:  relay_results + relay_members anlegen (für DSV7-Vollimport)
Schritt 8:  import_log anlegen
Schritt 9:  incoming_entries anlegen (für eigene Veranstaltungen)
Schritt 10: season_scores anlegen (optional, nur wenn Ranking-Feature live)
```

---

## 14. Offene Fragen & Risiken

| Thema | Risiko | Empfehlung |
|---|---|---|
| **Athleten-Matching** | Gleicher Athlet unter verschiedenen Schreibweisen | `(lastname, firstname, birth_year, gender)` als UK; DSV-ID priorisieren |
| **DSV7 Format 6** | Ältere WebClub-Exporte noch im Format 6 | `FORMAT`-Zeile prüfen; `Dsv7Parser` erkennt EasyWK 2.x bereits ✅ |
| **Staffel-Meldungen** | Zusammensetzung ändert sich noch | Meldedatei-Generator immer neu generieren, kein Cache |
| **robots.txt / Scraping** | SHSV/NSV könnten Scraping einschränken | Terms prüfen; ggf. direkten Datei-Feed beim SHSV anfragen |
| **Pflichtzeit-Nachweis** | DSV prüft Pflichtzeiten aus der Bestenliste, nicht aus dem Portal | Portal zeigt Hinweis: „Pflichtzeit aus Vereinszeit erreicht; Nachweis-Datum prüfen" |
| **Meldezeit-Qualität** | Meldezeit aus Portal-DB kann veraltet sein | Immer die neuste verfügbare Vereinszeit verwenden; Trainer kann überschreiben |
| **Ausschreibungs-PDF** | Layout variiert stark je Verband | Zunächst: einfaches, funktionales Layout; kein 1:1-Match der NDM-Optik |
| **Meldedatei-Kompatibilität** | WebClub / EasyWk können streng bei Feldformaten sein | Gegen DSV Standard 7 validieren; Testlauf mit WebClub-Import |
| **`competition.level`-Vergabe** | Keyword-Erkennung fehleranfällig | Inline-Edit in der Admin-Wettkampfliste als Backup |
| **Score-Formel** | Gewichtung subjektiv | Formel in `config/competition_levels.php` — nie hardcoded |
| **Shared Hosting** | Kein Daemon für Scraper | Laravel Scheduler via Cron: `* * * * * php artisan schedule:run` |
| **Zwei parallele Ergebnissysteme** | `competition_results` vs. `ext_competition_results` | Klar getrennte Zuständigkeit: `competition_results` = eigene Schwimmer (Cockpit, PBs, Rekorde); `ext_competition_results` = alle Athleten (Rankings, Vollansicht) |
| **Eingehende Meldungen** | Andere Vereine senden ggf. DSV6 oder fehlerhafte Dateien | Strikte Validierung; Upload-Fehler klar im Import-Log protokollieren |
| **MELDUNG/ANMELDUNG Format** | Exakte Feldstruktur für Vereinsmeldedatei (Formular 101/102) nicht im Parser verifizierbar — Parser überspringt diese Satzarten | Vor erstem produktivem Einsatz gegen offizielle DSV Standard 7 Spezifikation (dsv.de) abgleichen; Testlauf mit WebClub/EasyWk |
| **STAFFELANMELDUNG Format** | Relay-Meldungsformat (Formular 102) unbekannt | Wie oben; alternativ beim NSV/SHSV eine Beispiel-Meldedatei anfragen |

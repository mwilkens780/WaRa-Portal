# Frontend-Audit und Umbauplan

Stand 28.09.2026. Bestandsaufnahme des Portal-Frontends als Grundlage für den
Umbau zu einem einheitlichen UI-System. Noch keine Codeänderungen – dieses
Dokument ist der Plan dafür.

---

## 1. Kurzfazit

Das Portal ist funktional reich, aber das Frontend ist Seite für Seite
gewachsen. Es gibt praktisch kein gemeinsames Bauteil (eine einzige
Blade-Komponente bei 110 Views / 27.000 Zeilen), jede Seite bringt ihre
eigenen Buttons, Karten, Dialoge und Meldungen mit. Daraus folgen die
eigentlichen Probleme:

- **Pop-ups sind für Tastatur und Screenreader nicht bedienbar.** Kein
  einziger Dialog hat Dialog-Semantik, Fokusführung oder Scroll-Sperre.
- **Der Hallenplan ist ohne Maus nicht benutzbar** und kann beim Speichern
  unbemerkt Doppelbuchungen erzeugen.
- **Mobil brechen zentrale Listen** (Trainingseinheiten, Kalender-Monat,
  Wettkampfergebnisse), obwohl nirgends horizontal überläuft – der Platz wird
  falsch verteilt statt überlaufen.
- **Elf Import-Abläufe funktionieren elf Mal anders**, einer ist gar nicht
  verlinkt.
- **Die Auslieferung über das Tailwind-Play-CDN** ist ausdrücklich nicht für
  den Produktivbetrieb gedacht (Browser-Konsole sagt das auf jeder Seite) und
  verhindert Design-Tokens und Komponentenklassen.

Die gute Nachricht: Das Bottom-Sheet der Zeiterfassung und die
Speicher-Warteschlange dort sind bereits auf Produktniveau und taugen als
Vorlage für das ganze System.

---

## 2. Wie geprüft wurde

| | |
|---|---|
| Umgebung | Lokal (Laragon, `artisan serve`), lokale DB mit echten Daten, vorher auf aktuellen Migrationsstand gebracht |
| Browser | Chromium über Playwright |
| Breiten | Desktop 1440 px · Tablet 820 px (Touch) · Mobil 390 px (Touch) |
| Seiten | 40 Admin-Seiten, 5 Trainer-Seiten, je 3 Breiten = 135 Messungen |
| Automatisch | axe-core (WCAG 2.1 A/AA + Best Practice), Überlauf, Touch-Ziele < 44 px, Felder ohne Label, JS-Fehler, Ladezeit |
| Interaktiv | Jeder Dialog: Öffnen, Fokus, 25× Tab, Escape, Fokus-Rückgabe, Scroll-Sperre, Mobildarstellung |
| Statisch | Alle 110 Views: Muster, Varianten, Zustände, Menüs, Importe |

**Lücke:** Schwimmer- und Elternseiten konnten lokal nicht im Browser geprüft
werden – alle lokalen Konten dieser Rollen stehen noch auf Erst-Passwort und
werden auf „Passwort ändern“ umgeleitet. Diese Seiten sind nur statisch
ausgewertet. Vor Phase 5 sollte ein Testkonto je Rolle bereitstehen.

---

## 3. Sofort-Fehler (unabhängig vom Umbau)

Beim Audit gefundene echte Fehler. Klein, klar, sollten vor dem Umbau
behoben werden.

**Stand 28.09.2026 (Phase 0):** F1–F11, F13–F15 behoben. F12 (Quill → Tiptap)
braucht den Build aus Phase 1 und ist dorthin verschoben; das Sicherheitsrisiko
dahinter (F14) ist unabhängig davon geschlossen. Nebenbei behoben: Layout zeigt
jetzt auch `error`/`warning`/`info`-Meldungen (62 Controller-Aufrufe mit
`->with('error')` liefen vorher auf vielen Seiten ins Leere); Eltern und
Schwimmer sahen die Auswertung als rohes HTML; frische Installation scheiterte
zusätzlich an Migration 027 (`each()` ohne Sortierung) und 029 (Indexname
> 64 Zeichen).

| # | Wo | Fehler | Wirkung |
|---|---|---|---|
| F1 | `trainer/hall/index.blade.php` `save()` | Antwort ≠ 2xx wird ignoriert, kein Fehlertext | Validierungsfehler (z. B. fehlende Bezeichnung) → Dialog bleibt stumm stehen |
| F2 | ebenda, `save()` | Sendet immer `force: true` | Serverseitige Konfliktprüfung wird immer übergangen |
| F3 | ebenda, Bahn-Checkboxen | Wechsel der Bahn löst `checkConflicts()` nicht aus | Konfliktwarnung veraltet → mit F2 unbemerkte Doppelbuchung möglich |
| F4 | ebenda, `deleteBooking()` / `searchSessions()` | Kein Fehlerpfad; Löschen lädt die Seite auch bei Fehlschlag neu | Fehlgeschlagenes Löschen sieht aus wie Erfolg |
| F5 | `admin/users/edit.blade.php:249` | `@change` steht in `{{ }}` und wird escaped → Alpine-Syntaxfehler | Haken „Elternteil“ blendet die Kinderzuordnung nicht ein |
| F6 | `calendar/index.blade.php:97` | `try` in `x-init` ist ungültig → Syntaxfehler | Kalenderfilter werden nie gespeichert/wiederhergestellt |
| F7 | Layout + 72 Dateien | `hover:bg-primary-dark` ist nicht definiert | Primärbuttons haben keinen Hover-Zustand |
| F8 | 30 Views | Rendern `session('success')` selbst, das Layout auch | Erfolgsmeldung erscheint doppelt |
| F9 | `trainer/sessions/live.blade.php` | Offene Zeiten nur im Speicher; bei 419 (Sitzung abgelaufen) Endlos-Retry | Reload/Tab-Kill am Beckenrand ohne Netz = Zeiten weg |
| F10 | `database/migrations/2026_09_23_000099_…` | Ruft `GroupRoster::snapshotRunningSeason()` auf, das `left_at` braucht – die Spalte kommt erst in `000100` | Frische Installation / `migrate:fresh` bricht ab (in Produktion unkritisch, lief getrennt) |
| F11 | `trainer/dsv-import` | Seite ist von nirgends verlinkt | Funktion nur per URL erreichbar |
| F12 | Quill 1.3.7 (Wettkampf-Ausschreibung) | Seit 2019 unverändert, gemeldete XSS-Lücke (CVE-2021-3163, vom Projekt bestritten) | Sicherheits- und Wartungsrisiko |
| F13 | Layout | `alpinejs@3.x.x`, `chart.js@4` ohne feste Version, ohne SRI | Ein fremdes Release kann das Portal über Nacht ändern/brechen |
| F14 | `CompetitionController.php:525`, `auswertung-print`/`-pdf` | Auswertungstext (Quill-HTML) wird ungeprüft gespeichert und mit `{!! !!}` ausgegeben | Gespeichertes XSS: jedes Konto mit Wettkampfrechten (auch Kampfrichter) kann Skript in die Druckansicht anderer bringen |
| ~~F15~~ | Login / `/` | Angemeldete kreisten zwischen `/` und `/login`; Vorstand/Kampfrichter → 403, Ernährungsberater/Teamarzt → Schleife | **Behoben** in `6df5d76` (`User::homeUrl()`) |

---

## 4. Befunde nach Bereich

### 4.1 Fundament

- **Tailwind Play-CDN** (`cdn.tailwindcss.com`): kompiliert CSS im Browser bei
  jedem Aufruf, großes Skript vor dem ersten Rendern, kurzes ungestyltes Aufblitzen, keine
  `@apply`-Komponentenklassen, keine Plugins (Forms, Container Queries).
- **Farbskala `primary`** mischt Vereinsblau (600/800) mit Tailwind-Standardblau
  (50–500, 700, 900) – `primary-700` (`#1d4ed8`) ist nicht dunkler als
  `primary-600` (`#1B5EAB`), die Skala steigt nicht gleichmäßig. Dazu 47× `#1B5EAB` und ~80 weitere
  Hex-Werte direkt im Markup, 182 `style="…"`.
- **Schrift**: 128× `text-[9px]`–`text-[11px]`. Unter 12 px ist auf dem Handy
  am Beckenrand kaum lesbar.
- **JS**: 22 globale `function x()` direkt in Views, die großen (Zeiterfassung
  692 Zeilen, Hallenplan 424, Trainingsplan 190) ohne jede Modulgrenze.
  Hart codierte URLs (`/trainer/hall/...`) statt `route()`.

### 4.2 Navigation und Menüstruktur

**Seitenleiste**

- Admin sieht **23 Einträge** in 7 Abschnitten; Einträge gleichen Namens
  zeigen auf verschiedene Ziele: „Benutzerverwaltung“ ×2, „Kandidaten“ ×2
  (Ernährung, Teamarzt), „Motto der Woche“ ×2.
- Gruppierung folgt **Rollen**, nicht Aufgaben („Trainer-Bereich“ enthält
  Wettkämpfe und Rekorde, die auch Vorstand/Kampfrichter nutzen).
- Wichtige Werkzeuge fehlen ganz und stecken als Links in der
  Einstellungsseite: **Mail-Protokoll, Korrekturen (Zeiten, Bahnlängen),
  WA-Punkte**. Der DSV-Import ist gar nicht verlinkt (F11).
- Persönliches (Profil, Gesundheit, Passwort, Support, Abmelden) belegt den
  unteren Teil der Leiste in drei getrennten Blöcken.
- Barrierefreiheit: kein `aria-current` für die aktive Seite, kein
  Skip-Link, Hamburger ohne Namen und ohne `aria-expanded`. Die mobile
  Leiste schließt nicht mit Escape, der Fokus wandert nicht hinein und
  läuft beim Tabben hinter sie.

**Seitenköpfe**

- Der Seitentitel steht im globalen Header; lange Titel (Wettkampfnamen)
  brechen mobil auf drei Zeilen und verdrängen das Datum.
- Aktionen stehen mal rechts oben (13 Seiten), mal in einer Karte, mal in
  einem Filterformular. Es gibt keine Brotkrumen; „Zurück“ gibt es in fünf
  Schreibweisen.
- **Tabs** gibt es in drei Techniken: Alpine-Zustand, `?tab=`-Parameter,
  eigene Routen. Keine davon mit `role="tablist"`; mobil laufen Tabs aus dem
  Bild, ohne dass man es sieht (Wettkampf-Detail: 4. Tab unsichtbar).
- **Primäraktionen konkurrieren**: Wettkampf-Detail hat „Import“ (blau) und
  „+ Ergebnis eintragen“ (rot) nebeneinander – Rot ist sonst Löschen.
  Trainingseinheit-Detail stellt „Löschen“ und „Serie löschen…“ gleichrangig
  neben „Bearbeiten“.

**Import-Abläufe** (elf Stück)

| Import | Einstieg | Vorschau | Bestätigen-Text |
|---|---|---|---|
| Vereins-/Landesrekorde | Rekorde → Karte unten | eigene Seite | „Markierte Rekorde importieren“ |
| Bestenliste | Rekorde → zweite Karte | eigene Seite | „{n} Einträge importieren“ |
| Mitglieder (WebClub-CSV) | Benutzer → „WebClub Import“ | eigene Seite | „Import durchführen“ |
| Veranstaltungen (WebClub) | Wettkämpfe → Button | eigene Seite | – |
| Ergebnisse DSV7 | Wettkampf → Tab „Import“ | eigene Seite | „Ergebnisse importieren“ |
| Ergebnisse WebClub-CSV | Wettkampf → Tab „Import“ | eigene Seite | „Ergebnisse importieren“ |
| Voll-/Definitionsimport | Wettkampf → Tab „Import“ | keine | sofort |
| Wettkampf aus DSV7 anlegen | Wettkampf neu | keine | Formular |
| DSV-Import (Trainer) | **nicht verlinkt** | eigene Seite | „Import jetzt ausführen“ |
| Hallenplan (Excel) | Hallenbelegung → Button | eigene Seite | „Ausgewählte Einträge importieren“ |
| Gruppe (CSV) | Trainingsgruppe → Karte | eigene Seite | – |

Zwei verschiedene Abläufe heißen „WebClub-Import“. DSV7-Dateien lassen sich
an drei Stellen hochladen. Kein Upload hat Drag & Drop, Dateigröße oder
Fortschritt; die Vorschauen haben unterschiedliche Auswahl-, Filter- und
Zusammenfassungslogik.

### 4.3 Pop-ups und Overlays

Gemessen (alle drei Breiten identisch):

| Dialog | Dialog-Rolle | Fokus beim Öffnen | Tab verlässt Dialog | Escape | Fokus danach | Scroll gesperrt |
|---|---|---|---|---|---|---|
| Hallenplan: Belegung | nein | bleibt auf Seite | 25 von 25 | ja | irgendwo („Woche“) | nein |
| Passwort ändern | nein | bleibt auf Auslöser | 19 von 25 | ja | irgendwo | nein |
| Mobile Seitenleiste | nein | nicht hinein | ja | **nein** | irgendwo | nein |
| Zeiterfassung: Zeilen-Editor | nein | (statisch) | ja | ja* | – | nein |
| Zeiterfassung: Reihenfolge | nein | (statisch) | ja | ja* | – | nein |
| Meine Ziele | nein | (statisch) | ja | – | – | nein |

\* Beide Zeiterfassungs-Dialoge hängen `@keydown.escape.window` an – ein
Escape schließt beide zugleich.

Dazu im Einzelnen:

- **Hallenplan-Dialog**: Klick auf den Hintergrund schließt nicht; Labels
  sind nicht mit Feldern verknüpft; Suchergebnisse „Trainingseinheit
  verknüpfen“ sind `div`s mit Klick – per Tastatur nicht wählbar. Mobil liegt
  der Speichern-Button unter dem sichtbaren Bereich des Dialogs. Das leere
  Farbfeld zeigt Schwarz, als wäre Schwarz gewählt. Ungespeicherte Eingaben
  gehen bei Escape ohne Rückfrage verloren.
- **25 native `confirm()`/`alert()`** – darunter „Wirklich ALLE Benutzer
  löschen?“. Nicht gestaltbar, auf iOS teils unterdrückt, kein Hinweis auf
  Folgen, kein Rückgängig.

### 4.4 Die vier Schwerpunktbereiche

**Hallenbelegung**
- Buchungen sind `div`s mit Pointer-Ereignissen: nicht fokussierbar, keine
  Rolle, kein Name. Neue Buchung = Klick auf eine Pixelposition. Ohne Maus
  ist der Plan nicht bedienbar; mit dem Screenreader ist er leer.
- Verschieben nur per Ziehen, keine Alternative (Tastatur, Menü „Verschieben
  nach…“).
- Mobil: Werkzeugleiste belegt vier Zeilen, bevor der Plan beginnt.
- Nach jedem Speichern/Löschen `location.reload()` – Scrollposition und
  Ansicht springen zurück.
- Logikfehler F1–F4.

**Kalender**
- Keine Termin-Details außer `title`-Tooltips – auf Touch-Geräten gibt es die
  nicht. Das Bearbeiten-Symbol erscheint nur bei Maus-Hover
  (`opacity-0 group-hover`): **auf dem iPad können Trainer Termine aus dem
  Kalender heraus nicht bearbeiten**.
- Mobil ist die Monatsansicht ein 7-Spalten-Raster mit abgeschnittenen
  Terminen („Tag d…“) und winzigen „+“-Zielen je Tag; die Steuerung belegt
  vier Zeilen. Eine Agenda-Liste wäre hier die übliche Lösung.
- Filter-Chips ohne `aria-pressed`, Filterspeicherung defekt (F6), eigene
  zweite Erfolgsmeldung (F8).

**Trainingspläne**
- Block löschen ist sofort endgültig – kein Rückgängig, keine Rückfrage.
- Keine Warnung beim Verlassen mit ungespeicherten Änderungen.
- 8–9 Icon-Buttons pro Block unter 44 px (Desktop 26 × 26 px); am Tablet am
  Beckenrand schwer zu treffen.
- Stilart-/Material-Umschalter ohne `aria-pressed`; „Wdh.“-Label an keinem
  Feld; 20 Felder ohne Label.
- Positiv: Verschieben per Hoch/Runter-Button statt nur per Ziehen.

**Zeiterfassung (Live)**
- Bestes Muster im Portal: Bottom-Sheet mobil, zentriert ab `sm`,
  Speicherstatus sichtbar, Warteschlange mit Wiederholung.
- Lücken: siehe F9 (Offline-Persistenz, 419); Reihenfolge nur per Ziehen
  (SortableJS), keine Tastaturalternative; Zeitfelder ohne Label (nur „1.“
  als Text davor); keine Dialog-Semantik.

### 4.5 Barrierefreiheit (axe, Desktop + Mobil)

| Regel | Schwere | Knoten | Seiten |
|---|---|---|---|
| color-contrast | ernst | 909 | 50 |
| label (Feld ohne Beschriftung) | kritisch | 516 | 32 |
| select-name | kritisch | 134 | 18 |
| button-name (Icon-Button ohne Namen) | kritisch | 55 | 50 |
| link-name | ernst | 60 | 1 |
| empty-table-header | gering | 50 | 5 |
| nested-interactive | ernst | 16 | 1 |
| heading-order | mittel | 8 | 4 |

Hauptursachen: `text-gray-400` auf Weiß (Kontrast 2,5 : 1 statt 4,5 : 1) als
Standard für Nebentexte; Labels ohne `for`/`id`. Außerdem global: kein
`focus-visible`-Stil (379× `outline-none`, Ring nur bei `focus`), kein
`aria-live` für Speicherstatus/Meldungen, kein `prefers-reduced-motion`,
keine Tabelle mit `<caption>`/`scope`.

### 4.6 Responsive

- Kein horizontales Überlaufen auf Seitenebene – Tabellen sind meist in
  `overflow-x-auto` gepackt (46 von 71).
- Stattdessen **Quetschung**: Trainingseinheiten-Liste (Serieninfo in ~60 px
  Spalte, Wort-für-Wort-Umbruch), Wettkampfergebnisse (Namen abgeschnitten
  „Lukas …“, die Zeit bekommt den Platz), Kalender-Monat.
- **Touch-Ziele**: 2.957 Elemente unter 44 px auf 45 Seiten mobil, Median 32
  je Seite; Spitzen: Einstellungen 332 von 337, Trainingseinheiten 177 von
  177, Berechtigungen 145 von 146.
- Uhrzeiten mit Sekunden („05:30:00 – 07:15:00“) im Einheiten-Detail.

### 4.7 Zustände

- **Laden**: 10 Spinner/Skeletons im ganzen Portal; 1 von 187 Formularen
  schützt vor Doppelklick beim Absenden.
- **Fehler**: `fetch()` in 7 Views; 4 davon ohne vollständigen Fehlerpfad.
  Kein gemeinsamer Umgang mit 419 (Sitzung abgelaufen) oder 422
  (Validierung).
- **Leer**: 44 verschiedene „Keine …“-Texte, 39 davon als großer
  zentrierter Block, der Rest als graue Zeile; selten mit nächster Aktion
  („Noch keine Einheit – jetzt anlegen“).
- **Pagination**: 7 Stellen; lange Listen (Benutzer 301, Protokolle) sonst
  ungeteilt.

### 4.8 Wiederverwendbarkeit (Zahlen)

| Muster | Vorkommen | Varianten |
|---|---|---|
| Primär-Button (`bg-primary …`) | 359 | **140** verschiedene Klassenketten |
| Karte (`bg-white rounded-xl shadow-sm`) | 246 | – |
| Badge (`rounded-full text-xs px-2`) | 267 | – |
| Button-Stile je Seite | – | Median 6, max. 14 |
| Blautöne für Aktionen | – | 11 (`primary`, `blue-500/600/700`, `indigo-600/700`, `#1B5EAB` …) |
| Erfolgsmeldung | 31 | 2 Techniken |
| Dialog | 6 | 6 Bauweisen |

---

## 5. Zielbild: WaRa-UI

### 5.1 Architektur

```
resources/
  css/app.css            Tailwind + Tokens (CSS-Variablen) + wenige @layer components
  js/app.js              Alpine (fest versioniert) + @alpinejs/focus + Stores
  js/ui/                 api.js, confirm.js, toast.js, dialog.js
  js/features/           hallPlan.js, liveTiming.js, planBuilder.js, calendar.js
  views/components/ui/   Blade-Komponenten (x-ui.*)
```

- **Build**: Vite + Tailwind in der GitHub Action; die fertigen Dateien aus
  `public/build` gehen per `rsync` über SSH mit auf den Server. Der Server
  braucht kein Node. Laravel bindet sie über `@vite` ein.
- **Tokens** statt Hex im Markup: Vereinsblau als echte Skala
  (`brand-50…900`, 600 = `#1B5EAB`, 800 = `#0D3F7A`), `danger` (Vereinsrot
  nur für Gefahr), `success`, `warning`, `muted` mit geprüftem Kontrast
  ≥ 4,5 : 1. Mindestschrift 12 px.
- **Keine globalen Funktionen** in Views: Feature-Logik als
  `Alpine.data('hallPlan', …)` in Modulen, Daten per `@js()` hinein.
- **Ein `api()`-Helfer** für alle Requests: CSRF, JSON, 422 → Feldfehler,
  419 → „Sitzung abgelaufen, neu anmelden“ ohne Datenverlust, Netzfehler →
  Toast. Keine hart codierten URLs.

### 5.2 Bausteine (API-Skizze)

| Komponente | Zweck | Kern-API |
|---|---|---|
| `x-ui.button` | Alle Buttons/Links als Button | `variant=primary\|secondary\|ghost\|danger`, `size=sm\|md\|lg`, `href`, `icon`, `loading`, `type` |
| `x-ui.icon-button` | Nur-Symbol | `icon`, `label` (Pflicht → `aria-label`), min. 44 × 44 px Trefferfläche |
| `x-ui.field` | Label + Feld + Hilfe + Fehler | `label`, `name`, `hint`, `required`; verknüpft `for/id`, `aria-describedby`, `aria-invalid` automatisch |
| `x-ui.input` / `select` / `textarea` / `checkbox` / `toggle` | Einheitliche Felder | übernimmt `old()` und `$errors` selbst |
| `x-ui.dialog` | **Alle** Pop-ups | `name`, `title`, `size`, `variant=center\|sheet\|auto` (auto = Sheet < 640 px), Slots `body`/`footer`; `role=dialog`, `aria-modal`, Fokusfalle (`x-trap.inert.noscroll`), Escape nur für den obersten Dialog, Fokus zurück zum Auslöser, Rückfrage bei ungespeicherten Änderungen, Footer immer sichtbar |
| `$confirm()` | Ersetzt alle `confirm()` | `$confirm({ title, text, confirm: 'Löschen', danger: true })` → Promise; für Formulare `x-ui.confirm-form` |
| `$toast()` + `x-ui.toaster` | Ersetzt Flash-Blöcke | Varianten success/error/info, `aria-live`, Rückgängig-Aktion optional; Layout übernimmt `session('success')` zentral |
| `x-ui.page-header` | Kopf jeder Seite | `title`, `subtitle`, `back`, Slots `actions` (max. eine Primäraktion) und `menu` (Weiteres …) |
| `x-ui.tabs` | Einheitliche Tabs | `tabs=[key => label]`, `mode=client\|query`; `role=tablist`, Pfeiltasten, mobil als scrollbarer Streifen mit Randverlauf oder Auswahlfeld |
| `x-ui.card` | Karte | `title`, `meta`, `collapsible`, `storage-key` (übernimmt heutige `collapsible-card`) |
| `x-ui.badge` | Status/Kategorie | `tone=neutral\|brand\|success\|warning\|danger`, `size` |
| `x-ui.empty-state` | Leerzustand | `icon`, `title`, `text`, Slot `action` |
| `x-ui.skeleton` / `x-ui.spinner` | Laden | – |
| `x-ui.table` | Tabellen | `caption`, sticky Kopf; mobil `stack`-Modus (Zeile wird Karte, Label je Wert) |
| `x-ui.menu` | Dropdown/Mehr-Menü | `role=menu`, Pfeiltasten, schließt bei Escape/Außenklick |
| `x-ui.segmented` | Umschalter (Woche/Tag, Monat/Liste) | `aria-pressed`/Radiogruppe |
| `x-ui.chip-toggle` | Filter/Stilarten | `aria-pressed` |
| `x-ui.import` | Import-Assistent | Schritte Hochladen → Prüfen → Ergebnis; Drag & Drop, Dateityp/-größe, Zusammenfassung „neu / geändert / übersprungen“, einheitlicher Button „{n} übernehmen“ |

### 5.3 Regeln

- **Button-Hierarchie**: pro Bereich eine Primäraktion. Rot nur für
  Zerstörendes. Zerstörende Aktionen in „Weiteres …“-Menüs oder am Ende,
  nie gleichrangig neben „Bearbeiten“.
- **Löschen**: Einzelnes, leicht Wiederherstellbares → sofort mit
  Rückgängig-Toast. Folgenreiches (Serie, Wettkampf mit Ergebnissen, alle
  Benutzer) → `$confirm` mit Folgen im Text; Massenlöschen → Eingabe des
  Wortes zur Bestätigung.
- **Formulare**: Absenden deaktiviert den Button und zeigt Ladezustand;
  Fehler direkt am Feld; ungespeicherte Änderungen → Warnung beim Verlassen.
- **Touch**: Trefferfläche ≥ 44 × 44 px (sichtbares Symbol darf kleiner
  sein); keine Funktion nur bei Hover.
- **Fokus**: sichtbarer `focus-visible`-Ring überall; Bewegung nur mit
  `motion-safe`.
- **Mobil**: Tabellen stapeln statt quetschen; Werkzeugleisten falten in ein
  „Filter“-Sheet; Pop-ups als Bottom-Sheet.

---

## 6. Vorschlag Menüstruktur

Gruppiert nach Aufgabe; Sichtbarkeit weiter über `MenuPermission`.

```
Übersicht        Dashboard · Kalender
Training         Trainingseinheiten · Hallenbelegung · Trainingsgruppen
                 Ziele & Kriterien · Einschätzungen · Motto der Woche
Wettkampf        Wettkämpfe · Rekorde & Bestenlisten
Mitglieder       Benutzer · Ernährungsberatung · Sportmedizin
Daten            Import-Center · Crawler & Import-Log · Korrekturen
System (Admin)   Einstellungen · Berechtigungen · Protokoll
                 Mail-Protokoll · WA-Punkte · DSGVO-Anfragen

Konto-Menü (Avatar oben rechts / unten in der Leiste):
                 Mein Profil · Gesundheitsdaten · Passwort · Support · Abmelden
```

- **Eindeutige Namen**: „Ernährungsberatung“ und „Sportmedizin“ statt
  zweimal „Kandidaten“; Benutzerverwaltung nur einmal (die Lite-Variante ist
  dieselbe Seite mit weniger Rechten).
- **Import-Center**: eine Seite mit allen Importen als Kacheln (Was, woher,
  letzter Lauf). Kontextimporte (Ergebnisse eines Wettkampfs, Gruppe) bleiben
  zusätzlich am Objekt, nutzen aber denselben `x-ui.import`-Assistenten.
  Umbenennen: „Mitglieder aus WebClub“ vs. „Veranstaltungen aus WebClub“.
- **Schwimmer und Eltern mobil**: untere Navigationsleiste mit 4–5 Zielen
  (Start · Training · Zeiten · Wettkämpfe · Mehr) statt Hamburger – das ist
  die Rolle, die fast nur am Handy unterwegs ist.

---

## 7. Umbauplan

Jede Phase ist einzeln deploybar; nichts bleibt halb umgestellt.

**Stand:** Phase 0 erledigt (`c293bac`). Phase 1 erledigt: Vite 8 + Tailwind
3.4 + Alpine 3.17.4 (+ Focus) aus dem Build, Ziel Safari 14 mit Polyfills
(`.at`, `findLast`), Build in der GitHub Action, Logo lokal, Quill → Tiptap
(`x-ui.rich-text-editor`, lädt nur bei Bedarf). Geprüft: alle im DOM
verwendeten Klassen auf 50 Seiten haben eine Regel im Build (dabei gefunden:
Laravels Seitennavigation aus `vendor/` fehlte im Content-Pfad).
Phase 2a erledigt: Bausteine `x-ui.*` (dialog, confirm-dialog, toaster, button,
icon-button, icon, field, tabs, menu, card, badge, alert, empty-state,
page-header), JS `$confirm`/`$toast`/`api()`/iOS-taugliche Scroll-Sperre,
Menü aus `App\Support\Navigation` (nach Aufgaben gruppiert, Eltern je Kind),
untere Navigation für Schwimmer/Eltern, Skip-Link, `aria-current`,
Passwort-Dialog und mobile Seitenleiste bestehen den Dialogtest in drei
Breiten; `gray-400` auf 4,6:1 Kontrast angehoben.
Phase 2b erledigt: alle 36 nativen `confirm()`/`alert()`/`prompt()` ersetzt
(`data-confirm`, `$confirm`, `$prompt`, `$toast`), Tipp-Bestätigung für
„Alle Benutzer löschen“, Rückfall auf Browser-Rückfrage solange `app.js` nicht
geladen ist, Musterseite `/admin/ui`, Kontobereich der Seitenleiste aufklappbar.

Phase 3 zu drei Vierteln erledigt: Trainingsplan (Rückgängig, Verlassen-Warnung,
benannte Symbol-Knöpfe), Zeiterfassung (Dialog-Semantik, Fokusrückgabe,
Reihenfolge per Tastatur mit Ansage), Kalender (`e9f0f63`: Termine als Knöpfe
mit Detail-Sheet `calendar/_event-sheet`, Daten aus `App\Support\CalendarEventPayload`,
„+N weitere“ im Sheet, Monat mobil als Agenda, Filter mit `aria-pressed`;
Sprung in Einheit/Wettkampf auch für Schwimmer und Eltern; Schulferien SH/HH
nach amtlichen Quellen).

Phase 3 erledigt: Hallenplan – Belegungen fokussierbar und benannt (Enter
öffnet), Dialog mit Dialog-Semantik, Fokusfalle und -rückgabe, Rückfrage bei
ungespeicherten Änderungen, festem Fuß und Bottom-Sheet mobil; Speichern,
Verschieben und Löschen ohne Neuladen (`api()`, Toast); Farbfeld ohne
schwarzen Leerzustand (und eigene Farbe geht beim Bearbeiten nicht mehr
verloren); Suchergebnisse als Knöpfe; Werkzeugleiste mit `aria-pressed` und
mobil kompakt; Schriftfarbe der Blöcke nach WCAG-Kontrast.

Phase 4 erledigt (`0eaf754`, `2b1faff`): Import-Center `/import` mit allen
Importen je Rolle (`App\Support\ImportCatalog`) und Stand der Crawler;
Bausteine `x-ui.file-drop`, `x-ui.upload-form`, `x-ui.import-steps`,
`x-ui.import-summary`, `x-ui.import-bar`; alle Upload-Stellen und neun
Vorschauen umgestellt; eindeutige Namen („Mitglieder aus WebClub“ /
„Veranstaltungen aus WebClub“). Dabei behoben: DSV-Import mit mehreren
Wettkämpfen speicherte den letzten statt des gewählten; Ergebnis-Import am
Wettkampf verknüpfte bei anderer Auswahl falsche Schwimmer; „Alle
auswählen“ in Rekord-/Terminvorschau war defekt; Definitionsdatei ersetzt
nicht mehr ohne Rückfrage. DSV-Import warnt vor Duplikaten.
Offen für später: echte Auswahl mehrerer Wettkämpfe im Ergebnis-Import;
Zusammenführen von allgemeinem DSV-Import und Import am Wettkampf
(fachliche Entscheidung). Nachtrag `c5d9f78`: Der DSV-Import führt jetzt
mit einer vorhandenen Veranstaltung zusammen (Auswahl in der Vorschau,
keine doppelten Ergebnisse, leere Angaben ergänzt).

Phase 5 erledigt (`081cc0d`, `38d4454`), gemessen mit axe über alle
GET-Seiten als Admin und Trainer, Desktop und Mobil (186 Aufrufe):

| Regel | vorher | nachher |
|---|---|---|
| label | 604 | 1 |
| select-name | 174 | 4 |
| color-contrast | 127 | 25 (Rest danach behoben) |
| nested-interactive | 18 | 0 |
| button-name | 1 | 0 |
| Touch-Ziele < 44 px (mobil) | 2.863 | 2.313 |

Globale Regeln in `resources/css/app.css`: Mindestgröße für Bedienelemente
auf Touch-Geräten (`.touch-exempt` als Ausnahme), scrollbare Flex-Zeilen
stauchen nicht. Fehlerübersicht im Layout springt zum Feld. Knöpfe auf
drei Varianten vereinheitlicht. Übrige Touch-Ziele sind überwiegend
Textlinks (WCAG 2.2 AA verlangt 24 px, nicht 44).
Phase 6 erledigt (`836394d`): GitHub Action „E2E (Browsertests)“ bei jedem
Push und Pull Request – frische MySQL mit `E2eSeeder`, Playwright + axe,
17 Tests in rund 2 Minuten. `pages.spec.js` prüft jede Menüseite aller vier
Rollen auf Desktop und Mobil (Status, JS-Fehler, Überlauf, axe kritisch/ernst
= 0); dazu Dialoge, Hallenplan-Speichern, Import-Assistent mit
DSV-Zusammenführen und Eltern-Link. Die Tests liefen dabei erstmals über die
Schwimmer- und Elternseiten und fanden dort noch Kontrastfehler (behoben).
Der Lauf blockiert den Deploy nicht; bei Bedarf in den Branch-Regeln als
Pflichtprüfung eintragen. Anleitung: CLAUDE.md, Abschnitt „Browsertests“.

**Umbau abgeschlossen** (Phasen 0–6).

| Phase | Inhalt | Ergebnis / Abnahme |
|---|---|---|
| **0 · Sofort-Fehler** | F1–F13 beheben | Kein JS-Fehler mehr in der Konsole auf allen geprüften Seiten; Hallenplan zeigt Fehler und verhindert stille Doppelbuchung; Zeiterfassung überlebt Reload ohne Netz |
| **1 · Fundament** | Vite + Tailwind-Build in GitHub Action + `rsync`; Tokens; Alpine fest versioniert + Focus-Plugin; `api()`-Helfer; Quill ersetzen | Seiten sehen unverändert aus (Screenshot-Vergleich), CDN-Warnung weg, Ladezeit gleich oder besser |
| **2 · Kernbausteine + Layout** | `button`, `icon-button`, `field`/Felder, `dialog`, `$confirm`, `$toast`, `page-header`, `tabs`, `card`, `badge`, `empty-state`, `menu`; neues Layout mit Menüstruktur aus Kap. 6, Skip-Link, `aria-current` | Muster-Seite mit allen Bausteinen; Passwort-Dialog und Seitenleiste bestehen den Dialogtest (Fokus, Tab, Escape, Rückgabe, Scroll) |
| **3 · Pop-ups & Schwerpunkte** | Hallenplan (Tastaturbedienung, Buchung als Button, „Verschieben nach…“, kein Reload), Kalender (Termin-Sheet, Agenda mobil, Bearbeiten ohne Hover), Trainingsplan (Rückgängig, Verlassen-Warnung, 44-px-Ziele), Zeiterfassung (Dialog-Semantik, Tastatur-Reihenfolge, Labels) | Alle vier ohne Maus bedienbar; axe ohne kritische Verstöße; mobil ohne Quetschung |
| **4 · Import-Center** | `x-ui.import`, Import-Center-Seite, alle elf Abläufe umstellen, DSV-Uploads zusammenführen | Ein Ablauf, eine Sprache; jeder Import aus dem Menü erreichbar |
| **5 · Restliche Seiten** | Bereichsweise: Wettkampf → Benutzer/Admin → Schwimmer/Eltern (inkl. unterer Navigation) → Rest; native `confirm()` restlos ersetzen | 0 × `confirm(`, 0 × `hover:bg-primary-dark`, ≤ 3 Button-Varianten |
| **6 · Absicherung** | Playwright-Suite (Seiten × 3 Breiten × Rollen, Dialogtests, axe) in der GitHub Action vor dem Deploy | Deploy stoppt bei neuem kritischen axe-Verstoß oder JS-Fehler |

Reihenfolge-Begründung: Phase 0 ist unabhängig und bringt sofort Nutzen.
Phase 1 muss vor allem anderen kommen, weil Tokens und Komponentenklassen
den Build brauchen. Die Schwerpunkte (3) kommen vor der Fläche (5), weil
dort die Bausteine unter echter Last erprobt werden.

---

## 8. Entscheidungen (Martin, 28.09.2026)

1. **Untere Navigation für Schwimmer/Eltern: ja** → Phase 5.
2. **Texteingabe zweigeteilt:**
   - *Formatierte Texte, die gedruckt, als PDF oder per Mail verschickt
     werden* (Wettkampf-Auswertung; später ggf. Ankündigungen): **Tiptap** mit
     festem Umfang (Überschrift, fett/kursiv, Listen, Links). Gespeichert wird
     HTML, **serverseitig per Allowlist bereinigt** (behebt F14). HTML geht
     unverändert in Mail-Vorlage und PDF.
   - *Notizen und Kommentare* (Trainernotiz, Absage-Kommentar, Ziel-Kommentare
     …): **einfaches Textfeld**, Anzeige mit Zeilenumbrüchen und klickbaren
     Links, HTML wird immer escaped. Kein Editor, kein Markdown – kurze Texte,
     meist am Handy getippt; Markdown-Syntax kennen Eltern und Schwimmer nicht.
   - Anzeige über zwei Bausteine: `x-ui.text` (Klartext) und
     `x-ui.rich-text` (bereinigtes HTML, einheitliche Typografie).
3. **Logo ins Portal übernehmen** (`public/images/`, in Phase 1).
4. **Ältere iOS/iPadOS unterstützen.** Vorschlag Untergrenze: **iOS/iPadOS 15**
   (iPhone 6s/7/SE 1, iPad Air 2, iPad mini 4, iPad 5. Gen.). Folgen siehe 8.1.
5. **Testkonten:** Lukas Wilkens (Schwimmer) und Lydia Wilkens (Elternteil)
   auf Produktion. Lydias Login-Schleife war F15.

### 8.1 Folgen der iOS-15-Untergrenze

| Thema | Ohne Rücksicht auf Alt-Geräte | Mit iOS 15 |
|---|---|---|
| CSS-Framework | Tailwind v4 (braucht Safari ≥ 16.4) | **Tailwind v3.4** – voll gepflegt, gleiche Klassen; Container Queries per Plugin |
| Dialoge | natives `<dialog>`, `inert`, Popover-API | **Alpine Focus-Plugin** (`x-trap`) für Fokusfalle; kein `<dialog>`/Popover (erst iOS 15.4/17) |
| Scroll-Sperre | `overflow: hidden` genügt | Auf iOS < 16 scrollt die Seite trotzdem mit → Sperre per `position: fixed` + Scrollposition merken |
| Höhe mobil | `100dvh` | `100vh`-Fallback vor `dvh` (dvh erst 15.4) |
| JS | aktuelles ES | Vite-Ziel `safari14`; keine neueren APIs (`structuredClone`, `Array.at`) ohne Polyfill |
| CSS | `:has()`, Nesting nativ | kein `:has()`; Nesting nur, wenn der Build es auflöst |
| Test | Playwright WebKit (aktuell) | zusätzlich einmal pro Phase auf einem echten Alt-Gerät |

Die Nutzer merken davon nichts – es kostet etwas mehr JS/CSS-Handarbeit in
den Grundbausteinen, die dann aber für alle Seiten gilt. Welche Versionen
wirklich im Einsatz sind, zeigen die Zugriffslogs im KAS (User-Agent);
danach lässt sich die Grenze belegen statt schätzen.

---

## Anhang: Messdaten

Rohdaten und Screenshots liegen außerhalb des Repos im Scratchpad der
Audit-Sitzung (`audit.json`, `popups.json`, `shots/`). Die Skripte
(Playwright + axe) sind die Grundlage für Phase 6.

# WaRa-UI – Designsystem und Entwicklungsregeln

Verbindlich für alle neuen und geänderten Seiten im Portal. Entstanden aus dem
Frontend-Umbau (Hintergrund und Messdaten: [frontend-audit.md](frontend-audit.md)).
Die Bausteine selbst liegen unter `resources/views/components/ui/`, jede Datei
erklärt im Kopf ihre Verwendung; die Musterseite ist **`/admin/ui`**.

**Grundregel: Baustein vor Handarbeit.** Gibt es für etwas einen `x-ui.*`-Baustein,
wird er benutzt – auch wenn eine ältere Seite es noch von Hand baut. Ältere Seiten
sind kein Vorbild. Wer eine Seite anfasst, stellt die berührten Teile auf die
Bausteine um.

Geprüft wird automatisch: `npm run lint:ui` (Regeln aus Abschnitt 13) und die
Browsertests mit axe (`tests/e2e`), beides in GitHub Actions.

---

## 1. Farben

### Markenfarben (`tailwind.config.js`)

| Token | Wert | Verwendung | Kontrast Weiß |
|---|---|---|---|
| `primary` | `#1B5EAB` | Hauptaktion, Links, aktive Zustände | 6,5 : 1 |
| `primary-dark` | `#0D3F7A` | Hover der Hauptaktion | 10,5 : 1 |
| `accent` | `#C0392B` | **nur** Zerstörendes (Löschen) | 5,4 : 1 |
| `accent-dark` | `#992d22` | Hover von `accent` | 7,6 : 1 |
| `gray-400` | `#6c7381` | überschrieben (Tailwind-Standard hatte 2,5 : 1) | 4,8 : 1 |

Keine weiteren Farben für Knöpfe. Grün, Violett, Orange usw. sind **Statusfarben**
(Badges, Kalender-Kategorien, Hinweise), keine Aktionsfarben.

### Textfarben – Mindestkontrast 4,5 : 1 (WCAG AA)

| Klasse | auf Weiß | auf `gray-50` | auf `gray-100` | Einsatz |
|---|---|---|---|---|
| `text-gray-900` | 17,7 | 17,0 | 16,1 | Überschriften, Werte |
| `text-gray-800` | 14,7 | 14,0 | 13,3 | Fließtext betont |
| `text-gray-700` | 10,3 | 9,9 | 9,4 | Fließtext, Tabellenzellen |
| `text-gray-600` | 7,6 | 7,2 | 6,9 | **Nebentext, Hinweise, Legenden** |
| `text-gray-500` / `-400` | 4,8 | 4,6 | **4,4 ✗** | nur auf Weiß/`gray-50`, nie auf `gray-100`-Flächen |
| `text-gray-300` / `-200` | 1,5 | – | – | **nie für Text** – nur Rahmen, Trenner, dekorative Symbole |

Statusfarben als **Text** erst ab Stufe **700** (auf Weiß und auf der eigenen
50er-Fläche ≥ 4,8 : 1): `text-green-700`, `text-amber-700`, `text-red-700`,
`text-blue-700`, `text-orange-700`, `text-sky-700`. Stufe 600 reicht nur bei
Rot, Blau und Violett. Badges: Text 700/800 auf 50/100 (`x-ui.badge` macht das).

**Verboten**, weil es den Kontrast unbemerkt senkt:
- `opacity-*` auf Elementen, die Text enthalten (dimmt die Schrift mit)
- halbtransparente Textfarben (`text-primary/70`, `text-white/60`)
- farbige Schrift unter 12 px (z. B. Ressourcenfarbe als Beschriftung) – Farbe als Balken/Punkt zeigen, Text dunkel
- Information nur über Farbe (zusätzlich Text, Symbol oder Muster, vgl. Hallenplan: Schraffur + Legende)

Weiße Schrift auf farbigem Grund nur auf `primary`, `primary-dark`, `accent`,
`accent-dark`, `gray-700` und dunkler. Belegungsblöcke im Hallenplan wählen die
Schrift per `HallBooking::readableTextColor()` (Weiß oder Schwarz nach WCAG).

## 2. Schrift

Systemschrift (Tailwind-Standard, kein Webfont – schnell, gut lesbar auf allen Geräten).

| Rolle | Klassen |
|---|---|
| Seitentitel | steht in der Titelleiste: `@section('page-title', '…')` |
| Kartenüberschrift | `text-base font-semibold text-gray-900` (`x-ui.card title=`) |
| Abschnitt in Listen | `text-xs font-semibold uppercase tracking-wide text-gray-600` |
| Fließtext, Formulare, Tabellen | `text-sm text-gray-700` |
| Nebentext, Hinweise | `text-xs text-gray-600` |
| Zeiten, Zahlen in Spalten | zusätzlich `tabular-nums` (Schwimmzeiten `font-mono`) |

**Mindestgröße 12 px (`text-xs`).** Kleiner (`text-[10px]`) nur in dichten Rastern
(Hallenplan, Kalender-Monat) und dann mit ≥ 7 : 1 Kontrast. Uhrzeiten ohne Sekunden
(`substr($zeit, 0, 5)`).

## 3. Abstände, Flächen, Layout

- Seiteninhalt: Stapel mit `space-y-5` bzw. `space-y-6`, oben `mt-2`.
- Karte: `x-ui.card` (weiß, `rounded-xl`, `border-gray-100`, `shadow-sm`, Innenabstand `p-5`).
  Tabellen in Karten: `:padded="false"`.
- Seitenkopf mit Zurück-Link, Untertitel und Aktionen: `x-ui.page-header`.
- Mobil zuerst: Seite darf **nie waagerecht scrollen** (wird getestet). Breite Inhalte
  scrollen in ihrem eigenen Container (`overflow-x-auto`).
- Breakpoints: `sm` 640 px (ab hier Dialoge zentriert statt Bottom-Sheet),
  `lg` 1024 px (feste Seitenleiste). Untere Navigation für Schwimmer und Eltern
  kommt aus `App\Support\Navigation::bottom()`.
- Waagerecht scrollbare Zeilen (Reiter, Chips): `flex overflow-x-auto` – Einträge
  stauchen nicht (globale Regel in `app.css`).

## 4. Touch und Fokus

Global in `resources/css/app.css`, nichts einzeln zu tun:
- Auf Touch-Geräten: Knöpfe, Auswahl- und Eingabefelder mindestens **44 px** hoch,
  Checkboxen/Radios 24 px. Ausnahme nur, wenn die Größe Bedeutung hat
  (Hallenplan-Blöcke: Höhe = Dauer) – dann Klasse `touch-exempt`.
- Sichtbarer Fokus (`focus-visible`) auf allen Bedienelementen. Nie
  `outline-none` ohne Ersatz (`focus-visible:ring-2 focus-visible:ring-primary`).
- `prefers-reduced-motion` schaltet Animationen ab.
- Keine Funktion nur per Hover (Tooltips, Stifte, die erst beim Überfahren erscheinen).
- Symbol-Knöpfe: sichtbar klein, Trefferfläche groß (`x-ui.icon-button` macht beides).

## 5. Knöpfe und Links

**`x-ui.button`** – genau drei Varianten (plus `ghost` für Unauffälliges in Werkzeugleisten):

| Variante | Wann | Beispiel |
|---|---|---|
| `primary` (Standard) | **eine** Hauptaktion je Bereich | Speichern, Anlegen, Übernehmen |
| `secondary` | Nebenaktionen, Navigation zu Unterseiten | Abbrechen, Exportieren, Zur Serie |
| `danger` | nur Zerstörendes | Löschen, Anonymisieren |

```blade
<x-ui.button icon="plus" href="{{ route('trainer.sessions.create') }}">Neue Einheit</x-ui.button>
<x-ui.button variant="secondary" @click="close()">Abbrechen</x-ui.button>
<x-ui.button variant="danger" type="submit">Löschen</x-ui.button>
<x-ui.icon-button icon="trash" label="Block löschen" @click="remove(i)" />
```

- Zerstörende Aktionen nie gleichrangig neben „Bearbeiten“: ans Ende oder in ein
  `x-ui.menu` („Weitere Aktionen“) – vgl. `x-ui.page-header` Slot `menu`.
- Beschriftung sagt, was passiert („3 Rekorde übernehmen“, nicht „OK“).
- Formular abgeschickt → Knopf gesperrt mit „Wird gespeichert …“ (kein Doppelklick).
- Link (`<a>`) wenn es woandershin geht, Knopf (`<button type="button">`) wenn etwas
  passiert. Links im Fließtext **unterstrichen** (`underline underline-offset-2`),
  nicht nur farbig.
- Umschalter (gedrückt/nicht gedrückt) bekommen `:aria-pressed`, Aufklapper `:aria-expanded`.

## 6. Formulare

**`x-ui.field`** für Eingabe, Auswahl und Textfeld – verknüpft Beschriftung,
Hinweis und Fehler selbst und holt `old()`-Werte:

```blade
<x-ui.field label="Vorname" name="firstname" :value="$user->firstname" required />
<x-ui.field label="Typ" name="type" as="select"> <option …> </x-ui.field>
<x-ui.field label="Notizen" name="notes" as="textarea" hint="Nur für Trainer sichtbar" />
```

- **Jedes Feld hat eine Beschriftung** (`label for=` bzw. `aria-label`). Platzhalter
  ersetzen keine Beschriftung. Filter ohne sichtbares Label: `aria-label="Nach Rolle filtern"`.
- Pflichtfelder: `required` + rotes Sternchen mit `aria-hidden` (macht `x-ui.field`).
- Fehler stehen am Feld; das Layout zeigt oben eine Übersicht, deren Einträge zum
  Feld springen. Keine zusätzlichen eigenen `$errors->all()`-Blöcke.
- Ungespeicherte Änderungen: Dialoge mit `guard`, lange Formulare mit Warnung beim
  Verlassen (Muster: Trainingsplan).
- Formatierter Text nur, wo gedruckt/gemailt wird: `x-ui.rich-text-editor`, Ausgabe
  `x-ui.rich-text` (bereinigt serverseitig). Nie `{!! $html !!}` für Nutzertext.
- Datei-Uploads: `x-ui.upload-form` + `x-ui.file-drop` (Typ- und Größenprüfung mit
  denselben Grenzen wie der Server).

## 7. Tabellen

Noch kein eigener Baustein (geplant: `x-ui.table` mit Stapelansicht fürs Handy).
Bis dahin dieses Muster:

```blade
<x-ui.card :padded="false">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <caption class="sr-only">Vereinsrekorde Freistil männlich</caption>
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Strecke</th>
                    <th scope="col" class="px-4 py-2.5 text-right …">Zeit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-gray-700">50 m</td>
                    <td class="px-4 py-2.5 text-right font-mono tabular-nums text-gray-900">0:24,53</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-ui.card>
```

- Kopfzellen mit `scope="col"`, Zahlen rechtsbündig mit `tabular-nums`.
- Auswahl-Checkboxen je Zeile mit `aria-label` („Zeile 3 übernehmen: Name“),
  „Alle auswählen“ ebenso.
- Mehr als vier Spalten auf dem Handy: unwichtige Spalten `hidden md:table-cell`
  oder die Zeile als Karte darstellen – nicht quetschen.
- Leere Tabelle: `x-ui.empty-state` statt leerer Zeile; Einzelzellen „noch kein
  Rekord“ in `text-gray-600 italic`.
- Scrollbare Bereiche ohne Link/Knopf werden automatisch per Tastatur erreichbar
  (`resources/js/ui/scroll-regions.js`).

## 8. Pop-ups und Rückmeldungen

| Situation | Baustein |
|---|---|
| Eingabe oder Details in einem Fenster | `x-ui.dialog` (mobil Bottom-Sheet, Fokusfalle, Escape, Fokus zurück, `guard`) |
| Rückfrage vor Folgenreichem | `$confirm({ title, text, confirmLabel, danger })` bzw. `data-confirm` am Formular/Link |
| Massenlöschung u. ä. | `$confirm({ …, requireText: 'LÖSCHEN' })` – Wort muss getippt werden |
| Kurze Rückmeldung („Gespeichert“) | `$toast('…')`, bei Löschen mit Rückgängig: `{ action: { label: 'Rückgängig', run } }` |
| Bleibender Hinweis/Fehler im Seitenfluss | `x-ui.alert tone="info|success|warning|error"` |
| Eingabe eines einzelnen Werts | `$prompt({ title, label, value })` – liefert den Text oder `null` |

- **Nie** `alert()`, `confirm()`, `prompt()` (nicht gestaltbar, auf iOS teils
  unterdrückt; einzige Ausnahme: Rückfall im Layout-Kopf, solange `app.js` lädt).
- Löschen von Einzelnem, leicht Wiederherstellbarem: sofort + Rückgängig-Toast.
  Folgenreiches (Serie, Wettkampf mit Ergebnissen): `$confirm` mit den Folgen im Text.
- Eigene Overlays nur, wenn `x-ui.dialog` nicht passt (z. B. Zeiterfassung) – dann mit
  `role="dialog" aria-modal="true" aria-labelledby`, `x-trap`, Escape auf dem Panel,
  Fokusrückgabe und `uiScroll.lock()` (Muster: `trainer/hall/index.blade.php`).
- Speichern per JavaScript: `window.api()` (einheitliche Fehlermeldungen für 419/422/403,
  wirft nie) und Ergebnis ohne Neuladen in die Seite übernehmen.

## 9. Zustände

- **Leer:** `x-ui.empty-state` mit Titel, einem Satz warum – und der nächsten Aktion als Knopf.
- **Laden/Speichern:** Knopf gesperrt mit Ladetext; längere Vorgänge als Import-Schritt.
- **Fehler:** verständlicher Satz, was passiert ist und was man tun kann (`api()` liefert `message`).
- **Erfolg:** Toast, nicht eine neue Seite.

## 10. Navigation und Seitenaufbau

- Menüeinträge nur in `App\Support\Navigation` (nach Aufgaben gruppiert, Sichtbarkeit über
  `MenuPermission`), nie direkt im Layout.
- Reiter: `x-ui.tabs` (Pfeiltasten, mobil scrollbar). Aufklappmenü: `x-ui.menu`.
- Seitenkopf: `x-ui.page-header`, höchstens **eine** Primäraktion.
- Datums-/Zeitformate deutsch: `d.m.Y`, `H:i`; Wochentage per `isoFormat`.

## 11. Importe

Neuer Datei-Import = Eintrag in `App\Support\ImportCatalog` (erscheint im Import-Center),
drei Schritte mit denselben Bausteinen:

1. **Datei wählen:** `x-ui.import-steps :current="1"`, `x-ui.upload-form` + `x-ui.file-drop`
2. **Prüfen:** `x-ui.import-steps :current="2"`, `x-ui.import-summary` (neu / geändert /
   übersprungen), Tabelle mit Auswahl, `x-ui.import-bar` („{n} … übernehmen“)
3. **Übernehmen:** Rückmeldung mit Zahlen; nichts doppelt anlegen (vorhandene erkennen).

Vor dem Prüfen wird nichts gespeichert. Ersetzt ein Import sofort ohne Vorschau: `data-confirm`.

## 12. Technik-Regeln (Blade, Alpine, Tailwind)

- **Tailwind-Klassen nie zusammensetzen** (`bg-{{ $farbe }}-100`) – der Build findet nur
  ausgeschriebene Klassen. Farben aus Daten über feste Zuordnungen (`TrainingGroup::COLORS`).
- **Kein `<x-…>` in Kommentaren innerhalb von `<script>`** – Blade kompiliert es → Fehler 500.
- **Kein `\"` in HTML-Attributen** (`onclick`, `@click`, `x-data`): HTML kennt das nicht, das
  Attribut endet dort. Innen einfache Anführungszeichen (`\'` im JS-String).
- Keine doppelten Anführungszeichen in Kommentaren innerhalb von `x-data="…"`.
- Alpine-Komponenten mit Logik gehören nach `resources/js` (`Alpine.data('name', …)`),
  kleine Zustände dürfen inline bleiben.
- Deklarativ vor Skript: `data-confirm`, `x-ui.*`, `api()`, `$toast`, `$confirm`.
- iOS/iPadOS 15 ist Untergrenze: kein `<dialog>`, keine Tailwind-v4-Features, neue
  JS-APIs nur mit Polyfill (`resources/js/polyfills.js`).

## 13. Barrierefreiheit – Kurzliste

- Bilder/Symbole: dekorativ `aria-hidden="true"`, sonst Text/`aria-label`.
- Jeder Knopf und Link hat einen Namen (Text, `aria-label` oder `label` bei `x-ui.icon-button`).
- Jedes Feld hat eine Beschriftung; Gruppen (Radios) in `fieldset` + `legend`.
- Keine verschachtelten Bedienelemente (Link in `<summary>`, Knopf in Knopf).
- Live-Rückmeldungen über `$toast` (aria-live) – nicht nur Farbwechsel.
- Tastatur: alles erreichbar, sinnvolle Reihenfolge, Escape schließt, Fokus kehrt zurück.

## 14. Automatische Prüfung

| Prüfung | Was | Wo |
|---|---|---|
| `npm run lint:ui` | Regelverstöße im Quelltext (siehe unten) – **neue** Verstöße lassen den Lauf scheitern | GitHub Action „E2E“, Job „UI-Regeln“ |
| `npx playwright test` | jede Menüseite jeder Rolle (Status, JS-Fehler, Überlauf, axe), Dialoge, Importe | GitHub Action „E2E“ |

`lint:ui` prüft u. a.: Knopffarben außerhalb der drei Varianten, native
`alert/confirm/prompt`, `text-gray-200/300` als Textfarbe, halbtransparente
Textfarben, Schrift unter 10 px, `<x-…>` in Skript-Kommentaren, `\"` in Attributen,
Felder ohne Beschriftung, `{!! !!}` außerhalb erlaubter Stellen.
Bestehende Altlasten sind in `tests/ui-lint-baseline.json` erfasst und dürfen nur
**weniger** werden. Nach einer Bereinigung: `npm run lint:ui -- --update`.

## 15. Checkliste für neue Seiten und Änderungen

- [ ] Bausteine benutzt (`x-ui.button`, `x-ui.field`, `x-ui.card`, `x-ui.dialog`, `x-ui.empty-state` …)
- [ ] Eine Primäraktion, Zerstörendes als `danger` und nicht gleichrangig
- [ ] Textfarben aus Abschnitt 1, nichts unter 12 px, keine Deckkraft auf Text
- [ ] Jedes Feld beschriftet, jeder Knopf benannt
- [ ] Leer-, Lade- und Fehlerzustand bedacht
- [ ] Am Handy (390 px) geprüft: kein waagerechtes Scrollen, nichts gequetscht
- [ ] Per Tastatur bedienbar, Dialoge schließen mit Escape
- [ ] Neue Menüseite? Testdaten im `E2eSeeder` ergänzen, damit die Browsertests sie prüfen
- [ ] `npm run lint:ui` und Browsertests grün

## 16. So nicht → so

| So nicht | So |
|---|---|
| `<button class="bg-green-600 text-white …">Speichern</button>` | `<x-ui.button type="submit">Speichern</x-ui.button>` |
| `<label>Name</label> <input name="name">` | `<x-ui.field label="Name" name="name" />` |
| `onclick="return confirm('Löschen?')"` | `data-confirm="Löschen?" data-confirm-danger` |
| eigenes `fixed inset-0`-Overlay | `<x-ui.dialog name="…" title="…">` |
| `<p class="text-gray-400">Keine Einträge</p>` | `<x-ui.empty-state title="Noch keine Einträge" …>` |
| `text-gray-300` für „–“ oder Nebentext | `text-gray-600` (Text) / `text-gray-300` nur für Rahmen |
| `class="opacity-60"` auf einer Karte mit Text | eigener Hintergrund/Badge „abgelaufen“ |
| `fetch(url, {…})` mit eigener Fehlerbehandlung | `const res = await api(url, { method: 'POST', body })` |
| Stift erscheint nur bei Hover | Knopf immer sichtbar oder im Detail-Dialog |
| `window.location.reload()` nach dem Speichern | Ergebnis in die Seite übernehmen + `$toast` |

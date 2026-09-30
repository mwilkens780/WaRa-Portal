# Konzept: Trainingsserien, Einheiten und Hallenbelegung

Stand 30.09.2026 · Entscheidungen von Martin eingearbeitet (Abschnitt 5)

## 1. Ausgangslage – warum es heute hakt

**Eine Serie gibt es in der Datenbank nicht.** Sie ist nur eine gemeinsame Kennung
(`recurrence_group_id`) auf vielen einzelnen Einheiten. Alles, was „für die Serie“ gilt –
Titel, Zeit, Gruppen, Trainer, Bahnen –, steht in jeder Einheit einzeln und muss bei jeder
Änderung auf alle Kopien verteilt werden.

Daraus folgten die beobachteten Fehler (Schritt 1 behebt die schlimmsten, siehe unten):

| Beobachtung | Ursache |
|---|---|
| Änderungen „nicht gespeichert“ | Zwei Bearbeitungswege mit verschiedenem Verhalten: „Serie bearbeiten“ ändert nur kommende Einheiten und zeigte danach die erste – vergangene, unveränderte – Einheit. „Einheit bearbeiten → ganze Serie“ änderte alle, auch vergangene. |
| Doppelte, unsichtbare Belegungen | „Serie bearbeiten“ legte bei jedem Speichern je kommender Einheit eine eigene wöchentliche Belegung an. |
| Konflikt mit der eigenen Belegung | Belegungen hängen an einer Einheit; Belegungen derselben Serie galten als fremd. |
| Belegung verschwindet | Die Serienbelegung hing an einer Einheit – wurde diese gelöscht oder verschoben, ging sie mit. |
| Konflikt nur je Einheit auflösbar | Kein übergeordnetes Objekt „Serie“, das man beenden könnte. |
| Excel-Import | Legt Belegung und Serie an, verbindet sie aber nicht. |

Außerdem hängen an Einheiten: Anwesenheit/Absagen (`training_attendances`), Einzel- und
Gastbuchungen (`training_session_swimmers`, je Einheit oder je Serie), ständige Absagen
(`swimmer_series_exclusions`, je Serie), Anmeldungen, Trainer (Pivot), Trainingsplan mit
Blöcken und Live-Zeiten, Tagebuch/Einschätzungen.

### Schritt 1 (erledigt, `1ce46d4`)

- Eine Hallenbelegung je Bahn für die ganze Serie (`App\Services\SeriesHallBookings`),
  vorhandene Duplikate werden beim Speichern der Serie zusammengelegt, passende
  unverknüpfte Belegungen übernommen statt verdoppelt, echte Konflikte gemeldet.
- Serienbelegung wandert beim Löschen/Verschieben einer Einheit weiter.
- „Ganze Serie“ ändert nur noch kommende Einheiten.
- Nur lesende **Datenprüfung**: Admin → Daten → Datenprüfung Training
  (`php artisan training:audit`).

## 2. Zielbild – Datenmodell

```
Serie (training_series)                      Hallenplan
  Saison, Titel, Art, Wochentag,        ───1:n──  Wöchentliche Belegung (hall_bookings.series_id)
  Beginn, Ende, Ort, Rhythmus,                    – je Bahn genau eine, sichtbar im Plan
  gültig von/bis, SH-Ferien auslassen (ja)
  Plätze, Anmeldung, Gastgruppe
  Gruppen, Trainer
  Einzel-Teilnehmer, ständige Absagen
      │ 1:n
      ▼
Einheit (training_sessions)
  Datum + geerbte Werte der Serie
  Abweichungen (nur was bewusst anders ist: Zeit, Ort, Titel …)
  Status: geplant | fällt aus (Benachrichtigung per Mail)
  Ausnahme-Bahnen (nur für diesen Termin, NICHT im Hallenplan,
                   erzeugen dort keinen Konflikt)
  Anwesenheit, Absagen, Gäste, Trainingsplan, Live-Zeiten, Tagebuch
```

Grundsätze:

1. **Die Serie ist die Quelle.** Einheiten zeigen ihre Werte an; gespeichert wird dort nur,
   was bewusst abweicht. Eine Serienänderung erreicht damit automatisch alle Einheiten ohne
   eigene Abweichung – es gibt nichts mehr „zu verteilen“.
2. **Vergangenes bleibt, wie es war.** Änderungen gelten ab einem Datum (Standard: heute).
3. **Hallenplan = Serien.** Er zeigt nur regelmäßige Belegungen; jede gehört entweder zu einer
   Serie oder ist eine freie Belegung (Kurse, Schule, Wartung).
4. **Ausnahmen gehören zur Einheit** – z. B. Workshop auf Bahn 3 an einem Termin. Sie
   erscheinen in der Einheit und im Kalender, nicht im Hallenplan, und erzeugen dort keinen
   Konflikt. Beim Anlegen zeigt die Einheit Überschneidungen als Hinweis, speichern geht trotzdem.
5. **Absagen statt Löschen:** Ein ausfallender Termin wird als „fällt aus“ markiert
   (Schwimmer sehen es, Anwesenheit/Statistik bleiben stimmig), gelöscht wird nur Falsches.
6. **Eine Serie je Saison.** Zur neuen Saison wird eine neue Serie angelegt (Vorschlag aus der
   alten: Zeit, Gruppen, Trainer, Bahnen), weil sich Zusammensetzung und Zeiten ändern.
7. **Ferien:** Serien lassen Schulferien Schleswig-Holstein aus – einstellbar je Serie
   (Voreinstellung: auslassen).

Die vorhandene `recurrence_group_id` wird zur ID der Serie – bestehende Verweise
(ständige Absagen, Einzelzuweisungen je Serie) bleiben gültig.

## 3. Zielbild – Oberflächen

Heute: Liste, „Serie bearbeiten“, Einheit-Detail, „Einheit bearbeiten“ (mit Umschalter
Einheit/Serie), „Serie löschen“, „Neue Saison“ – vier Stellen zeigen fast dasselbe.

Künftig **zwei** Seiten mit klaren Zuständigkeiten:

**Serie** (ein Ort für alles Regelmäßige), Reiter:
- *Überblick:* Stammdaten direkt bearbeitbar („gilt ab …“), Bahnen, nächste Termine
- *Termine:* Liste mit Status und Abweichungen; Termin ausfallen lassen, einzeln ändern
- *Teilnehmer:* Gruppen, Einzel-Teilnehmer, ständige Absagen, erwartete Zahl
- *Saison:* nächste Saison planen (legt eine neue Serie an, vorbefüllt aus dieser),
  Serie beenden ab Datum, Serie komplett löschen (mit Übersicht, was verloren geht –
  auch vergangene Termine, Anwesenheiten, Pläne)

**Einheit** (ein Termin):
- Kopf: „Teil der Serie *Frühtraining* – Di 06:00, Bahn 1+2“ mit Link zur Serie;
  geerbte Werte sichtbar, Abweichungen markiert, Aktion „Nur diesen Termin ändern“
- Reiter: *Anwesenheit* · *Trainingsplan* · *Live-Zeitnahme* · *Einschätzungen*
- Ausnahme-Bahn buchen (Konfliktprüfung gegen Hallenplan und andere Ausnahmen)

**Hallenplan:** Konflikt mit einer Serienbelegung bietet „Serie öffnen“ und „Serie ab Datum
beenden“ statt Einheit für Einheit zu löschen. Verschieben einer Serienbelegung ändert die
Serie (ab heute).

**Liste Trainingseinheiten:** Serien und Einzeltermine getrennt, je Serie eine Zeile
(nächster Termin, Bahnen, Teilnehmer) – mobil als Karten.

## 4. Umsetzung in Schritten

| Schritt | Inhalt | Absicherung |
|---|---|---|
| 1 ✓ | Doppelbelegungen stoppen, Datenprüfung | Regressionstest `tests/e2e/series.spec.js` |
| 2 | Datenprüfung in Produktion ansehen, Bereinigung abstimmen | nur nach Freigabe |
| 3 ✓ (`eb4fcad`) | Tabelle `training_series` (Migration legt **nur die Struktur** an – der Deploy migriert automatisch). Übernahme des Bestands in einem eigenen Befehl mit Probelauf (`--dry-run` zeigt, was entstehen würde): Serienwerte = kommende Einheiten, Abweichungen erkennen, Belegungen an Serien (inkl. unverknüpfte Import-Belegungen). Ausgeführt erst nach Sichtung der Datenprüfung und Freigabe. | Probelauf, Datenprüfung vorher/nachher |
| 4 ✓ (`a4cac4c`, `4a915ee`) | Oberflächen Serie + Einheit (neue Bausteine, `x-ui.table`), alte Bearbeitungsseiten entfallen | Browsertests je Ablauf |
| 5 | Saisonplanung, Excel-Import und Hallenplan-Konflikte auf Serienebene | Browsertests |
| 6 | Live-Zeitnahme und Trainingsplan in der neuen Einheit-Seite prüfen | bestehende Tests |

## 5. Entscheidungen (Martin, 30.09.2026)

| # | Frage | Entscheidung |
|---|---|---|
| 1 | Gültigkeit von Serienänderungen | ab wählbarem Datum, vorbelegt mit heute |
| 2 | Einzeländerung vs. spätere Serienänderung | Einzeländerung gewinnt, Serie zeigt sie als Abweichung |
| 3 | Ausfall statt Löschen | ja, Termin „fällt aus“ – Benachrichtigung **auch per Mail** |
| 4 | Saisonwechsel | **neue Serie je Saison** (Gruppen und Zeiten ändern sich) |
| 5 | Ferien | nur **Schleswig-Holstein**; Attribut je Serie, Voreinstellung „auslassen“ |
| 6 | Ausnahme-Bahn einer Einheit | in der Einheit buchbar, Überschneidung wird angezeigt, **Speichern trotzdem möglich**; im Hallenplan **kein** Konflikt (Ausnahme) |
| 7 | Konflikt im Hallenplan mit Serie | dort „Serie ab Datum beenden“; in der Serie selbst ist **komplettes Löschen inkl. Vergangenheit** möglich |
| 8 | Trainingsplan-Vorlagen | **nicht** aus der Vorwoche. Stattdessen eigenes Konzept: KI-Vorschläge zur Vervollständigung eines Plans auf Basis früherer Pläne, lernend mit jedem Plan, ausgerichtet am Saisonverlauf (trainingsmethodische Makrozyklen) – siehe Abschnitt 6 |
| 9 | Excel-Import | automatische Verbindung von Belegung und Serie – neu versuchen, mit Vorschau und Datenprüfung |

## 6. Folgethema: KI-Unterstützung für Trainingspläne (eigenes Konzept)

Nicht Teil dieses Umbaus, aber durch ihn vorbereitet (Serie → Saison → Einheit gibt die
Einordnung im Saisonverlauf). Vor der Umsetzung mit Martin zu klären, weil es
trainingswissenschaftliche Fachfragen sind:

- Welche Zyklen gelten im Verein (Makro-/Meso-/Mikrozyklus, Wettkampfhöhepunkte der Saison,
  Periodisierungsmodell) und wo werden sie hinterlegt – je Gruppe, je Saison?
- Welche Merkmale eines Plans zählen für einen Vorschlag (Umfang, Intensitätsbereiche,
  Technik-/Ausdaueranteile, Lagen, Hilfsmittel)?
- „Lernfähig“: aus welchen Signalen – übernommene/abgelehnte Vorschläge, tatsächlich
  geschwommene Zeiten, Einschätzungen der Schwimmer?
- Datenschutz und Kosten der KI-Nutzung (bestehende Anbindung wie beim „Motto der Woche“?).

# Konzept: Trainingsserien, Einheiten und Hallenbelegung

Stand 30.09.2026 · Entwurf zur Abstimmung mit Martin (Entscheidungen in Abschnitt 5)

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
  Titel, Art, Wochentag, Beginn, Ende,  ───1:n──  Wöchentliche Belegung (hall_bookings.series_id)
  Ort, Rhythmus, gültig von/bis,                  – je Bahn genau eine, sichtbar im Plan
  Plätze, Anmeldung, Gastgruppe
  Gruppen, Trainer
  Einzel-Teilnehmer, ständige Absagen
      │ 1:n
      ▼
Einheit (training_sessions)
  Datum + geerbte Werte der Serie
  Abweichungen (nur was bewusst anders ist: Zeit, Ort, Titel …)
  Status: geplant | fällt aus
  Ausnahme-Bahnen (nur für diesen Termin, NICHT im Hallenplan)
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
   erscheinen in der Einheit und im Kalender, nicht im Hallenplan.
5. **Absagen statt Löschen:** Ein ausfallender Termin wird als „fällt aus“ markiert
   (Schwimmer sehen es, Anwesenheit/Statistik bleiben stimmig), gelöscht wird nur Falsches.

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
- *Saison:* verlängern / nächste Saison planen (Ferien ausgelassen), Serie beenden ab Datum

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
| 3 | Tabelle `training_series`, Übernahme des Bestands (Serienwerte = kommende Einheiten, Abweichungen erkennen), Belegungen an Serien (inkl. unverknüpfte Import-Belegungen), Datenprüfung vorher/nachher | Migration mit Probelauf auf Kopie |
| 4 | Oberflächen Serie + Einheit (neue Bausteine, `x-ui.table`), alte Bearbeitungsseiten entfallen | Browsertests je Ablauf |
| 5 | Saisonplanung, Excel-Import und Hallenplan-Konflikte auf Serienebene | Browsertests |
| 6 | Live-Zeitnahme und Trainingsplan in der neuen Einheit-Seite prüfen | bestehende Tests |

## 5. Entscheidungen für Martin

1. **Gültigkeit von Serienänderungen:** ab heute für alle kommenden Termine – oder ab einem
   wählbaren Datum (z. B. „ab den Herbstferien neue Zeit“)? *Vorschlag: wählbares Datum,
   vorbelegt mit heute.*
2. **Einzeländerung vs. spätere Serienänderung:** Termin am 14.10. wurde einzeln auf 07:00
   gelegt, danach ändert sich die Serie auf 06:30. Behält der 14.10. seine 07:00?
   *Vorschlag: ja, Einzeländerungen gewinnen; die Serie zeigt sie als Abweichung an.*
3. **Ausfall statt Löschen:** Soll ein Termin „fällt aus“ statt gelöscht werden (bleibt im
   Kalender durchgestrichen, Schwimmer werden benachrichtigt)? *Vorschlag: ja.*
4. **Saisonwechsel:** Dieselbe Serie über Saisons fortsetzen oder je Saison eine neue Serie?
   (Fortsetzen hält Teilnehmer/ständige Absagen; neue Serie trennt Statistiken.)
5. **Ferien:** Serien lassen Schulferien automatisch aus – SH, HH oder beide? Gilt das für
   alle Gruppen (z. B. auch Masters)?
6. **Ausnahme-Bahn einer Einheit (Workshop):** Soll gegen den Hallenplan geprüft werden
   (Warnung, wenn z. B. Bahn 3 an diesem Tag regulär belegt ist)? *Vorschlag: ja, Warnung.*
7. **Konflikt im Hallenplan mit einer Serie:** „Serie ab Datum beenden“ (Vergangenheit
   bleibt) oder auch komplett löschen anbieten? *Vorschlag: nur beenden; Löschen bleibt in
   der Serie mit Übersicht, was verloren geht.*
8. **Trainingsplan:** Heute je Einheit. Wünschst du Vorlagen je Serie („Plan der Vorwoche
   übernehmen“)?
9. **Excel-Import des Hallenplans:** Sollen importierte Belegungen und Serien künftig
   automatisch verbunden werden? *Vorschlag: ja.*

# E-Mail-Versand und Zugänge

Wie das Portal Mails verschickt, wer sie bekommt, und wie ein Mitglied zu
seinem Zugang kommt.

---

## 1. Grundregel: Opt-in

Ohne eigene Einstellung bekommt niemand Mails — **außer denen zum eigenen
Konto**. Die lassen sich nicht abbestellen, weil ohne sie kein Zugang
eingerichtet oder wiederhergestellt werden kann.

Alles andere schaltet jede Person selbst ein: **Mein Profil →
E-Mail-Benachrichtigungen**. Welche Themen dort auftauchen, hängt von der
Rolle ab; ein Schwimmer bekommt keine Trainer-Themen angeboten.

Der Themenkatalog steht in `app/Support/MailTopic.php`. Ein neues Thema
braucht dort einen Eintrag mit Beschriftung, Erklärung und den Rollen, denen
es angeboten wird — Profilseite und Mail-Protokoll richten sich danach.

Wechselt jemand die Rolle, verliert er automatisch die Themen, die zur neuen
Rolle nicht passen. Eine alte Einstellung soll nicht unbemerkt weiterwirken.

---

## 2. Wer bekommt was

| Ereignis | Empfänger | Thema |
|---|---|---|
| Konto angelegt, Einladung verschickt | die Person selbst | Konto (immer) |
| Passwort zurückgesetzt (selbst oder durch Admin) | die Person selbst | Konto (immer) |
| Passwort geändert | die Person selbst | Konto (immer) |
| Wettkampf-Abfrage gestartet | Sportler **und deren Eltern** | Einladungen |
| Erinnerung an offene Rückmeldung | Sportler und Eltern | Erinnerungen |
| Rückmeldung eingegangen | Trainer der betroffenen Gruppen | Rückantworten |
| Selbsteinschätzung abgegeben | Trainer der Gruppen | Selbsteinschätzungen |
| Ziel eingetragen | Trainer der Gruppen | Zielsetzungen |
| Ziel kommentiert | der Sportler | Ziele und Leistungskriterien |
| Leistungskriterium bewertet | der Sportler | Ziele und Leistungskriterien |
| Neuer Vereinsrekord | der Schwimmer + Trainer | eigene Rekorde / Vereinsrekorde |
| Motto der kommenden Woche fehlt (freitags) | die zuständige Person | Motto der Woche |

**Eltern bekommen die Nachrichten ihrer Kinder mit.** Bei jüngeren Mitgliedern
liest der Nachwuchs seine Mails nicht, die Eltern schon. Der Knopf in der Mail
führt für Eltern ins Elternportal, nicht auf eine Schwimmer-Seite, die sie
nicht öffnen dürfen.

Rekordmails entstehen **nur beim einzelnen neuen Ergebnis**, nicht beim
kompletten Neuaufbau der Rekordlisten (`recheckAll`). Sonst käme nach jeder
Bereinigung eine Flut über Rekorde von vorgestern.

---

## 3. Wartungsmodus: alle Mails an eine Testadresse

Solange der Wartungsmodus aktiv ist, geht **jede** Mail an die hinterlegte
Testadresse — mit dem eigentlichen Empfänger im Betreff:

```
[TEST an anna@example.de] Willkommen im WaRa-Portal
```

Einstellbar unter **Einstellungen → E-Mail-Versand**. Ist dort keine Adresse
hinterlegt, wird im Wartungsmodus **gar nichts** verschickt — lieber keine
Mail als eine echte Mail an ein Mitglied mitten in der Wartung.

Der Massenversand von Einladungen ist im Wartungsmodus gesperrt: mehrere
hundert Mails an die eigene Testadresse ist nie das, was jemand will.

---

## 4. Funktioniert der Versand?

**Einstellungen → E-Mail-Versand** zeigt den eingestellten Versandweg (Host,
Port, Absender, Zugangsname) und warnt, wenn die Angaben unvollständig
aussehen. Der Knopf **Testmail senden** liefert das Ergebnis direkt auf der
Seite — bei einem Fehlschlag mit der Meldung des Mailservers im Klartext.

Die Zugangsdaten des Mailservers stehen in der `.env` **auf dem Server**:

```
MAIL_MAILER=smtp
MAIL_HOST=…
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="portal@wasserratten.de"
```

---

## 5. Mail-Protokoll

**Einstellungen → Mail-Protokoll** hält jeden Versuch fest, auch die, bei
denen nichts verschickt wurde:

| Status | Bedeutung |
|---|---|
| versendet | an den Mailserver übergeben |
| wartet | in der Warteschlange, der Cron holt sie ab |
| nicht gewünscht | Thema im Profil nicht eingeschaltet oder keine Adresse hinterlegt |
| fehlgeschlagen | der Mailserver hat abgelehnt — der Grund steht in der Zeile |

Fehlgeschlagene Mails lassen sich einzeln erneut verschicken. Das Protokoll
wird nach zwölf Monaten automatisch gelöscht (`mails:purge-log`, monatlich).

Wichtig: „versendet" heißt *übergeben*, nicht *zugestellt*. Ob eine Mail im
Postfach ankommt oder im Spam landet, sieht das Portal nicht.

---

## 6. Warteschlange und Cron

Einzelne Mails gehen sofort raus. Der Massenversand (Einladungen an den
Bestand) läuft über eine Warteschlange, weil mehrere hundert Mails synchron
in den Zeitüberlauf des Webservers liefen.

Der Cron verschickt sie blockweise:

```
mails:process       jede Minute, 25 Mails je Lauf
mails:purge-log     monatlich, löscht Protokoll älter als 12 Monate
motto:remind        freitags 17:00
```

Alle drei hängen am bestehenden URL-Cron (`/cron/run/{token}`). Läuft der
nicht, bleiben Mails in der Warteschlange liegen — sichtbar im Protokoll als
„wartet".

---

## 7. So kommt ein Mitglied zu seinem Zugang

1. **Anlegen** in der Benutzerverwaltung, Häkchen bei „Konto sofort
   aktivieren".
2. Das Portal erzeugt ein **Initialpasswort** und zeigt es in der
   Benutzerverwaltung an, solange es nicht geändert wurde — für die
   persönliche Übergabe im Training.
3. Gleichzeitig geht die **Willkommensmail** raus: Begrüßung, Link zum Portal
   und ein **Einmallink**, über den sich die Person ihr Passwort selbst setzt.
   Der Link gilt 14 Tage und ist nach der ersten Verwendung verbraucht.
4. Beim ersten Login mit dem Initialpasswort **erzwingt** das Portal einen
   Passwortwechsel (`EnsurePasswordChanged`).

**In keiner Mail steht ein Passwort.** Wer die Mail später in die Finger
bekommt, findet darin nichts, was noch funktioniert.

### Bestandsmitglieder nachträglich einladen

**Benutzerverwaltung → Mitglieder einladen**: Liste aller aktiven Mitglieder
mit Adresse, Filter auf „noch nie angemeldet", Vermerk bei denen, die schon
eingeladen wurden. Der Versand läuft über die Warteschlange.

### Passwort zurücksetzen

- **Die Person selbst**: „Passwort vergessen?" auf der Anmeldeseite. Fünf
  Anforderungen je Adresse und Herkunft pro Stunde. Die Antwort verrät nie, ob
  es zu einer Adresse überhaupt einen Zugang gibt.
- **Trainer oder Admin**: Knopf in der Benutzerverwaltung. Setzt ein neues
  Initialpasswort (sichtbar für die persönliche Übergabe) **und** schickt eine
  Mail mit Einmallink. In der Mail steht, wer das Zurücksetzen veranlasst hat —
  sonst wirkt sie wie ein Angriffsversuch.

Nach jeder Passwortänderung geht eine Bestätigung raus. Das ist keine
Höflichkeit, sondern eine Sicherheitsmeldung: Wer sein Passwort nicht selbst
geändert hat, erfährt hier davon.

---

## 8. Anmeldung

- **Bremse**: fünf Fehlversuche je Kombination aus Adresse und Herkunft, dann
  eine Viertelstunde Pause — auch für das richtige Passwort. Eine erfolgreiche
  Anmeldung setzt den Zähler zurück. Eine andere Herkunft ist von einer Sperre
  nicht betroffen, damit nicht ein Angreifer das ganze Vereinsheim aussperrt.
- **Gleiche Meldung** für falsches Passwort und unbekannte Adresse.
- **Neue Sitzungs-ID** nach jeder Anmeldung und jeder Passwortänderung.
- **Passwortregeln**: mindestens zehn Zeichen mit Buchstaben und Ziffern.
- **Sitzungs-Cookie** wird automatisch als `secure` markiert, sobald `APP_URL`
  auf https zeigt. Wer lokal über http entwickelt, aber eine https-`APP_URL`
  hat, setzt `SESSION_SECURE_COOKIE=false` in seiner `.env` — sonst kommt keine
  Anmeldung zustande.
- **Gespeichert wird nur der Zeitpunkt** der letzten Anmeldung, keine
  IP-Adresse. Die Zählung der Fehlversuche liegt für 15 Minuten flüchtig im
  Cache und wird nicht dauerhaft abgelegt.

---

## 9. Wo was liegt

| Datei | Zweck |
|---|---|
| `app/Services/Mailer.php` | einziger Versandweg: Opt-in, Umleitung, Protokoll |
| `app/Services/EventMailer.php` | baut die Ereignis-Mails, bestimmt die Empfänger |
| `app/Services/AccountLinkService.php` | Einmallinks für Einrichtung und Zurücksetzen |
| `app/Support/MailTopic.php` | Themenkatalog und Opt-in-Regeln |
| `app/Models/MailMessage.php` | Protokoll und Warteschlange |
| `app/Mail/NotificationMail.php` | eine Vorlage für alle Ereignis-Mails |
| `resources/views/emails/` | Mailvorlagen (schlichtes HTML mit Inline-Styles) |
| `config/auth.php` | zwei Broker: `users` (1 Stunde), `welcome` (14 Tage) |
| `config/session.php` | Sitzungs-Cookie, secure abhängig von `APP_URL` |

Ein Fehler beim Versand wirft nie in die Anfrage zurück: Eine nicht
zugestellte Mail darf keine Seite zerlegen, die inhaltlich längst fertig ist.
Er landet im Protokoll und im Laravel-Log.

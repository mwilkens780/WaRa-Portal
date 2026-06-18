@extends('layouts.legal')
@section('title', 'Datenschutzerklärung')

@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-2">Datenschutzerklärung</h1>
<p class="text-sm text-gray-500 mb-8">Gemäß Art. 13, 14 DSGVO – Stand: Juni 2026</p>

<div class="space-y-8 text-sm text-gray-700">

    {{-- 1. Verantwortlicher --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">1. Verantwortlicher</h2>
        <p>
            Verantwortlich für die Verarbeitung personenbezogener Daten in diesem Portal ist:<br><br>
            <strong>SG Wasserratten Norderstedt e.V.</strong><br>
            {{-- ↓ BITTE ANPASSEN ↓ --}}
            [Straße], 22844 Norderstedt<br>
            E-Mail: <a href="mailto:[email@wasserratten.de]" class="text-primary hover:underline">[email@wasserratten.de]</a>
        </p>
        <p class="mt-2">
            Bei Fragen zum Datenschutz oder zur Geltendmachung Ihrer Rechte wenden Sie sich bitte an die oben genannte Adresse
            oder per E-Mail mit dem Betreff <em>„DSGVO-Anfrage"</em>.
        </p>
    </section>

    {{-- 2. Allgemeines zur Verarbeitung --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">2. Grundsätze der Verarbeitung</h2>
        <p>
            Das WaRa-Portal ist ein <strong>geschlossenes Mitgliederportal</strong> der SG Wasserratten Norderstedt e.V.
            Der Zugang ist ausschließlich registrierten Vereinsmitgliedern und Berechtigten (Trainer, Elternteile, Vorstand)
            vorbehalten. Alle Zugriffe werden durch Passwortschutz und rollenbasierte Berechtigungen gesichert.
        </p>
        <p class="mt-2">
            Wir verarbeiten personenbezogene Daten nur im erforderlichen Umfang und auf Basis einer
            zulässigen Rechtsgrundlage.
        </p>
    </section>

    {{-- 3. Verarbeitete Daten & Zwecke --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">3. Verarbeitete Daten, Zwecke und Rechtsgrundlagen</h2>

        <div class="space-y-5">
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-1">3.1 Mitgliedsstammdaten</h3>
                <p><strong>Daten:</strong> Vorname, Nachname, E-Mail-Adresse(n), Geburtsdatum, Geschlecht, Anschrift, Telefon, Mobilnummer, Mitgliedsnummer, Eintrittsdatum, DSV-Mitgliedsnummer, Trainingsgruppe.</p>
                <p class="mt-1"><strong>Zweck:</strong> Verwaltung der Vereinsmitgliedschaft, Organisation des Sportbetriebs, Kommunikation, Meldewesen gegenüber dem Deutschen Schwimm-Verband (DSV).</p>
                <p class="mt-1"><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung – Mitgliedschaftsvertrag), Art. 9 Abs. 2 lit. d DSGVO (Verarbeitung durch gemeinnützige Organisationen).</p>
                <p class="mt-1"><strong>Speicherdauer:</strong> Für die Dauer der Mitgliedschaft. Nach Beendigung werden die Daten entsprechend den gesetzlichen Aufbewahrungspflichten (§ 257 HGB, § 147 AO) bis zu 10 Jahre aufbewahrt und danach gelöscht.</p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-1">3.2 Trainingsaufzeichnungen</h3>
                <p><strong>Daten:</strong> Anwesenheit bei Trainingseinheiten (Datum, Einheit, Anwesenheitsstatus), Trainingstagebucheinträge, Schwimmzeiten im Training, Ziele und Fortschritte.</p>
                <p class="mt-1"><strong>Zweck:</strong> Dokumentation der sportlichen Entwicklung, Trainingsplanung, Leistungsanalyse.</p>
                <p class="mt-1"><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (Berechtigte Interessen des Vereins an der Trainingsorganisation und Mitgliederförderung).</p>
                <p class="mt-1"><strong>Speicherdauer:</strong> Anwesenheitsdaten 3 Jahre nach der jeweiligen Saison, Bestzeiten und Ziele für die Dauer der Mitgliedschaft.</p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-1">3.3 Wettkampfergebnisse</h3>
                <p><strong>Daten:</strong> Ergebnisse bei Wettkämpfen (Disziplin, Distanz, Zeit, Platzierung, Verein), Vereins- und Landesrekorde.</p>
                <p class="mt-1"><strong>Zweck:</strong> Dokumentation sportlicher Leistungen, Vereinschronik, Meldung an Verbände.</p>
                <p class="mt-1"><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (Berechtigte Interessen; Wettkampfergebnisse sind im Sportbereich herkömmlich öffentlich und Teil der Vereinsgeschichte). Art. 9 Abs. 2 lit. d DSGVO.</p>
                <p class="mt-1"><strong>Speicherdauer:</strong> Wettkampfergebnisse werden als Teil der Vereinschronik dauerhaft gespeichert. Persönliche Zuordnungen können auf Antrag anonymisiert werden (Art. 17 DSGVO), sofern keine vorrangigen berechtigten Interessen bestehen.</p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-1">3.4 Qualifikations- und Lizenzdaten (Trainer / Kampfrichter)</h3>
                <p><strong>Daten:</strong> Trainerlizenz, Kampfrichter-Lizenz, Rettungsschwimmschein, Erste-Hilfe-Nachweis, Datum des erweiterten Führungszeugnisses.</p>
                <p class="mt-1"><strong>Zweck:</strong> Nachweis der Qualifikation für den geregelten Sportbetrieb, Erfüllung verbandsrechtlicher Pflichten.</p>
                <p class="mt-1"><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. c DSGVO (rechtliche Verpflichtung), Art. 6 Abs. 1 lit. f DSGVO.</p>
                <p class="mt-1"><strong>Speicherdauer:</strong> Für die Dauer der jeweiligen Funktion, danach 3 Jahre.</p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-1">3.5 Support-Tickets</h3>
                <p><strong>Daten:</strong> Name, Beschreibung des Anliegens, Zeitpunkt der Einreichung.</p>
                <p class="mt-1"><strong>Zweck:</strong> Bearbeitung von Fehlermeldungen und Verbesserungsvorschlägen zum Portal.</p>
                <p class="mt-1"><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO.</p>
                <p class="mt-1"><strong>Drittlandübermittlung:</strong> Support-Tickets werden als Issues in einem privaten Repository auf <strong>GitHub (GitHub, Inc., USA)</strong> gespeichert. GitHub ist nach dem EU-U.S. Data Privacy Framework zertifiziert (Art. 45 DSGVO). Das Repository ist nicht öffentlich zugänglich.</p>
                <p class="mt-1"><strong>Speicherdauer:</strong> 2 Jahre nach Schließung des Tickets.</p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h3 class="font-semibold text-gray-800 mb-1">3.6 Protokoll- und Zugriffsdaten</h3>
                <p><strong>Daten:</strong> Systemprotokoll (Änderungen an Datensätzen mit Zeitstempel und Benutzer-ID), keine IP-Adressen oder Web-Server-Logs durch dieses Portal.</p>
                <p class="mt-1"><strong>Zweck:</strong> Nachvollziehbarkeit von Datenänderungen, Schutz vor unberechtigten Zugriffen, Fehleranalyse.</p>
                <p class="mt-1"><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO.</p>
                <p class="mt-1"><strong>Speicherdauer:</strong> 6 Monate, danach automatische Löschung.</p>
            </div>
        </div>
    </section>

    {{-- 4. Cookies & Session --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">4. Cookies und Session-Daten</h2>
        <p>
            Das Portal verwendet ausschließlich technisch notwendige Session-Cookies zur Aufrechterhaltung der
            angemeldeten Sitzung. Es werden keine Tracking-, Analyse- oder Marketing-Cookies eingesetzt.
            Die Session-Daten werden beim Abmelden oder nach Ablauf der Sitzung automatisch gelöscht.
        </p>
        <p class="mt-2">
            Eine Einwilligung nach Art. 6 Abs. 1 lit. a DSGVO ist für technisch notwendige Cookies nicht erforderlich.
        </p>
    </section>

    {{-- 5. Datenweitergabe --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">5. Datenweitergabe an Dritte</h2>
        <ul class="list-disc list-inside space-y-1">
            <li><strong>Deutscher Schwimm-Verband (DSV) / Landesverbände:</strong> Meldung von Mitgliedern und Wettkampfergebnissen auf Basis verbandsrechtlicher Pflichten (Art. 6 Abs. 1 lit. c / lit. b DSGVO).</li>
            <li><strong>GitHub, Inc. (USA):</strong> Speicherung von Support-Tickets in einem privaten Repository (siehe 3.5).</li>
            <li><strong>Hosting-Anbieter:</strong> Der Webserver wird bei einem deutschen Hosting-Anbieter betrieben. Eine Auftragsverarbeitungsvereinbarung (AVV) gemäß Art. 28 DSGVO liegt vor.</li>
        </ul>
        <p class="mt-2">Darüber hinaus werden keine personenbezogenen Daten an Dritte weitergegeben.</p>
    </section>

    {{-- 6. Betroffenenrechte --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">6. Ihre Rechte als betroffene Person</h2>
        <p>Gemäß der DSGVO haben Sie folgende Rechte:</p>
        <div class="mt-3 space-y-2">
            <div class="flex gap-3">
                <span class="text-xs font-bold bg-primary text-white px-1.5 py-0.5 rounded shrink-0 mt-0.5">Art. 15</span>
                <div><strong>Auskunftsrecht:</strong> Sie können Auskunft über alle zu Ihrer Person gespeicherten Daten verlangen.</div>
            </div>
            <div class="flex gap-3">
                <span class="text-xs font-bold bg-primary text-white px-1.5 py-0.5 rounded shrink-0 mt-0.5">Art. 16</span>
                <div><strong>Berichtigungsrecht:</strong> Sie können die Berichtigung unrichtiger oder unvollständiger Daten verlangen.</div>
            </div>
            <div class="flex gap-3">
                <span class="text-xs font-bold bg-primary text-white px-1.5 py-0.5 rounded shrink-0 mt-0.5">Art. 17</span>
                <div><strong>Recht auf Löschung:</strong> Sie können die Löschung Ihrer Daten verlangen, soweit keine gesetzlichen Aufbewahrungspflichten oder überwiegende berechtigte Interessen entgegenstehen.</div>
            </div>
            <div class="flex gap-3">
                <span class="text-xs font-bold bg-primary text-white px-1.5 py-0.5 rounded shrink-0 mt-0.5">Art. 18</span>
                <div><strong>Recht auf Einschränkung:</strong> Sie können die Einschränkung der Verarbeitung Ihrer Daten verlangen.</div>
            </div>
            <div class="flex gap-3">
                <span class="text-xs font-bold bg-primary text-white px-1.5 py-0.5 rounded shrink-0 mt-0.5">Art. 20</span>
                <div><strong>Datenübertragbarkeit:</strong> Sie können Ihre Daten in einem maschinenlesbaren Format erhalten.</div>
            </div>
            <div class="flex gap-3">
                <span class="text-xs font-bold bg-primary text-white px-1.5 py-0.5 rounded shrink-0 mt-0.5">Art. 21</span>
                <div><strong>Widerspruchsrecht:</strong> Sie können der Verarbeitung Ihrer Daten auf Basis berechtigter Interessen jederzeit widersprechen.</div>
            </div>
        </div>
        <p class="mt-4">
            Zur Ausübung Ihrer Rechte wenden Sie sich bitte per E-Mail mit dem Betreff <em>„DSGVO-Anfrage"</em>
            an die in Abschnitt 1 genannte Kontaktadresse. Wir bearbeiten Ihr Anliegen innerhalb von
            <strong>30 Tagen</strong> (Art. 12 Abs. 3 DSGVO).
        </p>
    </section>

    {{-- 7. Beschwerderecht --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">7. Beschwerderecht bei der Aufsichtsbehörde</h2>
        <p>
            Sie haben das Recht, sich bei einer Datenschutz-Aufsichtsbehörde zu beschweren (Art. 77 DSGVO).
            Für Schleswig-Holstein ist dies:
        </p>
        <p class="mt-2">
            <strong>Unabhängiges Landeszentrum für Datenschutz Schleswig-Holstein (ULD)</strong><br>
            Holstenstraße 98, 24103 Kiel<br>
            Telefon: 0431 988-1200<br>
            E-Mail: <a href="mailto:mail@datenschutzzentrum.de" class="text-primary hover:underline">mail@datenschutzzentrum.de</a><br>
            <a href="https://www.datenschutzzentrum.de" class="text-primary hover:underline" target="_blank" rel="noopener">www.datenschutzzentrum.de</a>
        </p>
    </section>

    {{-- 8. Datensicherheit --}}
    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">8. Datensicherheit</h2>
        <p>
            Das Portal verwendet HTTPS-Verschlüsselung. Passwörter werden ausschließlich als
            Bcrypt-Hashes gespeichert. Der Zugang ist auf registrierte und vom Administrator
            freigeschaltete Benutzer beschränkt. Sensible Felder werden im Systemprotokoll nicht erfasst.
        </p>
    </section>

</div>
@endsection

@extends('layouts.legal')
@section('title', 'Impressum')

@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-8">Impressum</h1>

<div class="prose prose-sm max-w-none space-y-6 text-gray-700">

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Angaben gemäß § 5 TMG</h2>
        <p>
            <strong>SG Wasserratten Norderstedt e.V.</strong><br>
            {{-- ↓ BITTE ANPASSEN ↓ --}}
            [Straße und Hausnummer]<br>
            22844 Norderstedt<br>
            Deutschland
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Vertreten durch</h2>
        <p>
            {{-- ↓ BITTE ANPASSEN: 1. Vorsitzende/r ↓ --}}
            [Vorname Nachname], 1. Vorsitzende/r
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Kontakt</h2>
        <p>
            {{-- ↓ BITTE ANPASSEN ↓ --}}
            E-Mail: <a href="mailto:[email@wasserratten.de]" class="text-primary hover:underline">[email@wasserratten.de]</a><br>
            Telefon: [Telefonnummer]
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Vereinsregister</h2>
        <p>
            {{-- ↓ BITTE ANPASSEN ↓ --}}
            Registergericht: Amtsgericht [Norderstedt/Hamburg]<br>
            Vereinsregisternummer: VR [NUMMER]
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Verantwortlich für den Inhalt gemäß § 18 Abs. 2 MStV</h2>
        <p>
            {{-- ↓ BITTE ANPASSEN ↓ --}}
            [Vorname Nachname]<br>
            [Straße und Hausnummer]<br>
            22844 Norderstedt
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Datenschutzbeauftragter</h2>
        <p>
            {{-- ↓ Wenn kein eigener DSB bestellt: ↓ --}}
            Für Fragen zum Datenschutz wenden Sie sich bitte an:<br>
            {{-- ↓ BITTE ANPASSEN ↓ --}}
            [Vorname Nachname]<br>
            E-Mail: <a href="mailto:[datenschutz@wasserratten.de]" class="text-primary hover:underline">[datenschutz@wasserratten.de]</a>
        </p>
        <p class="text-xs text-gray-500 mt-1">
            Hinweis: Gemeinnützige Vereine mit weniger als 20 Personen, die regelmäßig personenbezogene Daten
            verarbeiten, sind gemäß Art. 37 DSGVO i.V.m. § 38 BDSG in der Regel nicht verpflichtet, einen
            Datenschutzbeauftragten zu bestellen.
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Haftungsausschluss</h2>
        <p class="text-sm">
            Die Inhalte dieses Portals wurden mit größter Sorgfalt erstellt.
            Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte kann keine Gewähr übernommen werden.
            Als Diensteanbieter sind wir gemäß § 7 Abs. 1 TMG für eigene Inhalte auf diesen Seiten nach den
            allgemeinen Gesetzen verantwortlich.
        </p>
    </section>

</div>

<p class="mt-10 text-xs text-gray-400">Stand: Juni 2026</p>
@endsection

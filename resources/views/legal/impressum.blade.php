@extends('layouts.legal')
@section('title', 'Impressum')

{{-- Inhalt abgeglichen mit https://www.wasserratten.de/index.php/impressum (Stand 23.09.2026).
     Aenderungen am Vereinsimpressum bitte hier nachziehen. --}}

@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-8">Impressum</h1>

<div class="prose prose-sm max-w-none space-y-6 text-gray-700">

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">SG Wasserratten Norderstedt e.V.</h2>
        <p>
            Wiesenstrasse 50 a, 22850 Norderstedt<br>
            Tel. 040-521 109 37 / Fax: 040-521 109 38<br>
            Homepage: <a href="https://www.wasserratten.de" class="text-primary hover:underline" target="_blank" rel="noopener">www.wasserratten.de</a><br>
            E-Mail: <a href="mailto:info@wasserratten.de" class="text-primary hover:underline">info@wasserratten.de</a>
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Vertretungsberechtigter Vorstand nach § 26 BGB</h2>
        <p>
            Manfred Fitz (1. Vorsitzender)<br>
            Marc Buchholz (2. Vorsitzender)<br>
            Denis Christesen (Geschäftsführer)<br>
            Sven Pawlowski (Kassenwart)
        </p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Für den Inhalt verantwortlich</h2>
        <p>Denis Christesen (Geschäftsführer)</p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Vereinsregister</h2>
        <p>Amtsgericht Kiel, VR 236 NO</p>
    </section>

    <section>
        <h2 class="text-base font-semibold text-gray-800 mb-2">Haftungsausschluss</h2>
        <p class="text-sm">
            Die Inhalte unserer Seiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit
            und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen. Trotz sorgfältiger inhaltlicher
            Kontrolle übernehmen wir keine Haftung für die Inhalte externer Links. Für den Inhalt der verlinkten
            Seiten sind ausschließlich deren Betreiber verantwortlich.
        </p>
    </section>

</div>

<p class="mt-10 text-xs text-gray-400">
    Stand: September 2026 · entspricht dem
    <a href="https://www.wasserratten.de/index.php/impressum" class="underline hover:text-gray-600" target="_blank" rel="noopener">Impressum der Vereinsseite</a>
</p>
@endsection

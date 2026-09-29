@extends('layouts.app')
@section('title', 'DSV-Ergebnisdatei importieren')
@section('page-title', 'DSV-Ergebnisdatei importieren')

@section('content')
<div class="mt-2 max-w-2xl space-y-6">

    <x-ui.import-steps :current="session('import_success') ? 3 : 1" />

    {{-- Erfolgsmeldung nach Import --}}
    @if(session('import_success'))
        @php $s = session('import_success'); @endphp
        <div class="bg-green-50 border border-green-200 rounded-xl p-5">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-green-700 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="font-semibold text-green-800">
                        {{ !empty($s['merged']) ? 'Mit vorhandenem Wettkampf zusammengeführt' : 'Import erfolgreich' }}
                    </p>
                    <p class="text-sm text-green-800 mt-1">
                        <strong>{{ $s['competition'] }}</strong> ({{ $s['date'] }}) —
                        {{ $s['imported'] }} Ergebnis{{ $s['imported'] !== 1 ? 'se' : '' }} neu,
                        @if(($s['duplicates'] ?? 0) > 0)
                            {{ $s['duplicates'] }} schon vorhanden (nicht doppelt angelegt),
                        @endif
                        {{ $s['skipped'] }} Athlet{{ $s['skipped'] !== 1 ? 'en' : '' }} ohne Zuordnung übersprungen.
                    </p>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.competitions.show', $s['comp_id']) }}"
                           class="text-sm text-green-700 font-medium underline mt-1 inline-block">
                            Wettkampf ansehen →
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Schritt 1 wie in allen Importen (x-ui.upload-form / x-ui.file-drop) --}}
    <x-ui.card>
        <p class="text-sm text-gray-700 mb-4">
            Übernimmt die Zeiten der zugeordneten Schwimmer aus einer Ergebnisdatei. Gibt es die Veranstaltung schon
            (z. B. aus WebClub oder der Ausschreibung), werden die Ergebnisse mit ihr <strong>zusammengeführt</strong>,
            sonst entsteht ein neuer Wettkampf. DQ, DNS und DNF werden übersprungen, Bestzeiten erkannt.
        </p>
        <x-ui.upload-form :action="route('trainer.dsv-import.upload')">
            <x-ui.file-drop name="dsv_file" label="Ergebnisdatei (Lenex/DSV)" accept=".dsv7,.lef,.xml,.txt" :max-mb="20"
                            hint="Aus DSV, Swimrankings oder WebClub (Lenex 2.0/3.0)." />
        </x-ui.upload-form>
    </x-ui.card>

    <a href="{{ route('imports.index') }}" class="inline-block text-sm text-gray-700 hover:text-primary">← Import-Center</a>

</div>
@endsection

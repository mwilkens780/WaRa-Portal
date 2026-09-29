@extends('layouts.app')

@section('title', 'Mitglieder aus WebClub')
@section('page-title', 'Mitglieder aus WebClub importieren')

@section('content')
<div class="mt-2 max-w-2xl space-y-6">
    <x-ui.import-steps :current="session('import_success') ? 3 : 1" />

    {{-- Success / partial-success banner --}}
    @if(session('import_success'))
        @php $r = session('import_success'); $hasErrors = !empty($r['errors']); @endphp
        <div class="{{ $hasErrors ? 'bg-amber-50 border-amber-300' : 'bg-green-50 border-green-200' }} border rounded-xl px-5 py-4 space-y-2">
            <p class="font-semibold {{ $hasErrors ? 'text-amber-800' : 'text-green-800' }}">
                Import {{ $hasErrors ? 'mit Fehlern abgeschlossen' : 'abgeschlossen' }}
            </p>
            <ul class="text-sm {{ $hasErrors ? 'text-amber-700' : 'text-green-700' }} space-y-0.5">
                <li>{{ $r['created'] }} Mitglied{{ $r['created'] !== 1 ? 'er' : '' }} neu angelegt</li>
                <li>{{ $r['updated'] }} Mitglied{{ $r['updated'] !== 1 ? 'er' : '' }} aktualisiert</li>
                <li>{{ $r['skipped'] }} Zeile{{ $r['skipped'] !== 1 ? 'n' : '' }} übersprungen</li>
            </ul>
            @if($hasErrors)
                <details class="mt-2" open>
                    <summary class="text-sm font-medium text-amber-800 cursor-pointer">
                        {{ count($r['errors']) }} Fehler beim Import
                    </summary>
                    <ul class="mt-2 space-y-1">
                        @foreach($r['errors'] as $err)
                            <li class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-1.5">
                                <span class="font-semibold">{{ $err['name'] }}:</span> {{ $err['message'] }}
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    {{-- Upload card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
        <div>
            <h2 class="text-base font-semibold text-gray-900">CSV-Datei hochladen</h2>
            <p class="text-sm text-gray-700 mt-1">
                Exportiere die Mitgliederliste aus WebClub als CSV (Semikolon-getrennt) und lade sie hier hoch.
            </p>
        </div>

        {{-- Info box --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-800 space-y-1.5">
            <p class="font-semibold">Hinweise zum Export</p>
            <ul class="list-disc list-inside space-y-1 text-blue-700">
                <li>Export unter <strong>Mitglieder → Exportieren → CSV</strong> in WebClub</li>
                <li>Zeichensatz: Windows-1252 (wird automatisch konvertiert)</li>
                <li>Pflichtfelder: <code class="bg-blue-100 px-1 rounded">Name</code>, <code class="bg-blue-100 px-1 rounded">Vorname</code>, <code class="bg-blue-100 px-1 rounded">AktiverSchwimmer</code>, <code class="bg-blue-100 px-1 rounded">AktiverTrainer</code></li>
                <li>Importiert werden nur <strong>aktive Schwimmer</strong> und <strong>aktive Trainer</strong></li>
                <li>Bestehende Mitglieder werden per DSV-ID oder Name + Geburtsdatum erkannt und aktualisiert</li>
                <li>Passwörter und E-Mail-Adressen bestehender Konten werden <strong>nicht überschrieben</strong></li>
                <li>Neue Mitglieder ohne E-Mail erhalten eine Platzhalter-Adresse (<code class="bg-blue-100 px-1 rounded">@mitglied.wasserratten.intern</code>)</li>
            </ul>
        </div>

        <x-ui.upload-form :action="route('admin.webclub-import.upload')">
            <x-ui.file-drop name="csv_file" label="Mitgliederliste (CSV)" accept=".csv,.txt" :max-mb="10" />
        </x-ui.upload-form>
    </div>

    <a href="{{ route('imports.index') }}" class="inline-block text-sm text-gray-700 hover:text-primary">← Import-Center</a>
</div>
@endsection

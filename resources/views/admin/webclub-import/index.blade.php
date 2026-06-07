@extends('layouts.app')

@section('title', 'WebClub Mitglieder-Import')
@section('page-title', 'WebClub Mitglieder-Import')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

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
            <p class="text-sm text-gray-500 mt-1">
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

        <form method="POST" action="{{ route('admin.webclub-import.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-1">
                        CSV-Datei <span class="text-red-500">*</span>
                    </label>
                    <input type="file"
                           id="csv_file"
                           name="csv_file"
                           accept=".csv,.txt"
                           class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700 cursor-pointer border border-gray-300 rounded-lg p-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('csv_file')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Datei einlesen &amp; Vorschau anzeigen
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

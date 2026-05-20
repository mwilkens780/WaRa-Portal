@extends('layouts.app')
@section('title', 'DSV-Ergebnisdatei importieren')
@section('page-title', 'DSV-Ergebnisdatei importieren')

@section('content')
<div class="mt-2 max-w-2xl space-y-6">

    {{-- Erfolgsmeldung nach Import --}}
    @if(session('import_success'))
        @php $s = session('import_success'); @endphp
        <div class="bg-green-50 border border-green-200 rounded-xl p-5">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="font-semibold text-green-800">Import erfolgreich!</p>
                    <p class="text-sm text-green-700 mt-1">
                        <strong>{{ $s['competition'] }}</strong> ({{ $s['date'] }}) —
                        {{ $s['imported'] }} Ergebnis{{ $s['imported'] !== 1 ? 'se' : '' }} importiert,
                        {{ $s['skipped'] }} Athlet{{ $s['skipped'] !== 1 ? 'en' : '' }} übersprungen.
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

    {{-- Info-Box: Format --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
        <h2 class="font-semibold text-blue-800 mb-2">Unterstütztes Format: Lenex XML (DSV6/7)</h2>
        <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside">
            <li>Dateiendungen: <strong>.dsv7</strong>, <strong>.lef</strong>, <strong>.xml</strong>, <strong>.txt</strong></li>
            <li>Ergebnisdateien vom DSV, Swimrankings oder WebClub (Lenex 2.0 / 3.0)</li>
            <li>DQ, DNS und DNF werden automatisch übersprungen</li>
            <li>Bestzeiten werden automatisch erkannt und aktualisiert</li>
        </ul>
    </div>

    {{-- Upload-Formular --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('trainer.dsv-import.upload') }}"
              enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Lenex-Ergebnisdatei auswählen <span class="text-red-500">*</span>
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary transition-colors">
                    <svg class="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-gray-500 mb-3">.dsv7, .lef, .xml oder .txt – max. 20 MB</p>
                    <input type="file" name="dsv_file" accept=".xml,.lef,.txt,.dsv7"
                           class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
                </div>
                @error('dsv_file')
                    <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-3 rounded-lg transition-colors flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Datei einlesen und Vorschau anzeigen
            </button>
        </form>
    </div>

</div>
@endsection

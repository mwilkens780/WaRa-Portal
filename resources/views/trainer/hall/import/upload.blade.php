@extends('layouts.app')
@section('title', 'Belegungsplan importieren')
@section('page-title', 'Belegungsplan importieren')

@section('content')
<div class="mt-2 max-w-3xl space-y-5">

    @if(session('error'))
        <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="font-semibold text-gray-800 mb-1">Excel-Datei hochladen</h2>
        <p class="text-sm text-gray-500 mb-4">
            Erwartet wird die Vorlage „Hallenbelegung SuV“ im Format <code class="text-xs">.xlsx</code>.
            Gelesen wird das erste sichtbare Arbeitsblatt – ältere Stände sind dort in der Regel ausgeblendet.
        </p>

        <form method="POST" action="{{ route('trainer.hall.import.upload') }}" enctype="multipart/form-data"
              class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Belegungsplan</label>
                <input type="file" name="plan" accept=".xlsx" required
                       class="block w-full text-sm text-gray-600 border border-gray-200 rounded-lg
                              file:mr-3 file:py-2 file:px-4 file:rounded-l-lg file:border-0
                              file:text-sm file:font-medium file:bg-gray-50 file:text-gray-700
                              hover:file:bg-gray-100 cursor-pointer">
                @error('plan')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2 rounded-lg
                           text-sm transition-colors">
                Einlesen und prüfen
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="font-semibold text-gray-800 mb-3">Was der Import tut</h2>

        <ul class="text-sm text-gray-600 space-y-2">
            <li class="flex gap-2">
                <span class="text-gray-400">•</span>
                <span>Farbige Blöcke werden zu <strong>Hallenbelegungen</strong>. Kurse, DLRG,
                      TGW, SVF und Synchro erzeugen ausschließlich eine Belegung.</span>
            </li>
            <li class="flex gap-2">
                <span class="text-gray-400">•</span>
                <span>Leistungssport, Breitensport, Masters, Triathlon sowie Delfine und Robben
                      erzeugen zusätzlich eine <strong>Trainingsserie</strong> – aber nur, wenn
                      Gruppe und Trainer zugeordnet sind.</span>
            </li>
            <li class="flex gap-2">
                <span class="text-gray-400">•</span>
                <span>Serien laufen über die Saison
                      @if($season)
                          <strong>{{ $season->name }}</strong>
                          ({{ $season->start_date->format('d.m.Y') }} – {{ $season->end_date->format('d.m.Y') }})
                      @endif
                      und sparen Ferien aus.</span>
            </li>
            <li class="flex gap-2">
                <span class="text-green-500">•</span>
                <span><strong>Bestehendes wird nie verändert.</strong> Überschneidet sich eine Zeile
                      mit einer vorhandenen Belegung, wird sie übersprungen und in der Vorschau
                      ausgewiesen.</span>
            </li>
        </ul>
    </div>

    <a href="{{ route('trainer.hall.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zum Belegungsplan
    </a>
</div>
@endsection

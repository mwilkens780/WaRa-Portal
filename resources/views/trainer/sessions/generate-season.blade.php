@extends('layouts.app')
@section('title', 'Neue Saison generieren')
@section('page-title', 'Neue Saison generieren')

@section('content')
<div class="max-w-xl mt-2 space-y-4">

    {{-- Info-Banner --}}
    @if($hasFuture)
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-blue-700">
            Diese Serie hat noch zukünftige Einheiten. Du kannst trotzdem neue Termine hinzufügen –
            sie werden zum selben <code class="font-mono text-xs">recurrence_group_id</code>-Verbund hinzugefügt.
        </p>
    </div>
    @else
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <p class="text-sm text-amber-800">
            Diese Trainingsserie ist ausgelaufen. Lege neue Einheiten für die nächste Saison an.
            Bisherige Einheiten werden nicht verändert.
        </p>
    </div>
    @endif

    {{-- Serie-Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Seriendefinition</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <dt class="text-gray-500">Titel</dt>
            <dd class="text-gray-800 font-medium">{{ $rep->title }}</dd>
            <dt class="text-gray-500">Typ</dt>
            <dd class="text-gray-800">{{ $rep->type_label }}</dd>
            <dt class="text-gray-500">Zeit</dt>
            <dd class="text-gray-800">
                {{ substr($rep->start_time, 0, 5) }}@if($rep->end_time) – {{ substr($rep->end_time, 0, 5) }}@endif
            </dd>
            <dt class="text-gray-500">Ort</dt>
            <dd class="text-gray-800">{{ $rep->location }}</dd>
            <dt class="text-gray-500">Gruppen</dt>
            <dd class="text-gray-800">{{ $rep->trainingGroups->pluck('name')->join(', ') ?: '–' }}</dd>
            <dt class="text-gray-500">Bisherige Einheiten</dt>
            <dd class="text-gray-800">{{ $sessions->count() }} ({{ $sessions->first()->date->format('d.m.Y') }} – {{ $sessions->last()->date->format('d.m.Y') }})</dd>
        </dl>
        <p class="text-xs text-gray-400 mt-3">
            <a href="{{ route('trainer.sessions.series.edit', $group) }}" class="underline hover:text-gray-600">
                Seriendefinition zuerst anpassen?
            </a>
        </p>
    </div>

    {{-- Saison-Formular --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700">Neue Einheiten generieren</h3>
            @if($suggestedSeason)
                <span class="text-xs bg-blue-50 text-primary px-2.5 py-1 rounded-full border border-blue-100 font-medium">
                    Vorschlag: Saison {{ $suggestedSeason->name }}
                    @if($seriesSeason && $seriesSeason->id !== $suggestedSeason->id)
                        <span class="text-gray-400 font-normal">(Folgesaison nach {{ $seriesSeason->name }})</span>
                    @endif
                </span>
            @endif
        </div>
        <form method="POST" action="{{ route('trainer.sessions.series.store-season', $group) }}" class="space-y-5">
            @csrf

            {{-- Startdatum --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Startdatum</label>
                <input type="date" name="start_date"
                       value="{{ old('start_date', $suggestedSeason?->start_date?->format('Y-m-d') ?? '') }}"
                       min="{{ today()->format('Y-m-d') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                       required>
                @error('start_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Enddatum --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Enddatum</label>
                <input type="date" name="end_date"
                       value="{{ old('end_date', $suggestedSeason?->end_date?->format('Y-m-d') ?? '') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                       required>
                @error('end_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Kadenz --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kadenz</label>
                @php
                    $defaultType = in_array($rep->recurrence_type, ['weekly','biweekly','monthly'])
                        ? $rep->recurrence_type : 'weekly';
                @endphp
                <select name="recurrence_type"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    <option value="weekly"   {{ old('recurrence_type', $defaultType) === 'weekly'   ? 'selected' : '' }}>Wöchentlich</option>
                    <option value="biweekly" {{ old('recurrence_type', $defaultType) === 'biweekly' ? 'selected' : '' }}>Zweiwöchentlich</option>
                    <option value="monthly"  {{ old('recurrence_type', $defaultType) === 'monthly'  ? 'selected' : '' }}>Monatlich</option>
                </select>
            </div>

            <p class="text-xs text-gray-400">
                Schulferien werden automatisch ausgespart. Die neuen Einheiten erhalten dasselbe Titel, Typ, Zeit, Ort, Gruppen und Trainer wie die bisherige Serie.
            </p>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                    Einheiten generieren
                </button>
                <a href="{{ route('trainer.sessions.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>

</div>
@endsection

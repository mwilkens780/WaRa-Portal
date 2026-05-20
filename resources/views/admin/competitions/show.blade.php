@extends('layouts.app')
@section('title', $competition->name)
@section('page-title', $competition->name)

@section('content')
<div class="mt-2 space-y-6" x-data="{ showForm: false, showImport: {{ $errors->has('dsv_file') ? 'true' : 'false' }} }">

    {{-- Info-Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-gray-800">{{ $competition->date_range }}</span>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $competition->type_label }}</span>
                </div>
                <p class="text-sm text-gray-500">{{ $competition->location }}</p>
                @if($competition->organizer)
                    <p class="text-xs text-gray-400">Veranstalter: {{ $competition->organizer }}</p>
                @endif
                @if($competition->course)
                    <p class="text-xs text-gray-400">Bahnlänge: {{ $competition->course_label }}</p>
                @endif
                @if($competition->description)
                    <p class="text-sm text-gray-600 mt-2">{{ $competition->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.competitions.edit', $competition) }}"
                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                    Bearbeiten
                </a>
                <button @click="showImport = !showImport; showForm = false"
                        class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    DSV-Import
                </button>
                <button @click="showForm = !showForm; showImport = false"
                        class="px-4 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:bg-accent-dark transition-colors">
                    + Ergebnis eintragen
                </button>
            </div>
        </div>
    </div>

    {{-- DSV-Import --}}
    <div x-show="showImport" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-1">Ergebnisse aus DSV7-Datei importieren</h3>
        <p class="text-sm text-gray-500 mb-4">
            Es werden automatisch Ergebnisse der <strong>SG Wasserratten Norderstedt</strong> aus der Datei gefiltert.
            Enthält die Datei keinen passenden Verein, werden alle Vereine zur manuellen Auswahl angezeigt.
        </p>
        <form method="POST" action="{{ route('admin.competitions.results-import.upload', $competition) }}"
              enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="border-2 border-dashed {{ $errors->has('dsv_file') ? 'border-red-300 bg-red-50' : 'border-gray-300' }} rounded-xl p-6 text-center hover:border-primary transition-colors">
                <p class="text-sm text-gray-400 mb-3">.dsv7, .lef, .xml oder .txt – max. 20 MB</p>
                <input type="file" name="dsv_file" accept=".xml,.lef,.txt,.dsv7"
                       class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
            </div>
            @error('dsv_file')
                <p class="text-red-600 text-sm font-medium">{{ $message }}</p>
            @enderror
            <div class="flex gap-3">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
                    Datei einlesen → Vorschau
                </button>
                <button type="button" @click="showImport = false"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>

    {{-- Ergebnis-Formular --}}
    <div x-show="showForm" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Neues Ergebnis eintragen</h3>
        <form method="POST" action="{{ route('admin.competitions.result.store', $competition) }}" class="space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Schwimmer <span class="text-red-500">*</span></label>
                    <select name="user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">Bitte wählen...</option>
                        @foreach($swimmers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Disziplin <span class="text-red-500">*</span></label>
                    <select name="discipline" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="freistil">Freistil</option>
                        <option value="brust">Brust</option>
                        <option value="ruecken">Rücken</option>
                        <option value="schmetterling">Schmetterling</option>
                        <option value="lagen">Lagen</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Distanz (m) <span class="text-red-500">*</span></label>
                    <select name="distance" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                            <option value="{{ $d }}">{{ $d }} m</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Zeit <span class="text-red-500">*</span></label>
                    <div class="flex gap-1 items-center">
                        <input type="number" name="time_minutes" min="0" placeholder="Min" value="0"
                               class="w-16 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                        <span class="text-gray-400">:</span>
                        <input type="number" name="time_seconds" min="0" max="59" placeholder="Sek" required value="0"
                               class="w-16 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                        <span class="text-gray-400">,</span>
                        <input type="number" name="time_centiseconds" min="0" max="99" placeholder="1/100" required value="0"
                               class="w-16 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Min : Sek , 1/100-Sek</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Platzierung</label>
                    <input type="number" name="placement" min="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Altersklasse</label>
                    <input type="text" name="age_group" placeholder="z.B. AK12, AK14"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Notizen</label>
                <input type="text" name="notes" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-accent hover:bg-accent-dark text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                    Ergebnis speichern
                </button>
                <button type="button" @click="showForm = false" class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>

    {{-- Ergebnistabelle --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Wettkampfergebnisse</h2>
        </div>
        @if($results->isEmpty())
            <p class="text-sm text-gray-400 px-5 py-8 text-center">Noch keine Ergebnisse eingetragen.</p>
        @else
            @foreach($results as $key => $group)
                @php
                    $first = $group->first();
                    $disciplineLabel = $first->discipline_label;
                @endphp
                <div class="border-b border-gray-100 last:border-0">
                    <div class="bg-gray-50 px-5 py-2">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ $first->distance }} m {{ $disciplineLabel }}
                        </p>
                    </div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-50">
                            @foreach($group->sortBy('time_ms') as $idx => $result)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2.5 text-gray-400 w-8">{{ $idx + 1 }}.</td>
                                    <td class="px-5 py-2.5 font-medium text-gray-800">{{ $result->user->name }}</td>
                                    <td class="px-5 py-2.5 font-mono font-semibold text-primary">{{ $result->formatted_time }}</td>
                                    <td class="px-5 py-2.5">
                                        @if($result->placement)
                                            <span class="text-xs {{ $result->placement <= 3 ? 'text-amber-600 font-semibold' : 'text-gray-500' }}">
                                                Platz {{ $result->placement }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5">
                                        @if($result->is_personal_best)
                                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">PB</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-gray-400 text-xs">{{ $result->age_group }}</td>
                                    <td class="px-5 py-2.5 text-right">
                                        <form method="POST" action="{{ route('admin.competitions.result.destroy', $result) }}"
                                              onsubmit="return confirm('Ergebnis löschen?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Startdisziplinen (aus Lenex-Import) --}}
    @if($competition->events->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Startdisziplinen</h2>
                <span class="text-xs text-gray-400">{{ $competition->events->count() }} Disziplinen aus Lenex-Datei</span>
            </div>
            @php $bySession = $competition->events->groupBy('session_number'); @endphp
            @foreach($bySession as $sessionNum => $evts)
                @php $first = $evts->first(); @endphp
                <div class="px-5 py-3 border-b border-gray-50">
                    <p class="text-xs font-semibold text-gray-500 mb-2">
                        {{ $first->session_name ?: ('Abschnitt ' . $sessionNum) }}
                        @if($first->session_date) · {{ $first->session_date->format('d.m.Y') }} @endif
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($evts as $ev)
                            <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full border border-gray-200 text-gray-600 bg-gray-50">
                                <span class="font-semibold text-primary">{{ $ev->event_number }}</span>
                                {{ $ev->distance }} m {{ $ev->discipline_label }}
                                @if($ev->age_group) · {{ $ev->age_group }} @endif
                                @if($ev->gender !== 'X') · {{ $ev->gender === 'M' ? 'M' : 'W' }} @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection

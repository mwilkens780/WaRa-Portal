@extends('layouts.app')
@section('title', $competition->name)
@section('page-title', $competition->name)

@section('content')
<div class="mt-2 space-y-4"
     x-data="{
         activeTab: '{{ $errors->has('dsv_file') ? 'info' : 'ergebnisse' }}',
         showForm: false,
         showImport: {{ $errors->has('dsv_file') ? 'true' : 'false' }},
     }">

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
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.competitions.edit', $competition) }}"
                       class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                        Bearbeiten
                    </a>
                @endif
                <button @click="activeTab = 'info'; showImport = !showImport; showForm = false"
                        class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    DSV-Import
                </button>
                @if(auth()->user()->role === 'admin')
                    <button @click="activeTab = 'ergebnisse'; showForm = !showForm; showImport = false"
                            class="px-4 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:bg-accent-dark transition-colors">
                        + Ergebnis eintragen
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Tab-Leiste + Inhalt --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Tab-Bar --}}
        <div class="flex border-b border-gray-200 overflow-x-auto">
            <button @click="activeTab = 'info'"
                    :class="activeTab === 'info'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                Import
            </button>
            <button @click="activeTab = 'wettkampf'"
                    :class="activeTab === 'wettkampf'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Wettkampffolge
                @if($competition->events->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">
                        {{ $competition->events->unique('event_number')->count() }}
                    </span>
                @endif
            </button>
            @if($hasPflichtzeiten)
            <button @click="activeTab = 'pflichtzeiten'"
                    :class="activeTab === 'pflichtzeiten'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                Pflichtzeiten
            </button>
            @endif
            @if($hasMeldegelder)
            <button @click="activeTab = 'meldegelder'"
                    :class="activeTab === 'meldegelder'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                Meldegelder
            </button>
            @endif
            <button @click="activeTab = 'ergebnisse'"
                    :class="activeTab === 'ergebnisse'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Ergebnisse
                @if($results->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $results->sum(fn($g) => $g->count()) }}</span>
                @endif
            </button>
            <button @click="activeTab = 'auswertung'"
                    :class="activeTab === 'auswertung'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                Auswertung
            </button>
            <button @click="activeTab = 'gruppen'"
                    :class="activeTab === 'gruppen'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Gruppen
                @if($competition->trainingGroups->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $competition->trainingGroups->count() }}</span>
                @endif
            </button>
        </div>

        {{-- Tab: Import --}}
        <div x-show="activeTab === 'info'" x-cloak>
            <div x-show="showImport" class="border-b border-gray-100 p-5 space-y-4">
                {{-- Info-Box: Format --}}
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                    <h3 class="font-semibold text-blue-800 mb-2">Unterstütztes Format: Lenex XML (DSV6/7)</h3>
                    <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside">
                        <li>Dateiendungen: <strong>.dsv7</strong>, <strong>.lef</strong>, <strong>.xml</strong>, <strong>.txt</strong> – max. 20 MB</li>
                        <li>Ergebnisdateien vom DSV, Swimrankings oder WebClub (Lenex 2.0 / 3.0)</li>
                        <li>Vereine aus der Datei werden in einer Vorschau angezeigt – Einträge der SG Wasserratten werden automatisch vorausgewählt</li>
                        <li>DQ, DNS und DNF werden als solche gespeichert</li>
                        <li>Bestzeiten werden automatisch erkannt und aktualisiert</li>
                        <li>Mehrere Wertungsklassen (z.B. AK14 + Offene Wertung) für denselben Start werden zusammengeführt</li>
                    </ul>
                </div>
                <form method="POST" action="{{ route('admin.competitions.results-import.upload', $competition) }}"
                      enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="border-2 border-dashed {{ $errors->has('dsv_file') ? 'border-red-300 bg-red-50' : 'border-gray-300' }} rounded-xl p-6 text-center hover:border-primary transition-colors">
                        <svg class="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="text-sm text-gray-400 mb-3">.dsv7, .lef, .xml oder .txt</p>
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
            <div x-show="!showImport" class="p-5">
                <p class="text-sm text-gray-400 text-center py-4">
                    Klicke auf <strong class="text-gray-600">DSV-Import</strong> oben, um Ergebnisse aus einer DSV7-Datei zu importieren.
                </p>
            </div>
        </div>

        {{-- Tab: Wettkampffolge --}}
        <div x-show="activeTab === 'wettkampf'" x-cloak>
            @if($competition->events->isEmpty())
                <p class="text-sm text-gray-400 text-center px-5 py-8">
                    Noch keine Wettkampffolge hinterlegt. Importiere eine DSV7-Definitionsdatei über die Bearbeiten-Seite.
                </p>
            @else
                @php $bySession = $competition->events->groupBy('session_number'); @endphp
                @foreach($bySession as $sessionNum => $sessionEvts)
                    @php
                        $firstEvt = $sessionEvts->first();
                        $byEvent  = $sessionEvts->groupBy('event_number');
                    @endphp
                    <div class="px-5 py-4 border-b border-gray-100 last:border-0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                            {{ $firstEvt->session_name ?: ('Abschnitt ' . $sessionNum) }}
                            @if($firstEvt->session_date) · {{ $firstEvt->session_date->format('d.m.Y') }} @endif
                        </p>
                        <div class="space-y-3">
                            @foreach($byEvent as $eventNum => $wertungen)
                                @php $baseWk = $wertungen->first(); @endphp
                                <div class="flex flex-wrap items-start gap-2">
                                    {{-- Wettkampf-Label --}}
                                    <div class="flex items-center gap-2 min-w-[220px]">
                                        <span class="text-xs font-bold text-primary bg-blue-50 border border-blue-100 px-2 py-1 rounded w-10 text-center shrink-0">
                                            {{ $eventNum }}
                                        </span>
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ $baseWk->distance }} m {{ $baseWk->discipline_label }}
                                            @if($baseWk->gender !== 'X')
                                                <span class="text-gray-400 font-normal">· {{ $baseWk->gender === 'M' ? 'Männlich' : 'Weiblich' }}</span>
                                            @endif
                                        </span>
                                    </div>
                                    {{-- Wertungen als Chips --}}
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($wertungen as $wertung)
                                            <span class="text-xs px-2.5 py-1 rounded-md border border-gray-200 text-gray-600 bg-gray-50">
                                                {{ $wertung->age_group ?: 'Offene Klasse' }}
                                                @if($wertung->gender !== $baseWk->gender)
                                                    <span class="text-gray-400">· {{ $wertung->gender === 'M' ? 'M' : 'W' }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Tab: Pflichtzeiten --}}
        @if($hasPflichtzeiten)
        <div x-show="activeTab === 'pflichtzeiten'" x-cloak>
            @php
                $pflichtEvts = $competition->events->filter(fn($e) => $e->qualifying_time_ms > 0);
                $pBySession  = $pflichtEvts->groupBy('session_number');
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">WK</th>
                            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Disziplin</th>
                            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Wertung</th>
                            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Pflichtzeit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($pBySession as $sessionNum => $sEvts)
                            @php $firstEvt = $competition->events->where('session_number', $sessionNum)->first(); @endphp
                            <tr class="bg-gray-50/60">
                                <td colspan="4" class="px-5 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    {{ $firstEvt->session_name ?: ('Abschnitt ' . $sessionNum) }}
                                    @if($firstEvt->session_date) · {{ $firstEvt->session_date->format('d.m.Y') }} @endif
                                </td>
                            </tr>
                            @foreach($sEvts->groupBy('event_number') as $eventNum => $wertungen)
                                @foreach($wertungen as $ev)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 font-bold text-primary text-xs w-12">{{ $eventNum }}</td>
                                        <td class="px-5 py-2.5 text-gray-700">
                                            {{ $ev->distance }} m {{ $ev->discipline_label }}
                                            @if($ev->gender !== 'X')
                                                <span class="text-gray-400">· {{ $ev->gender === 'M' ? 'M' : 'W' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2.5 text-gray-600">{{ $ev->age_group ?: 'Offene Klasse' }}</td>
                                        <td class="px-5 py-2.5 font-mono font-semibold text-gray-800">{{ $ev->formatted_qualifying_time }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Tab: Meldegelder --}}
        @if($hasMeldegelder)
        <div x-show="activeTab === 'meldegelder'" x-cloak>
            @php
                $meldegeldEvts = $competition->events->filter(fn($e) => $e->meldegeld > 0);
                $uniqueFees    = $meldegeldEvts->pluck('meldegeld')->unique()->values();
            @endphp
            @if($uniqueFees->count() === 1)
                {{-- Einheitliches Meldegeld --}}
                <div class="px-5 py-6 text-center">
                    <p class="text-sm text-gray-500 mb-1">Einheitliches Meldegeld je Start</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($uniqueFees->first(), 2, ',', '.') }} €</p>
                    <p class="text-xs text-gray-400 mt-2">Gilt für alle {{ $meldegeldEvts->unique('event_number')->count() }} Wettkämpfe</p>
                </div>
            @else
                {{-- Unterschiedliche Meldegelder je Wertung --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">WK</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Disziplin</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Wertung</th>
                                <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Meldegeld</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($meldegeldEvts->groupBy('event_number') as $eventNum => $wertungen)
                                @foreach($wertungen as $ev)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 font-bold text-primary text-xs w-12">{{ $eventNum }}</td>
                                        <td class="px-5 py-2.5 text-gray-700">
                                            {{ $ev->distance }} m {{ $ev->discipline_label }}
                                            @if($ev->gender !== 'X')
                                                <span class="text-gray-400">· {{ $ev->gender === 'M' ? 'M' : 'W' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2.5 text-gray-600">{{ $ev->age_group ?: 'Offene Klasse' }}</td>
                                        <td class="px-5 py-2.5 text-right font-semibold text-gray-800">{{ number_format($ev->meldegeld, 2, ',', '.') }} €</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        {{-- Tab: Ergebnisse --}}
        <div x-show="activeTab === 'ergebnisse'">

            {{-- Ergebnis-Formular (nur Admin) --}}
            @if(auth()->user()->role === 'admin')
            <div x-show="showForm" x-cloak class="border-b border-gray-100 p-5">
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
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Geschlecht</label>
                            <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">– unbekannt –</option>
                                <option value="M">Männlich</option>
                                <option value="F">Weiblich</option>
                            </select>
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
            @endif

            {{-- Ergebnistabelle --}}
            @if($results->isEmpty())
                <p class="text-sm text-gray-400 px-5 py-8 text-center">Noch keine Ergebnisse eingetragen.</p>
            @else
                @foreach($results as $key => $group)
                    @php $first = $group->first(); $rank = 0; @endphp
                    <div class="border-b border-gray-100 last:border-0">
                        <div class="bg-gray-50 px-5 py-2">
                            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                {{ $first->distance }} m {{ $first->discipline_label }}
                            </p>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-50">
                                @foreach($group as $swim)
                                    @php if (!$swim->is_dns) $rank++; @endphp
                                    <tr class="hover:bg-gray-50 {{ $swim->is_dns ? 'opacity-60' : '' }}">
                                        <td class="px-5 py-2.5 text-gray-400 w-8 text-xs">
                                            {{ !$swim->is_dns ? $rank . '.' : '–' }}
                                        </td>
                                        <td class="px-5 py-2.5 font-medium text-gray-800">{{ $swim->user?->name }}</td>
                                        <td class="px-5 py-2.5">
                                            @if(!$swim->is_dns)
                                                <span class="font-mono font-semibold text-primary">{{ $swim->formatted_time }}</span>
                                            @elseif($swim->notes)
                                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-semibold tracking-wide">{{ $swim->notes }}</span>
                                            @else
                                                <span class="text-gray-400 text-xs">NT</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2.5">
                                            @if(!empty($swim->placements))
                                                <div class="flex flex-col gap-0.5">
                                                    @foreach($swim->placements as $p)
                                                        <span class="text-xs {{ $p->placement <= 3 ? 'text-amber-600 font-semibold' : 'text-gray-500' }}">
                                                            @if($p->age_group)<span class="text-gray-400">{{ $p->age_group }}:</span> @endif
                                                            Platz {{ $p->placement }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2.5">
                                            <div class="flex gap-1 flex-wrap">
                                                @if($swim->is_final)
                                                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Finale</span>
                                                @endif
                                                @if($swim->is_personal_best && !$swim->is_dns)
                                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">PB</span>
                                                @endif
                                                @if($swim->breaks_vereinsrekord)
                                                    <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full font-bold">VR</span>
                                                @endif
                                                @if($swim->breaks_landesrekord)
                                                    <span class="text-xs bg-amber-500 text-white px-2 py-0.5 rounded-full font-bold">LR</span>
                                                @endif
                                            </div>
                                        </td>
                                        @if(auth()->user()->role === 'admin')
                                        <td class="px-5 py-2.5 text-right">
                                            <form method="POST" action="{{ route('admin.competitions.result.destroy', $swim->id) }}"
                                                  onsubmit="return confirm('Ergebnis löschen? Bei mehreren Wertungsklassen werden alle zugehörigen Einträge entfernt.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                            </form>
                                        </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Tab: Auswertung --}}
        <div x-show="activeTab === 'auswertung'" x-cloak>
            @if($results->isEmpty())
                <p class="text-sm text-gray-400 px-5 py-8 text-center">Noch keine Ergebnisse vorhanden.</p>
            @else
                {{-- Ergebnistabelle Auswertung --}}
                <div class="overflow-x-auto border-b border-gray-100">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Schwimmer</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Disziplin</th>
                                <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Zeit</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Platzierung(en)</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Auszeichnungen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($results->flatten(1)->sortBy(fn($s) => $s->is_dns ? PHP_INT_MAX : $s->time_ms) as $swim)
                                <tr class="hover:bg-gray-50 {{ $swim->is_dns ? 'opacity-50' : '' }}">
                                    <td class="px-5 py-2.5 font-medium text-gray-800">{{ $swim->user?->name }}</td>
                                    <td class="px-5 py-2.5 text-gray-700">{{ $swim->distance }}m {{ $swim->discipline_label }}</td>
                                    <td class="px-5 py-2.5 text-right">
                                        @if(!$swim->is_dns)
                                            <span class="font-mono font-semibold text-primary">{{ $swim->formatted_time }}</span>
                                        @elseif($swim->notes)
                                            <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-semibold">{{ $swim->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5">
                                        @if(!empty($swim->placements))
                                            <div class="flex flex-col gap-0.5">
                                                @foreach($swim->placements as $p)
                                                    <span class="text-xs {{ $p->placement <= 3 ? 'text-amber-600 font-semibold' : 'text-gray-600' }}">
                                                        @if($p->age_group)<span class="text-gray-400 font-normal">{{ $p->age_group }}:</span> @endif
                                                        Platz {{ $p->placement }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-400">–</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <div class="flex gap-1 flex-wrap">
                                            @if($swim->is_final)
                                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Finale</span>
                                            @endif
                                            @if($swim->is_personal_best && !$swim->is_dns)
                                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">PB</span>
                                            @endif
                                            @if($swim->breaks_vereinsrekord)
                                                <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full font-bold">VR</span>
                                            @endif
                                            @if($swim->breaks_landesrekord)
                                                <span class="text-xs bg-amber-500 text-white px-2 py-0.5 rounded-full font-bold">LR</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- KI-Auswertungstext --}}
                <div class="p-5"
                     x-data="{ loading: false, text: '', error: '' }">
                    <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
                        <div>
                            <h3 class="font-semibold text-gray-800">KI-Auswertungstext</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Generiert einen motivierenden Auswertungstext für den Trainer-Newsletter.</p>
                        </div>
                        <button
                            @click="
                                loading = true; error = '';
                                fetch('{{ route('admin.competitions.analysis', $competition) }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                                })
                                .then(r => r.json())
                                .then(d => { loading = false; if (d.error) { error = d.error } else { text = d.text } })
                                .catch(e => { loading = false; error = e.message })
                            "
                            :disabled="loading"
                            class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors disabled:opacity-50 flex items-center gap-2 shrink-0">
                            <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="loading ? 'Generiere...' : 'Text generieren'"></span>
                        </button>
                    </div>
                    <p x-show="error" class="text-red-600 text-sm mb-3" x-text="error"></p>
                    <textarea x-show="text" x-model="text"
                              class="w-full border border-gray-300 rounded-lg p-3 text-sm text-gray-800 min-h-[200px] font-sans leading-relaxed focus:ring-2 focus:ring-blue-500 outline-none resize-y"></textarea>
                    <p x-show="!text && !loading && !error" class="text-sm text-gray-400 text-center py-4">
                        Klicke auf "Text generieren" um einen KI-generierten Auswertungstext zu erstellen.
                    </p>
                </div>
            @endif
        </div>

        {{-- Tab: Gruppen --}}
        <div x-show="activeTab === 'gruppen'" x-cloak class="p-5">
            <p class="text-sm text-gray-500 mb-4">
                Startberechtigte Trainingsgruppen für diesen Wettkampf. Wenn keine Gruppe ausgewählt ist, ist der Wettkampf für alle Sportler sichtbar.
            </p>
            <form method="POST" action="{{ route('admin.competitions.sync-groups', $competition) }}">
                @csrf
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-5">
                    @foreach($allGroups as $group)
                        @php $dots = $group->color_dots; @endphp
                        <label class="flex items-center gap-2.5 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors
                            {{ $competition->trainingGroups->contains($group->id) ? 'border-primary bg-blue-50/40' : 'border-gray-200' }}">
                            <input type="checkbox" name="groups[]" value="{{ $group->id }}"
                                   {{ $competition->trainingGroups->contains($group->id) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-accent focus:ring-accent">
                            <span class="w-2.5 h-2.5 rounded-full {{ $dots['dot'] }} flex-shrink-0"></span>
                            <span class="text-sm font-medium text-gray-700">{{ $group->name }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit"
                        class="bg-primary text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                    Speichern
                </button>
            </form>
        </div>

    </div>{{-- end tab container --}}

</div>
@endsection

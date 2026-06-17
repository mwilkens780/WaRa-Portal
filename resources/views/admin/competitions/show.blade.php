@extends('layouts.app')
@section('title', $competition->name)
@section('page-title', $competition->name)

@section('content')
<div class="mt-2 space-y-4"
     x-data="{
         activeTab: '{{ $errors->has('dsv_file') || $errors->has('def_file') ? 'import' : 'ergebnisse' }}',
         showForm: false,
         resultsView: 'strecke',
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
                <button @click="activeTab = 'import'; showForm = false"
                        class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import
                </button>
                @if(auth()->user()->role === 'admin')
                    <button @click="activeTab = 'ergebnisse'; showForm = !showForm"
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
            <button @click="activeTab = 'import'"
                    :class="activeTab === 'import'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                Import
            </button>
            <button @click="activeTab = 'dokumente'"
                    :class="activeTab === 'dokumente'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Dokumente
                @php $docCount = $documents->flatten()->count(); @endphp
                @if($docCount > 0)
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $docCount }}</span>
                @endif
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
            <button @click="activeTab = 'anmeldungen'"
                    :class="activeTab === 'anmeldungen'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Anmeldungen
                @if($signupRequest?->isActive())
                    @php $pending = $signupRequest->responses->where('status','pending')->count(); @endphp
                    @if($pending > 0)
                        <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-normal">{{ $pending }} offen</span>
                    @endif
                @endif
            </button>
            <button @click="activeTab = 'organisation'"
                    :class="activeTab === 'organisation'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                Organisation
            </button>
            <button @click="activeTab = 'ausschreibung'"
                    :class="activeTab === 'ausschreibung'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Ausschreibung
                @if($competition->announcement_data)
                    <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-normal">PDF</span>
                @endif
            </button>
            @if($hasQualifikation)
            <button @click="activeTab = 'qualifikation'"
                    :class="activeTab === 'qualifikation'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Qualifikation
                @php $qualCount = $qualificationSwimmers->filter(function($s) use ($qualifyingEvents, $qualResultsByUserEvent) {
                    foreach ($qualifyingEvents as $e) {
                        $k = "{$s->id}_{$e->discipline}_{$e->distance}";
                        if (isset($qualResultsByUserEvent[$k]) && $qualResultsByUserEvent[$k] <= $e->qualifying_time_ms) return true;
                    }
                    return false;
                })->count(); @endphp
                @if($qualCount > 0)
                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-normal">{{ $qualCount }} ✓</span>
                @endif
            </button>
            @endif
            <button @click="activeTab = 'meldungen'"
                    :class="activeTab === 'meldungen'
                        ? 'border-primary text-primary bg-blue-50/40'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Meldungen
                @if($signupRequest?->isClosed())
                    @php $attending = $signupRequest->responses->where('status','attending')->count(); @endphp
                    @if($attending > 0)
                        <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-normal">{{ $attending }}</span>
                    @endif
                @endif
            </button>
        </div>

        {{-- Tab: Import --}}
        <div x-show="activeTab === 'import'" x-cloak>
            <div class="p-5 space-y-6">

                {{-- 1. Wettkampfdefinitionsdatei --}}
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-700">Wettkampfdefinitionsdatei importieren (*-Wk.DSV7)</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Ersetzt die aktuelle Wettkampffolge durch die Events aus der Definitionsdatei. Pflichtzeiten und Meldegelder werden automatisch übernommen.</p>
                    </div>
                    <div class="p-4">
                        <form method="POST" action="{{ route('admin.competitions.definition-import', $competition) }}"
                              enctype="multipart/form-data" class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <input type="file" name="def_file" accept=".dsv7,.txt"
                                       class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                            </div>
                            <button type="submit"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors whitespace-nowrap">
                                Wettkampffolge importieren
                            </button>
                        </form>
                        @error('def_file')
                            <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- 2. DSV7 Ergebnisse (Vereinsergebnisliste / Wettkampfergebnisliste) --}}
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-700">Ergebnisse importieren – DSV7 / Lenex XML</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Dateiendungen: .dsv7, .lef, .xml, .txt – max. 20 MB. Nur Ergebnisse der eigenen Schwimmer werden zugeordnet; Vorschau vor dem Speichern.</p>
                    </div>
                    <div class="p-4">
                        <form method="POST" action="{{ route('admin.competitions.results-import.upload', $competition) }}"
                              enctype="multipart/form-data" class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <input type="file" name="dsv_file" accept=".xml,.lef,.txt,.dsv7"
                                       class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
                            </div>
                            <button type="submit"
                                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors whitespace-nowrap">
                                Datei einlesen → Vorschau
                            </button>
                        </form>
                        @error('dsv_file')
                            <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- 3. WebClub CSV --}}
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-700">Ergebnisse importieren – WebClub CSV</h3>
                        <p class="text-xs text-gray-500 mt-0.5">WebClub-Ergebnisexport (.csv) – enthält PBZ/SBZ/SR-Kennzeichnung. Alle Athleten aus der Datei werden gegen Portal-Schwimmer abgeglichen.</p>
                    </div>
                    <div class="p-4">
                        <form method="POST" action="{{ route('admin.competitions.wc-import.upload', $competition) }}"
                              enctype="multipart/form-data" class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <input type="file" name="csv_file" accept=".csv,.txt"
                                       class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-600 file:text-white hover:file:bg-gray-700 cursor-pointer">
                            </div>
                            <button type="submit"
                                    class="bg-gray-600 hover:bg-gray-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors whitespace-nowrap">
                                CSV einlesen → Vorschau
                            </button>
                        </form>
                        @error('csv_file')
                            <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

            </div>
        </div>

        {{-- Tab: Wettkampffolge --}}
        <div x-show="activeTab === 'wettkampf'" x-cloak>
            @if($competition->events->isEmpty())
                <p class="text-sm text-gray-400 text-center px-5 py-8">
                    Noch keine Wettkampffolge hinterlegt. Lade eine DSV7-Definitionsdatei (*-Wk.DSV7) im Import-Tab hoch.
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
                                            {{ $baseWk->distance_label }} m {{ $baseWk->discipline_label }}
                                            @if($baseWk->relay_legs) <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium ml-1">Staffel</span> @endif
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
                                            {{ $ev->distance_label }} m {{ $ev->discipline_label }}
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
                                            {{ $ev->distance_label }} m {{ $ev->discipline_label }}
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
                                <option value="F">Freistil</option>
                                <option value="B">Brust</option>
                                <option value="R">Rücken</option>
                                <option value="S">Schmetterling</option>
                                <option value="L">Lagen</option>
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
                {{-- Toggle: Strecke / Sportler --}}
                <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100">
                    <span class="text-xs font-medium text-gray-500 mr-1">Gruppierung:</span>
                    <button @click="resultsView = 'strecke'"
                            :class="resultsView === 'strecke'
                                ? 'bg-primary text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                        Nach Strecke
                    </button>
                    <button @click="resultsView = 'sportler'"
                            :class="resultsView === 'sportler'
                                ? 'bg-primary text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                        Nach Sportler
                    </button>
                </div>

                {{-- Ansicht: Nach Strecke --}}
                <div x-show="resultsView === 'strecke'">
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
                                                      onsubmit="return confirm('Ergebnis löschen?')">
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
                </div>

                {{-- Ansicht: Nach Sportler --}}
                <div x-show="resultsView === 'sportler'" x-cloak>
                    @php
                        $bySwimmer = $results->flatten(1)
                            ->groupBy('user_id')
                            ->sortBy(fn($g) => ($g->first()->user?->lastname ?? '') . ($g->first()->user?->firstname ?? ''));
                    @endphp
                    @foreach($bySwimmer as $uid => $swims)
                        @php
                            $user       = $swims->first()->user;
                            $swimsSorted = $swims->sortBy(fn($s) => [$s->discipline, $s->distance, $s->time_ms]);
                            $pbCount    = $swims->where('is_personal_best', true)->where('is_dns', false)->count();
                            $vrCount    = $swims->where('breaks_vereinsrekord', true)->count();
                        @endphp
                        <div class="border-b border-gray-100 last:border-0">
                            {{-- Sportler-Header --}}
                            <div class="bg-gray-50 px-5 py-2.5 flex items-center justify-between">
                                <p class="text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    {{ $user?->name ?? '–' }}
                                    @if($user?->birth_year ?? $user?->birth_date?->year)
                                        <span class="font-normal text-gray-400 normal-case">· Jg.&nbsp;{{ $user->birth_year ?? $user->birth_date?->year }}</span>
                                    @endif
                                </p>
                                <div class="flex gap-1">
                                    @if($pbCount > 0)
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">{{ $pbCount }} PB</span>
                                    @endif
                                    @if($vrCount > 0)
                                        <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full font-bold">{{ $vrCount }} VR</span>
                                    @endif
                                </div>
                            </div>
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($swimsSorted as $swim)
                                        <tr class="hover:bg-gray-50 {{ $swim->is_dns ? 'opacity-60' : '' }}">
                                            <td class="px-5 py-2.5 text-gray-700 font-medium w-40">
                                                {{ $swim->distance }} m {{ $swim->discipline_label }}
                                            </td>
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
                                            @if(!empty($swim->wertungen))
                                                <td class="px-5 py-2.5">
                                                    <div class="flex flex-wrap gap-0.5">
                                                        @foreach($swim->wertungen as $w)
                                                            <span class="px-1 py-0.5 bg-indigo-50 text-indigo-600 rounded text-xs">{{ $w }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                            @else
                                                <td></td>
                                            @endif
                                            @if(auth()->user()->role === 'admin')
                                            <td class="px-5 py-2.5 text-right">
                                                <form method="POST" action="{{ route('admin.competitions.result.destroy', $swim->id) }}"
                                                      onsubmit="return confirm('Ergebnis löschen?')">
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
                </div>
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

                {{-- KI-Auswertungstext mit WYSIWYG-Editor --}}
                <div class="p-5" x-data="auswertungEditor()">

                    {{-- Toolbar: Generieren + Speichern + PDF --}}
                    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
                        <div>
                            <h3 class="font-semibold text-gray-800">Auswertungstext</h3>
                            <p class="text-xs text-gray-500 mt-0.5">KI generiert · Trainer bearbeitet · als PDF exportieren</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            {{-- KI generieren --}}
                            <button @click="generate()" :disabled="loading"
                                    class="flex items-center gap-1.5 px-3 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors disabled:opacity-50">
                                <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <span x-text="loading ? 'Generiere…' : 'KI-Text'"></span>
                            </button>

                            {{-- Speichern --}}
                            <button @click="save()" :disabled="saving"
                                    class="flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors disabled:opacity-50">
                                <svg x-show="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <svg x-show="!saving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                <span x-text="saving ? 'Speichern…' : 'Speichern'"></span>
                            </button>

                            {{-- PDF Export --}}
                            <a href="{{ route('admin.competitions.analysis.pdf', $competition) }}"
                               target="_blank"
                               class="flex items-center gap-1.5 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                                PDF
                            </a>
                        </div>
                    </div>

                    {{-- Status-Nachrichten --}}
                    <p x-show="error" x-cloak class="text-red-600 text-sm mb-3 bg-red-50 border border-red-200 rounded-lg px-3 py-2" x-text="error"></p>
                    <p x-show="saveMsg" x-cloak class="text-green-700 text-sm mb-3 bg-green-50 border border-green-200 rounded-lg px-3 py-2" x-text="saveMsg"></p>

                    {{-- Quill WYSIWYG Editor --}}
                    <div x-ref="editorContainer"
                         style="min-height: 280px; font-size: 14px; line-height: 1.6;"
                         class="rounded-b-lg"></div>
                </div>
            @endif
        </div>

        {{-- Tab: Anmeldungen --}}
        <div x-show="activeTab === 'anmeldungen'" x-cloak class="p-5">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
            @endif

            @if(!$signupRequest)
                {{-- Noch keine Abfrage --}}
                <div class="max-w-2xl">
                    <p class="text-sm text-gray-500 mb-5">Noch keine Anmeldeabfrage erstellt. Definiere Gruppen oder Schwimmer, schreibe eine Nachricht und starte dann die Abfrage.</p>
                    <form method="POST" action="{{ route('admin.competitions.signup.store', $competition) }}" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        @include('admin.competitions._signup_form', ['signupRequest' => null, 'allGroups' => $allGroups, 'swimmers' => $swimmers])
                        <div class="flex gap-3">
                            <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
                                Als Entwurf speichern
                            </button>
                        </div>
                    </form>
                </div>

            @elseif($signupRequest->isDraft())
                {{-- Draft-Phase --}}
                <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                    <div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 text-gray-600 px-3 py-1 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span> Entwurf
                        </span>
                        <p class="text-sm text-gray-500 mt-1">Entwurf gespeichert. Bearbeite die Abfrage und starte sie, wenn du bereit bist.</p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.competitions.signup.activate', [$competition, $signupRequest]) }}">
                            @csrf
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors">
                                Abfrage starten
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.competitions.signup.destroy', [$competition, $signupRequest]) }}"
                              onsubmit="return confirm('Entwurf wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-4 py-2 border border-red-200 text-red-600 hover:bg-red-50 rounded-lg text-sm transition-colors">
                                Löschen
                            </button>
                        </form>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.competitions.signup.update', [$competition, $signupRequest]) }}" enctype="multipart/form-data" class="space-y-5 max-w-2xl">
                    @csrf @method('PUT')
                    @include('admin.competitions._signup_form', ['signupRequest' => $signupRequest, 'allGroups' => $allGroups, 'swimmers' => $swimmers])
                    <div>
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
                            Entwurf aktualisieren
                        </button>
                    </div>
                </form>

            @elseif($signupRequest->isActive())
                {{-- Aktive Phase --}}
                <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                    <div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-green-100 text-green-700 px-3 py-1 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> Aktiv
                        </span>
                        <p class="text-sm text-gray-500 mt-1">
                            Gestartet {{ $signupRequest->activated_at->deBerlin('d.m.Y H:i') }} Uhr
                            @if($signupRequest->deadline) · Deadline: {{ $signupRequest->deadline->format('d.m.Y') }}@endif
                        </p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        @php $pendingCount = $signupRequest->responses->where('status','pending')->count(); @endphp
                        @if($pendingCount > 0)
                            <form method="POST" action="{{ route('admin.competitions.signup.remind', [$competition, $signupRequest]) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 border border-amber-300 text-amber-700 hover:bg-amber-50 rounded-lg text-sm transition-colors">
                                    Erinnerung ({{ $pendingCount }})
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.competitions.signup.close', [$competition, $signupRequest]) }}"
                              onsubmit="return confirm('Anmeldeabfrage schließen? Danach können Schwimmer nicht mehr antworten.')">
                            @csrf
                            <button type="submit" class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg text-sm transition-colors">
                                Abfrage schließen
                            </button>
                        </form>
                    </div>
                </div>

                @if($signupRequest->meeting_point || $signupRequest->meeting_time)
                    <div class="bg-sky-50 border border-sky-100 rounded-lg px-4 py-3 mb-4 text-sm text-gray-700 flex items-center gap-3">
                        <svg class="w-4 h-4 text-sky-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>
                            <strong>Treffpunkt:</strong>
                            @if($signupRequest->meeting_time){{ \Illuminate\Support\Str::substr($signupRequest->meeting_time, 0, 5) }} Uhr @endif
                            @if($signupRequest->meeting_point) – {{ $signupRequest->meeting_point }} @endif
                        </span>
                    </div>
                @endif

                @if($signupRequest->message)
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mb-5 text-sm text-gray-700 whitespace-pre-line">
                        {{ $signupRequest->message }}
                    </div>
                @endif

                @if($signupRequest->attachment_path)
                    <div class="mb-5">
                        <a href="{{ route('admin.competitions.signup.attachment', [$competition, $signupRequest]) }}"
                           class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            Anhang herunterladen
                        </a>
                    </div>
                @endif

                {{-- Antwortstatus-Tabelle --}}
                @php
                    $responses = $signupRequest->responses->sortBy(fn($r) => $r->user?->lastname . $r->user?->firstname);
                    $countAttending    = $responses->where('status', 'attending')->count();
                    $countNotAttending = $responses->where('status', 'not_attending')->count();
                    $countPending      = $responses->where('status', 'pending')->count();
                    $busBooked         = $signupRequest->bus_available ? $signupRequest->busBookedCount() : null;
                    $busRemaining      = $signupRequest->bus_available ? $signupRequest->busSeatsRemaining() : null;
                @endphp
                <div class="flex gap-6 mb-4 flex-wrap items-end">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">{{ $countAttending }}</p>
                        <p class="text-xs text-gray-500">Zusagen</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-red-500">{{ $countNotAttending }}</p>
                        <p class="text-xs text-gray-500">Absagen</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-amber-500">{{ $countPending }}</p>
                        <p class="text-xs text-gray-500">Ausstehend</p>
                    </div>
                    @if($signupRequest->bus_available)
                        <div class="text-center border-l border-gray-200 pl-6">
                            <p class="text-2xl font-bold text-blue-600">{{ $busBooked }} / {{ $signupRequest->bus_seats }}</p>
                            <p class="text-xs text-gray-500">Bus gebucht</p>
                        </div>
                        @if($busRemaining === 0)
                            <span class="text-xs font-medium text-red-600 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full">Ausgebucht</span>
                        @else
                            <span class="text-xs font-medium text-blue-600 bg-blue-50 border border-blue-100 px-2.5 py-1 rounded-full">{{ $busRemaining }} Plätze frei</span>
                        @endif
                    @endif
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Schwimmer</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                @if($signupRequest->bus_available)
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Bus</th>
                                @endif
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Antwort am</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Notiz</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Erinnert</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($responses as $response)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-800">{{ $response->user?->name }}</td>
                                    <td class="px-4 py-2.5">
                                        @if($response->isAttending())
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-full">Zusage</span>
                                        @elseif($response->isNotAttending())
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold bg-red-100 text-red-600 px-2.5 py-1 rounded-full">Absage</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">Ausstehend</span>
                                        @endif
                                    </td>
                                    @if($signupRequest->bus_available)
                                        <td class="px-4 py-2.5">
                                            @if($response->bus_booked)
                                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Gebucht</span>
                                            @elseif($response->isAttending())
                                                <span class="text-xs text-gray-400">–</span>
                                            @else
                                                <span class="text-xs text-gray-300">–</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $response->responded_at?->deBerlin('d.m.Y H:i') ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $response->note ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $response->reminder_sent_at?->deBerlin('d.m. H:i') ?? '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @else
                {{-- Geschlossen --}}
                <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                    <div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-200 text-gray-600 px-3 py-1 rounded-full">
                            Geschlossen
                        </span>
                        <p class="text-sm text-gray-500 mt-1">
                            Abfrage geschlossen am {{ $signupRequest->closed_at->deBerlin('d.m.Y H:i') }} Uhr.
                        </p>
                    </div>
                </div>
                @php
                    $responses      = $signupRequest->responses->sortBy(fn($r) => $r->user?->lastname . $r->user?->firstname);
                    $busBookedCount = $signupRequest->bus_available ? $signupRequest->busBookedCount() : null;
                @endphp

                @if($signupRequest->meeting_point || $signupRequest->meeting_time)
                    <div class="bg-sky-50 border border-sky-100 rounded-lg px-4 py-3 mb-4 text-sm text-gray-700 flex items-center gap-3">
                        <svg class="w-4 h-4 text-sky-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>
                            <strong>Treffpunkt:</strong>
                            @if($signupRequest->meeting_time){{ \Illuminate\Support\Str::substr($signupRequest->meeting_time, 0, 5) }} Uhr @endif
                            @if($signupRequest->meeting_point) – {{ $signupRequest->meeting_point }} @endif
                        </span>
                    </div>
                @endif

                @if($signupRequest->bus_available)
                    <div class="mb-4 flex items-center gap-3">
                        <span class="text-sm text-gray-700">
                            <strong>Bus:</strong> {{ $busBookedCount }} von {{ $signupRequest->bus_seats }} Plätzen gebucht
                        </span>
                    </div>
                @endif

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Schwimmer</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                @if($signupRequest->bus_available)
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Bus</th>
                                @endif
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Notiz</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($responses as $response)
                                <tr class="hover:bg-gray-50 {{ $response->isNotAttending() ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-2.5 font-medium text-gray-800">{{ $response->user?->name }}</td>
                                    <td class="px-4 py-2.5">
                                        @if($response->isAttending())
                                            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-full">Zusage</span>
                                        @elseif($response->isNotAttending())
                                            <span class="text-xs font-semibold bg-red-100 text-red-600 px-2.5 py-1 rounded-full">Absage</span>
                                        @else
                                            <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-2.5 py-1 rounded-full">Keine Antwort</span>
                                        @endif
                                    </td>
                                    @if($signupRequest->bus_available)
                                        <td class="px-4 py-2.5">
                                            @if($response->bus_booked)
                                                <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Gebucht</span>
                                            @else
                                                <span class="text-xs text-gray-300">–</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $response->note ?? '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Tab: Organisation --}}
        <div x-show="activeTab === 'organisation'" x-cloak class="p-5">
            <p class="text-sm text-gray-500 mb-4">Interne Notizen zur Organisation: Anreise, Unterkunft, Zeitplan, Kontakte etc.</p>
            <form method="POST" action="{{ route('admin.competitions.organisation.save', $competition) }}" class="space-y-4">
                @csrf
                <textarea name="notes" rows="10"
                          placeholder="z.B. Anreise: Abfahrt 7:00 Uhr ab Vereinsheim&#10;Unterkunft: Hotel Musterstadt, Zimmer für 8 Personen&#10;Kontakt Ausrichter: Max Mustermann, 0123-456789"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm text-gray-800 focus:ring-2 focus:ring-blue-500 outline-none resize-y font-sans leading-relaxed">{{ $competition->organisation_notes['text'] ?? '' }}</textarea>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
                    Speichern
                </button>
            </form>
        </div>

        {{-- Tab: Qualifikation --}}
        @if($hasQualifikation)
        <div x-show="activeTab === 'qualifikation'" x-cloak class="p-5 space-y-6">

            {{-- Qualifikationszeitraum (immer editierbar) --}}
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <form method="POST" action="{{ route('admin.competitions.signup.qualification-period', [$competition, $signupRequest]) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <div>
                        <p class="text-sm font-semibold text-blue-800">Qualifikationszeitraum</p>
                        <p class="text-xs text-blue-600 mt-0.5">Nur Wettkampf-Ergebnisse aus diesem Zeitraum werden für den Pflichtzeiten-Nachweis herangezogen. Leer = keine Datumsbeschränkung.</p>
                    </div>
                    <div class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Von</label>
                            <input type="date" name="qualifying_period_start"
                                   value="{{ $signupRequest->qualifying_period_start?->format('Y-m-d') }}"
                                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Bis</label>
                            <input type="date" name="qualifying_period_end"
                                   value="{{ $signupRequest->qualifying_period_end?->format('Y-m-d') }}"
                                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>

            {{-- Pflichtzeiten-Übersicht --}}
            @if($qualifyingEvents->isNotEmpty())
            <div>
                <h3 class="font-semibold text-gray-800 mb-3 text-sm">Pflichtzeiten dieser Veranstaltung</h3>
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2.5 text-left">WK</th>
                                <th class="px-4 py-2.5 text-left">Strecke</th>
                                <th class="px-4 py-2.5 text-left">Geschlecht</th>
                                <th class="px-4 py-2.5 text-left">Wertung</th>
                                <th class="px-4 py-2.5 text-left">Pflichtzeit</th>
                                <th class="px-4 py-2.5 text-left">Meldeschluss PZ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($qualifyingEvents as $qev)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $qev->event_number }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $qev->distance_label }} m {{ $qev->discipline_label }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $qev->gender_label }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $qev->age_group ?: '–' }}</td>
                                <td class="px-4 py-2 font-mono font-semibold text-blue-700">{{ $qev->formatted_qualifying_time }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $qev->qualifying_deadline?->format('d.m.Y') ?? '–' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Schwimmer-Qualifikationsmatrix --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800 text-sm">Qualifikationsstand der Schwimmer</h3>
                    @if(!$signupRequest->qualifying_period_start && !$signupRequest->qualifying_period_end)
                        <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-1 rounded-lg">
                            Kein Zeitraum gesetzt – alle Ergebnisse werden berücksichtigt
                        </span>
                    @else
                        <span class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded-lg">
                            Zeitraum:
                            {{ $signupRequest->qualifying_period_start?->format('d.m.Y') ?? '...' }}
                            –
                            {{ $signupRequest->qualifying_period_end?->format('d.m.Y') ?? '...' }}
                        </span>
                    @endif
                </div>

                @if($qualificationSwimmers->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">Keine Schwimmer aus zugeordneten Trainingsgruppen oder Einzelzuweisungen gefunden.</p>
                @else
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="text-sm w-full">
                        <thead class="bg-gray-50 text-xs text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold sticky left-0 bg-gray-50">Schwimmer</th>
                                <th class="px-3 py-2.5 text-center font-semibold">Jg.</th>
                                @foreach($qualifyingEvents->unique(fn($e) => $e->discipline . '_' . $e->distance) as $qev)
                                <th class="px-3 py-2.5 text-center font-semibold min-w-[100px]">
                                    {{ $qev->distance_label }}m {{ $qev->discipline_label }}<br>
                                    <span class="text-gray-400 font-normal">PZ {{ $qev->formatted_qualifying_time }}</span>
                                </th>
                                @endforeach
                                <th class="px-3 py-2.5 text-left font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($qualificationSwimmers as $qs)
                            @php
                                $isAssigned   = $signupRequest->responses->contains('user_id', $qs->id);
                                $qualifiesAny = false;
                                $swimmerAge   = $competition->date && $qs->birth_date
                                    ? ($competition->date->year - $qs->birth_date->year)
                                    : null;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $isAssigned ? 'bg-green-50/30' : '' }}">
                                <td class="px-4 py-2.5 sticky left-0 bg-inherit">
                                    <span class="font-medium text-gray-800">{{ $qs->lastname }}, {{ $qs->firstname }}</span>
                                    @if($qs->gender)
                                        <span class="text-xs text-gray-400 ml-1">{{ $qs->gender === 'M' ? '♂' : '♀' }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs text-gray-500">
                                    {{ $qs->birth_date?->year ?? '–' }}
                                </td>
                                @foreach($qualifyingEvents->unique(fn($e) => $e->discipline . '_' . $e->distance) as $qev)
                                @php
                                    $rKey    = "{$qs->id}_{$qev->discipline}_{$qev->distance}";
                                    $bestMs  = $qualResultsByUserEvent[$rKey] ?? null;
                                    $qualOk  = $bestMs !== null && $bestMs <= $qev->qualifying_time_ms;
                                    // Gender check
                                    $genderOk = ($qev->gender === 'X') ||
                                                ($qs->gender && strtoupper($qs->gender) === $qev->gender);
                                    // Age check
                                    $ageOk = $swimmerAge === null ||
                                             (($qev->age_min === null || $swimmerAge >= $qev->age_min) &&
                                              ($qev->age_max === null || $swimmerAge <= $qev->age_max));
                                    if ($qualOk && $genderOk && $ageOk) $qualifiesAny = true;
                                @endphp
                                <td class="px-3 py-2.5 text-center text-xs">
                                    @if(!$genderOk || !$ageOk)
                                        <span class="text-gray-300">–</span>
                                    @elseif($bestMs !== null)
                                        <span class="font-mono {{ $qualOk ? 'text-green-700 font-semibold' : 'text-red-500' }}">
                                            {{ \App\Models\SwimmingTime::formatMs($bestMs) }}
                                        </span>
                                        <span class="{{ $qualOk ? 'text-green-600' : 'text-red-400' }} ml-0.5">{{ $qualOk ? '✓' : '✗' }}</span>
                                    @else
                                        <span class="text-gray-300">keine</span>
                                    @endif
                                </td>
                                @endforeach
                                <td class="px-3 py-2.5">
                                    @if($isAssigned)
                                        <span class="text-xs text-green-700 font-medium bg-green-100 px-2 py-0.5 rounded-full">Zugewiesen</span>
                                    @elseif($qualifiesAny)
                                        <form method="POST" action="{{ route('admin.competitions.signup.quick-assign', [$competition, $signupRequest]) }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $qs->id }}">
                                            <button type="submit"
                                                    class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-lg transition-colors font-medium">
                                                Zuweisen
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">Keine PZ</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Tab: Meldungen (erweitert: Streckenauswahl + DSV7-Generator) --}}
        <div x-show="activeTab === 'meldungen'" x-cloak
             x-data="{
                 saving: {},
                 errors: {},
                 saved: {},
                 async toggleEntry(userId, eventId, discipline, distance, gender, ageGroup, currentlyEntered) {
                     this.saving[userId + '_' + eventId] = true;
                     try {
                         if (currentlyEntered) {
                             // Find entry id and delete
                             const res = await fetch('{{ route('admin.competitions.entries.index', $competition) }}');
                             const data = await res.json();
                             const userEntries = data.entries[userId] ?? [];
                             const entry = userEntries.find(e => e.discipline === discipline && e.distance === distance && (e.age_group == ageGroup || (!e.age_group && !ageGroup)));
                             if (entry) {
                                 await fetch('{{ url('admin/wettkaempfe/' . $competition->id . '/meldungen/entries') }}/' + entry.id, {
                                     method: 'DELETE',
                                     headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                 });
                             }
                         } else {
                             await fetch('{{ route('admin.competitions.entries.store', $competition) }}', {
                                 method: 'POST',
                                 headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                                 body: JSON.stringify({ user_id: userId, competition_event_id: eventId, discipline, distance, gender, age_group: ageGroup }),
                             });
                         }
                         this.saved[userId + '_' + eventId] = true;
                         setTimeout(() => delete this.saved[userId + '_' + eventId], 2000);
                     } catch(e) {
                         this.errors[userId + '_' + eventId] = true;
                     } finally {
                         delete this.saving[userId + '_' + eventId];
                         // Reload page to refresh state
                         window.location.reload();
                     }
                 }
             }"
             class="p-5 space-y-5">

            @if(!$signupRequest || !$signupRequest->isClosed())
                <div class="text-center py-8">
                    <svg class="mx-auto w-10 h-10 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm text-gray-400">Die Meldeliste steht zur Verfügung, sobald die Anmeldeabfrage geschlossen wurde.</p>
                    @if($signupRequest?->isActive())
                        <p class="text-xs text-gray-400 mt-1">Gehe zu „Anmeldungen" und schließe die Abfrage.</p>
                    @elseif(!$signupRequest)
                        <p class="text-xs text-gray-400 mt-1">Starte zunächst eine Anmeldeabfrage im Tab „Anmeldungen".</p>
                    @endif
                </div>
            @else
                @php
                    $attending = $signupRequest->responses->where('status', 'attending')
                        ->sortBy(fn($r) => $r->user?->lastname . $r->user?->firstname);
                    $events = $competition->events->sortBy('event_number');
                    // Load existing entries grouped by user
                    $existingEntries = \App\Models\CompetitionEntry::where('competition_id', $competition->id)
                        ->where('status', 'entered')
                        ->get()
                        ->groupBy('user_id');
                @endphp

                {{-- Header: Meldeschluss + DSV7-Downloads --}}
                <div class="flex items-center justify-between flex-wrap gap-3 pb-4 border-b border-gray-100">
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $attending->count() }} Schwimmer · {{ $existingEntries->sum(fn($g) => $g->count()) }} Meldungen</h3>
                        @if($competition->meldeschluss)
                            <p class="text-sm text-gray-500 mt-0.5">Meldeschluss: <strong>{{ $competition->meldeschluss->format('d.m.Y') }}</strong></p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($events->isNotEmpty())
                        <a href="{{ route('admin.competitions.dsv7.meldedatei', $competition) }}"
                           class="bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Meldedatei *-Vm.DSV7
                        </a>
                        <a href="{{ route('admin.competitions.dsv7.definitionsdatei', $competition) }}"
                           class="border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Definitionsdatei *-Wk.DSV7
                        </a>
                        @endif
                    </div>
                </div>

                @if($attending->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">Keine Zusagen vorhanden.</p>
                @elseif($events->isEmpty())
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
                        Noch keine Wettkampffolge importiert. Lade eine <strong>*-Wk.DSV7</strong>-Datei im Tab „Import" hoch, um Strecken anzuzeigen.
                    </div>
                @else
                    {{-- Per-Schwimmer-Accordion --}}
                    <div class="space-y-3">
                        @foreach($attending as $response)
                            @php
                                $user       = $response->user;
                                if (!$user) continue;
                                $userEntries = $existingEntries->get($user->id, collect());
                                $enteredKeys = $userEntries->map(fn($e) => $e->discipline . '_' . $e->distance . '_' . ($e->age_group ?? ''))->all();
                                $userAge = ($competition->date && $user->birth_date)
                                    ? ($competition->date->year - $user->birth_date->year) : null;
                            @endphp
                            <details class="border border-gray-200 rounded-xl overflow-hidden group" open>
                                <summary class="flex items-center justify-between px-4 py-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors select-none">
                                    <div class="flex items-center gap-3">
                                        <span class="font-semibold text-gray-800 text-sm">{{ $user->name }}</span>
                                        @if($user->birth_year ?? $user->birth_date?->year)
                                            <span class="text-xs text-gray-500">Jg. {{ $user->birth_year ?? $user->birth_date?->year }}</span>
                                        @endif
                                        @if($user->dsv_id)
                                            <span class="text-xs font-mono text-gray-400">{{ $user->dsv_id }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-medium">
                                            {{ $userEntries->count() }} Meldung(en)
                                        </span>
                                        <svg class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </summary>

                                <div class="divide-y divide-gray-50">
                                    @foreach($events->unique(fn($e) => $e->discipline . '_' . $e->distance . '_' . $e->event_number) as $event)
                                        @php
                                            $key   = $event->discipline . '_' . $event->distance . '_' . ($event->age_group ?? '');
                                            $entered = in_array($key, $enteredKeys);
                                            $match   = $userEntries->first(fn($e) => $e->discipline === $event->discipline && $e->distance === $event->distance);

                                            // Gender eligibility
                                            $genderOk = ($event->gender === 'X') ||
                                                        ($user->gender && strtoupper($user->gender) === $event->gender);

                                            // Age eligibility
                                            $ageOk = $userAge === null ||
                                                     (($event->age_min === null || $userAge >= $event->age_min) &&
                                                      ($event->age_max === null || $userAge <= $event->age_max));

                                            // Best qualifying-period time for suggestion
                                            $rKey       = "{$user->id}_{$event->discipline}_{$event->distance}";
                                            $qualBestMs = $qualResultsByUserEvent[$rKey] ?? null;
                                            $allBestMs  = $bestTimesByUserEvent[$rKey] ?? null;

                                            // Entry status
                                            $entryTime    = $match?->entry_time_formatted;
                                            $entryMs      = $match?->entry_time_ms;
                                            $meetsPflicht = true;
                                            if ($entered && $entryMs && $event->qualifying_time_ms) {
                                                $meetsPflicht = $entryMs <= $event->qualifying_time_ms;
                                            }
                                            // Check qualifying period
                                            $qualOk = $event->qualifying_time_ms
                                                ? ($qualBestMs !== null && $qualBestMs <= $event->qualifying_time_ms)
                                                : true;
                                        @endphp
                                        <div class="flex items-center gap-4 px-4 py-2.5 hover:bg-gray-50 transition-colors
                                             {{ (!$genderOk || !$ageOk) && !$entered ? 'opacity-40' : '' }}">
                                            <form method="POST"
                                                  action="{{ $entered
                                                      ? route('admin.competitions.entries.destroy', [$competition, $match?->id ?? 0])
                                                      : route('admin.competitions.entries.store', $competition) }}"
                                                  class="flex items-center gap-4 flex-1">
                                                @csrf
                                                @if($entered) @method('DELETE') @endif
                                                @if(!$entered)
                                                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                                                    <input type="hidden" name="discipline" value="{{ $event->discipline }}">
                                                    <input type="hidden" name="distance" value="{{ $event->distance }}">
                                                    <input type="hidden" name="gender" value="{{ $user->gender ?? $event->gender }}">
                                                    <input type="hidden" name="age_group" value="{{ $event->age_group }}">
                                                    <input type="hidden" name="competition_event_id" value="{{ $event->id }}">
                                                @endif

                                                <button type="submit"
                                                        class="w-5 h-5 rounded border-2 flex-shrink-0 flex items-center justify-center transition-colors
                                                               {{ $entered ? 'bg-primary border-primary' : 'border-gray-300 hover:border-primary' }}">
                                                    @if($entered)
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    @endif
                                                </button>

                                                <div class="flex-1 min-w-0">
                                                    <span class="text-sm font-medium text-gray-800">WK {{ $event->event_number }} · {{ $event->distance_label }}m {{ $event->discipline_label }}</span>
                                                    @if($event->age_group)
                                                        <span class="text-xs text-gray-400 ml-1">{{ $event->age_group }}</span>
                                                    @endif
                                                </div>

                                                <div class="text-right shrink-0 space-y-0.5">
                                                    @if($entered && $entryTime)
                                                        {{-- Already entered: show entry time --}}
                                                        <div class="text-xs font-mono {{ $meetsPflicht ? 'text-green-600' : 'text-red-600' }}">{{ $entryTime }}</div>
                                                    @elseif(!$entered && $qualBestMs)
                                                        {{-- Not yet entered: show best qualifying-period time as suggestion --}}
                                                        <div class="text-xs font-mono {{ $qualOk ? 'text-green-600' : 'text-orange-500' }}">
                                                            {{ \App\Models\SwimmingTime::formatMs($qualBestMs) }}
                                                        </div>
                                                        <div class="text-xs text-gray-400">Vorschlag</div>
                                                    @elseif(!$entered && $allBestMs)
                                                        <div class="text-xs font-mono text-gray-400">{{ \App\Models\SwimmingTime::formatMs($allBestMs) }}</div>
                                                        <div class="text-xs text-gray-300">Best ges.</div>
                                                    @endif
                                                    @if($event->qualifying_time_ms)
                                                        <div class="text-xs text-gray-400">PZ: {{ $event->formatted_qualifying_time }}</div>
                                                    @endif
                                                </div>

                                                @if($event->qualifying_time_ms)
                                                    @if($entered && !$meetsPflicht)
                                                        <span class="text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full shrink-0">PZ fehlt</span>
                                                    @elseif(!$entered && $qualOk && $qualBestMs)
                                                        <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full shrink-0">PZ ✓</span>
                                                    @elseif(!$entered && $event->qualifying_time_ms && !$qualBestMs)
                                                        <span class="text-xs bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded-full shrink-0">PZ?</span>
                                                    @endif
                                                @endif
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>

                    {{-- Nicht-Zusagen --}}
                    @php $notAttending = $signupRequest->responses->where('status', 'not_attending'); @endphp
                    @if($notAttending->isNotEmpty())
                        <details class="text-sm text-gray-400 mt-4">
                            <summary class="cursor-pointer hover:text-gray-600 select-none">
                                {{ $notAttending->count() }} Absage(n) anzeigen
                            </summary>
                            <ul class="mt-2 space-y-0.5 pl-4">
                                @foreach($notAttending->sortBy(fn($r) => $r->user?->lastname) as $r)
                                    <li>{{ $r->user?->name }}@if($r->note) <span class="text-gray-400">– {{ $r->note }}</span>@endif</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @endif
            @endif
        </div>

        {{-- Tab: Ausschreibung --}}
        <div x-show="activeTab === 'ausschreibung'" x-cloak
             x-data="{
                uploading: false,
                saving: false,
                parsed: {{ json_encode($competition->announcement_data) }},
                pdfPath: '{{ $competition->announcement_pdf_path ?? '' }}',
                error: null,
                saveMsg: null,
                deadlineLabels: {
                    meldeschluss_einzel: 'Meldeschluss Einzel',
                    meldeschluss_staffel: 'Meldeschluss Staffeln',
                    eingangsbestaetigung: 'Eingangsbestätigung',
                    meldebestaetigung: 'Meldebestätigung',
                    veroffentlichung_meldeergebnis: 'Veröffentlichung Meldeergebnis',
                    meldegeld_zahlung: 'Meldegeld-Zahlung',
                    beanstandungen: 'Beanstandungsfrist',
                    warmup: 'Einschwimmen',
                    sonstiges: 'Sonstiges',
                },
                async uploadPdf(event) {
                    const form = event.target;
                    const fd = new FormData(form);
                    this.uploading = true;
                    this.error = null;
                    try {
                        const res = await fetch('{{ route('admin.competitions.announcement.parse', $competition) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: fd,
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.error ?? 'Unbekannter Fehler');
                        this.parsed = json.data;
                        this.pdfPath = json.pdf_path;
                    } catch(e) {
                        this.error = e.message;
                    } finally {
                        this.uploading = false;
                    }
                },
                async save() {
                    this.saving = true;
                    this.saveMsg = null;
                    this.error = null;
                    try {
                        const res = await fetch('{{ route('admin.competitions.announcement.save', $competition) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                pdf_path: this.pdfPath,
                                announcement: this.parsed,
                                apply_to_fields: ['name','level','date','date_end','organizer','ausrichter','location','meldeschluss','venue_details','kampfgericht','contact_info'],
                            }),
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.error ?? 'Fehler beim Speichern');
                        this.saveMsg = 'Daten übernommen.';
                    } catch(e) {
                        this.error = e.message;
                    } finally {
                        this.saving = false;
                    }
                },
                fmt(cents) {
                    if (!cents) return '–';
                    return (cents / 100).toFixed(2).replace('.', ',') + ' €';
                },
                fmtDate(d) {
                    if (!d) return '–';
                    const p = d.split('-');
                    return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : d;
                },
             }"
             class="p-5 space-y-6">

            {{-- DSV Kopfdaten (aus Definitionsdatei-Import) --}}
            @if($competition->dsv_header_data)
            @php $hd = $competition->dsv_header_data; @endphp
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-indigo-800">Kopfdaten aus DSV7-Definitionsdatei</h3>
                    @if($hd['software'] ?? null)
                        <span class="text-xs text-indigo-400">{{ $hd['software'] }}</span>
                    @endif
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-5">

                    @if($hd['venue_name'] ?? null)
                    <div>
                        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Wettkampfstätte</p>
                        <p class="text-sm font-medium text-gray-800">{{ $hd['venue_name'] }}</p>
                        @if($hd['venue_street'] ?? null)
                            <p class="text-xs text-gray-500">{{ $hd['venue_street'] }}</p>
                            <p class="text-xs text-gray-500">{{ $hd['venue_plz'] ?? '' }} {{ $hd['venue_city'] ?? '' }}</p>
                        @endif
                        @if($hd['venue_phone'] ?? null)
                            <p class="text-xs text-gray-400 mt-1">{{ $hd['venue_phone'] }}</p>
                        @endif
                    </div>
                    @endif

                    @if($hd['ausrichter_name'] ?? null)
                    <div>
                        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Ausrichter</p>
                        <p class="text-sm font-medium text-gray-800">{{ $hd['ausrichter_name'] }}</p>
                        @if($hd['ausrichter_person'] ?? null)
                            <p class="text-xs text-gray-500">{{ $hd['ausrichter_person'] }}</p>
                        @endif
                        @if($hd['ausrichter_street'] ?? null)
                            <p class="text-xs text-gray-500">{{ $hd['ausrichter_street'] }}, {{ $hd['ausrichter_plz'] ?? '' }} {{ $hd['ausrichter_city'] ?? '' }}</p>
                        @endif
                        @if($hd['ausrichter_phone'] ?? null)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $hd['ausrichter_phone'] }}</p>
                        @endif
                        @if($hd['ausrichter_email'] ?? null)
                            <p class="text-xs text-gray-400">{{ $hd['ausrichter_email'] }}</p>
                        @endif
                    </div>
                    @endif

                    <div class="space-y-3">
                        @if($hd['meldeschluss_date'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Meldeschluss</p>
                            <p class="text-sm font-semibold text-primary">
                                {{ $hd['meldeschluss_date'] }}
                                @if($hd['meldeschluss_time'] ?? null) · {{ $hd['meldeschluss_time'] }} Uhr @endif
                            </p>
                        </div>
                        @endif
                        @if($hd['melde_email'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Meldeadresse</p>
                            @if($hd['melde_person'] ?? null)
                                <p class="text-xs text-gray-600">{{ $hd['melde_person'] }}</p>
                            @endif
                            <p class="text-xs text-gray-500">{{ $hd['melde_email'] }}</p>
                            @if($hd['melde_phone'] ?? null)
                                <p class="text-xs text-gray-400">{{ $hd['melde_phone'] }}</p>
                            @endif
                        </div>
                        @endif
                    </div>

                    @if($hd['bank_iban'] ?? null)
                    <div>
                        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Bankverbindung</p>
                        @if($hd['bank_recipient'] ?? null)
                            <p class="text-xs font-medium text-gray-700">{{ $hd['bank_recipient'] }}</p>
                        @endif
                        <p class="text-xs font-mono text-gray-600">{{ $hd['bank_iban'] }}</p>
                        @if($hd['bank_bic'] ?? null)
                            <p class="text-xs font-mono text-gray-400">BIC: {{ $hd['bank_bic'] }}</p>
                        @endif
                    </div>
                    @endif

                    @if(!empty($hd['sessions']))
                    <div>
                        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Abschnitte</p>
                        @foreach($hd['sessions'] as $sess)
                        <div class="text-xs text-gray-600 mb-1">
                            <span class="font-medium">Abschnitt {{ $sess['nr'] }}:</span>
                            {{ $sess['date'] ?? '' }}
                            @if($sess['pause_after'] ?? null)
                                · <span class="text-amber-600">+{{ $sess['pause_after'] }} nach Ende des vorherigen Abschnitts</span>
                            @elseif(($sess['start_time'] ?? '') && $sess['start_time'] !== '00:00')
                                · Start {{ $sess['start_time'] }} Uhr
                            @endif
                            @if(($sess['warmup_start'] ?? '') && $sess['warmup_start'] !== '00:00')
                                · Einschwimmen ab {{ $sess['warmup_start'] }} Uhr
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @if(($hd['besonderes'] ?? null) && $hd['besonderes'] !== '')
                    <div>
                        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Besonderes</p>
                        <p class="text-xs text-gray-600">{{ $hd['besonderes'] }}</p>
                    </div>
                    @endif

                    @if(($hd['announcement_url'] ?? null) && $hd['announcement_url'] !== '')
                    <div>
                        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-1">Ausschreibung Online</p>
                        <a href="{{ $hd['announcement_url'] }}" target="_blank" rel="noopener"
                           class="text-xs text-primary hover:underline break-all">{{ $hd['announcement_url'] }}</a>
                    </div>
                    @endif

                </div>
            </div>
            @endif

            {{-- Upload-Bereich --}}
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                <h3 class="font-semibold text-gray-800 mb-1">Ausschreibung als PDF importieren</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Das PDF wird direkt an die Claude AI gesendet. Es werden Fristen, Wettkampfstätte,
                    Kampfgericht, Sonderregeln und Qualifikationszeiten automatisch extrahiert.
                </p>
                <form @submit.prevent="uploadPdf($event)" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1 min-w-48">
                        <input type="file" name="announcement_pdf" accept=".pdf" required
                               class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
                    </div>
                    <button type="submit"
                            :disabled="uploading"
                            class="bg-primary hover:bg-primary-dark disabled:opacity-50 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors flex items-center gap-2">
                        <svg x-show="uploading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="uploading ? 'Wird analysiert…' : 'PDF analysieren'"></span>
                    </button>
                </form>
                <div x-show="error" x-cloak class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" x-text="error"></div>
                <div x-show="saveMsg" x-cloak class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700" x-text="saveMsg"></div>
            </div>

            {{-- Geparste Daten --}}
            <template x-if="parsed">
                <div class="space-y-5">

                    {{-- Header-Leiste mit Übernehmen-Button --}}
                    <div class="flex items-center justify-between flex-wrap gap-3 pb-3 border-b border-gray-100">
                        <div>
                            <h3 class="font-semibold text-gray-800" x-text="parsed.competition?.name ?? 'Geparste Ausschreibung'"></h3>
                            <p class="text-xs text-gray-400 mt-0.5"
                               x-text="'Geparst: ' + (parsed._meta?.parsed_at ? new Date(parsed._meta.parsed_at).toLocaleString('de-DE') : '–')"></p>
                        </div>
                        <button @click="save()"
                                :disabled="saving"
                                class="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors flex items-center gap-2">
                            <svg x-show="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-text="saving ? 'Wird gespeichert…' : 'Daten in Wettkampf übernehmen'"></span>
                        </button>
                    </div>

                    {{-- Veranstalter & Stätte --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        {{-- Wettkampfstätte --}}
                        <template x-if="parsed.venue">
                            <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Wettkampfstätte</h4>
                                <p class="font-medium text-gray-800 text-sm" x-text="parsed.venue.name ?? '–'"></p>
                                <p class="text-sm text-gray-600"
                                   x-text="[parsed.venue.street, parsed.venue.zip, parsed.venue.city].filter(Boolean).join(', ') || '–'"></p>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm mt-2">
                                    <template x-if="parsed.venue.pool_length_m">
                                        <div class="text-gray-600">Bahn: <span class="font-medium text-gray-800" x-text="parsed.venue.pool_length_m + 'm'"></span></div>
                                    </template>
                                    <template x-if="parsed.venue.lanes_heats">
                                        <div class="text-gray-600">Bahnen VL: <span class="font-medium text-gray-800" x-text="parsed.venue.lanes_heats"></span></div>
                                    </template>
                                    <template x-if="parsed.venue.lanes_finals">
                                        <div class="text-gray-600">Bahnen Finale: <span class="font-medium text-gray-800" x-text="parsed.venue.lanes_finals"></span></div>
                                    </template>
                                    <template x-if="parsed.venue.water_temp_c">
                                        <div class="text-gray-600">Wassertemp.: <span class="font-medium text-gray-800" x-text="parsed.venue.water_temp_c + ' °C'"></span></div>
                                    </template>
                                    <template x-if="parsed.venue.water_depth_m">
                                        <div class="text-gray-600">Tiefe: <span class="font-medium text-gray-800" x-text="parsed.venue.water_depth_m + ' m'"></span></div>
                                    </template>
                                    <template x-if="parsed.venue.timing">
                                        <div class="text-gray-600">Zeitmessung: <span class="font-medium text-gray-800" x-text="parsed.venue.timing"></span></div>
                                    </template>
                                    <template x-if="parsed.venue.lane_ropes">
                                        <div class="col-span-2 text-gray-600">Leinen: <span class="font-medium text-gray-800" x-text="parsed.venue.lane_ropes"></span></div>
                                    </template>
                                </div>
                                <template x-if="parsed.venue.warmup_pool">
                                    <div class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-500">
                                        Einschwinmbecken: <span x-text="parsed.venue.warmup_pool.length_m + 'm, ' + parsed.venue.warmup_pool.depth_m + 'm tief, ' + parsed.venue.warmup_pool.temp_c + '°C'"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Veranstalter & Meldung --}}
                        <div class="space-y-4">
                            <template x-if="parsed.organizer">
                                <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-1">
                                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Veranstalter</h4>
                                    <div class="text-sm text-gray-600">Veranstalter: <span class="font-medium text-gray-800" x-text="parsed.organizer.veranstalter ?? '–'"></span></div>
                                    <template x-if="parsed.organizer.ausrichter && parsed.organizer.ausrichter !== parsed.organizer.veranstalter">
                                        <div class="text-sm text-gray-600">Ausrichter: <span class="font-medium text-gray-800" x-text="parsed.organizer.ausrichter"></span></div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="parsed.entry">
                                <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-1">
                                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Meldeanschrift & Zahlung</h4>
                                    <template x-if="parsed.entry.contact_name">
                                        <div class="text-sm text-gray-600">Meldeservice: <span class="font-medium text-gray-800" x-text="parsed.entry.contact_name"></span></div>
                                    </template>
                                    <template x-if="parsed.entry.contact_email">
                                        <div class="text-sm text-gray-600">E-Mail: <span class="font-medium text-gray-800" x-text="parsed.entry.contact_email"></span></div>
                                    </template>
                                    <template x-if="parsed.entry.fee_individual_cents">
                                        <div class="text-sm text-gray-600">Meldegeld Einzel: <span class="font-semibold text-gray-800" x-text="fmt(parsed.entry.fee_individual_cents)"></span></div>
                                    </template>
                                    <template x-if="parsed.entry.payment_iban">
                                        <div class="text-sm text-gray-600 font-mono">IBAN: <span x-text="parsed.entry.payment_iban"></span></div>
                                    </template>
                                    <template x-if="parsed.entry.payment_bic">
                                        <div class="text-sm text-gray-600 font-mono">BIC: <span x-text="parsed.entry.payment_bic"></span></div>
                                    </template>
                                    <template x-if="parsed.entry.payment_bank">
                                        <div class="text-sm text-gray-600">Bank: <span x-text="parsed.entry.payment_bank"></span></div>
                                    </template>
                                    <template x-if="parsed.entry.payment_reference">
                                        <div class="text-sm text-gray-600">Verwendungszweck: <span class="font-medium text-gray-800" x-text="parsed.entry.payment_reference"></span></div>
                                    </template>
                                    <template x-if="parsed.entry.format">
                                        <div class="text-sm text-gray-600">Format: <span x-text="parsed.entry.format"></span></div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Fristen --}}
                    <template x-if="parsed.deadlines && parsed.deadlines.length">
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Fristen & Termine</h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 border-b border-gray-100">
                                        <tr>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Datum</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Uhrzeit</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Art</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Beschreibung</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <template x-for="(dl, i) in parsed.deadlines" :key="i">
                                            <tr :class="dl.type === 'meldeschluss_einzel' ? 'bg-amber-50' : 'hover:bg-gray-50'">
                                                <td class="px-4 py-2.5 font-medium text-gray-800 whitespace-nowrap" x-text="fmtDate(dl.date)"></td>
                                                <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap" x-text="dl.time ?? '–'"></td>
                                                <td class="px-4 py-2.5 whitespace-nowrap">
                                                    <span :class="dl.type === 'meldeschluss_einzel' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'"
                                                          class="text-xs px-2 py-0.5 rounded-full font-medium"
                                                          x-text="deadlineLabels[dl.type] ?? dl.type"></span>
                                                </td>
                                                <td class="px-4 py-2.5 text-gray-600 text-xs" x-text="dl.description ?? ''"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    {{-- Kampfgericht --}}
                    <template x-if="parsed.kampfgericht">
                        <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Kampfgericht</h4>
                            <template x-if="parsed.kampfgericht.note">
                                <p class="text-sm text-gray-700" x-text="parsed.kampfgericht.note"></p>
                            </template>
                            <template x-if="parsed.kampfgericht.special && parsed.kampfgericht.special.length">
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(s, i) in parsed.kampfgericht.special" :key="i">
                                        <span class="text-xs bg-blue-50 text-blue-700 border border-blue-100 px-2.5 py-1 rounded-full" x-text="s"></span>
                                    </template>
                                </div>
                            </template>
                            <template x-if="parsed.kampfgericht.contacts && parsed.kampfgericht.contacts.length">
                                <div class="space-y-1.5">
                                    <template x-for="(c, i) in parsed.kampfgericht.contacts" :key="i">
                                        <div class="text-sm text-gray-600">
                                            <span class="font-medium text-gray-800" x-text="c.role + ': '"></span>
                                            <span x-text="c.name"></span>
                                            <template x-if="c.email">
                                                <span class="text-gray-400 font-mono text-xs" x-text="' (' + c.email + ')'"></span>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Besondere Regeln & WB-Abweichungen --}}
                    <template x-if="parsed.special_rules && parsed.special_rules.length">
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Besondere Bestimmungen</h4>
                                <template x-if="parsed.special_rules.some(r => r.is_deviation_from_wb)">
                                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">enthält WB-Abweichungen</span>
                                </template>
                            </div>
                            <div class="divide-y divide-gray-50">
                                <template x-for="(rule, i) in parsed.special_rules" :key="i">
                                    <div class="px-4 py-3 space-y-1" :class="rule.is_deviation_from_wb ? 'bg-red-50/40' : ''">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-800 text-sm" x-text="rule.title"></span>
                                            <template x-if="rule.is_deviation_from_wb">
                                                <span class="text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full font-medium">Abweichung WB</span>
                                            </template>
                                            <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full" x-text="rule.category"></span>
                                        </div>
                                        <p class="text-sm text-gray-600 leading-relaxed" x-text="rule.text"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- ENM --}}
                    <template x-if="parsed.enm && parsed.enm.cases && parsed.enm.cases.length">
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Erhöhtes Nachträgliches Meldegeld (ENM)</h4>
                            </div>
                            <div class="divide-y divide-gray-50">
                                <template x-for="(c, i) in parsed.enm.cases" :key="i">
                                    <div class="px-4 py-3 flex flex-wrap items-start gap-x-6 gap-y-1">
                                        <div class="flex-1 min-w-48">
                                            <p class="text-sm text-gray-700" x-text="c.description"></p>
                                            <template x-if="c.waiver_condition">
                                                <p class="text-xs text-gray-400 mt-0.5" x-text="'Erlass: ' + c.waiver_condition"></p>
                                            </template>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-lg font-bold text-red-700" x-text="fmt(c.amount_cents)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Akkreditierung --}}
                    <template x-if="parsed.accreditation">
                        <div class="bg-white border border-gray-200 rounded-xl p-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Akkreditierung / Betreuer</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                <template x-if="parsed.accreditation.initial_coaches">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <div class="text-2xl font-bold text-gray-800" x-text="parsed.accreditation.initial_coaches"></div>
                                        <div class="text-xs text-gray-500">Betreuer für erste <span x-text="parsed.accreditation.initial_athletes"></span> Schwimmer</div>
                                    </div>
                                </template>
                                <template x-if="parsed.accreditation.additional_per_athletes">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <div class="text-2xl font-bold text-gray-800" x-text="'+' + parsed.accreditation.additional_count"></div>
                                        <div class="text-xs text-gray-500">je weitere <span x-text="parsed.accreditation.additional_per_athletes"></span> Schwimmer</div>
                                    </div>
                                </template>
                                <template x-if="parsed.accreditation.extra_card_fee_cents">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <div class="text-2xl font-bold text-gray-800" x-text="fmt(parsed.accreditation.extra_card_fee_cents)"></div>
                                        <div class="text-xs text-gray-500">je Zusatz-Akkreditierung</div>
                                    </div>
                                </template>
                            </div>
                            <template x-if="parsed.accreditation.notes">
                                <p class="mt-3 text-sm text-gray-600" x-text="parsed.accreditation.notes"></p>
                            </template>
                        </div>
                    </template>

                    {{-- Qualifikation --}}
                    <template x-if="parsed.qualification">
                        <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Qualifikation</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                                <template x-if="parsed.qualification.period_from">
                                    <div class="text-gray-600">Zeitraum:
                                        <span class="font-medium text-gray-800"
                                              x-text="fmtDate(parsed.qualification.period_from) + ' – ' + fmtDate(parsed.qualification.period_to)"></span>
                                    </div>
                                </template>
                                <template x-if="parsed.qualification.pool_type">
                                    <div class="text-gray-600">Bahn: <span class="font-medium text-gray-800" x-text="parsed.qualification.pool_type"></span></div>
                                </template>
                                <template x-if="parsed.qualification.series">
                                    <div class="text-gray-600">Serie: <span class="font-medium text-gray-800" x-text="parsed.qualification.series"></span></div>
                                </template>
                                <template x-if="parsed.qualification.rudolph_min">
                                    <div class="text-gray-600">Rudolph-Punkte min.: <span class="font-medium text-gray-800" x-text="parsed.qualification.rudolph_min"></span></div>
                                </template>
                            </div>
                            <template x-if="parsed.qualification.note">
                                <p class="text-sm text-gray-500 mt-1" x-text="parsed.qualification.note"></p>
                            </template>
                        </div>
                    </template>

                    {{-- Qualifikationszeiten --}}
                    <template x-if="parsed.qualifying_times && Object.keys(parsed.qualifying_times).length">
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Qualifikationszeiten</h4>
                            </div>
                            <div class="p-4 space-y-4">
                                <template x-for="[gender, yearGroups] in Object.entries(parsed.qualifying_times)" :key="gender">
                                    <div>
                                        <p class="text-xs font-semibold text-gray-500 mb-2"
                                           x-text="gender === 'M' ? 'Männlich' : 'Weiblich'"></p>
                                        <div class="overflow-x-auto">
                                            <table class="text-xs border-collapse">
                                                <thead>
                                                    <tr class="bg-gray-50">
                                                        <th class="px-2 py-1.5 text-left font-semibold text-gray-500 border border-gray-100 sticky left-0 bg-gray-50">Strecke</th>
                                                        <template x-for="year in Object.keys(yearGroups)" :key="year">
                                                            <th class="px-2 py-1.5 text-center font-semibold text-gray-500 border border-gray-100" x-text="year"></th>
                                                        </template>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="key in ['50F','100F','200F','400F','800F','1500F','50B','100B','200B','50R','100R','200R','50S','100S','200S','200L','400L']" :key="key">
                                                        <template x-if="Object.values(yearGroups).some(y => y[key])">
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-2 py-1.5 font-medium text-gray-700 border border-gray-100 sticky left-0 bg-white whitespace-nowrap"
                                                                    x-text="key.replace('F','m Fr.').replace('B','m Br.').replace('R','m Rü.').replace('S','m Sch.').replace('L','m La.')"></td>
                                                                <template x-for="year in Object.keys(yearGroups)" :key="year">
                                                                    <td class="px-2 py-1.5 text-center text-gray-700 border border-gray-100 font-mono whitespace-nowrap"
                                                                        x-text="yearGroups[year][key] ?? '–'"></td>
                                                                </template>
                                                            </tr>
                                                        </template>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                </div>
            </template>

            <template x-if="!parsed">
                <div class="text-center py-10">
                    <svg class="mx-auto w-10 h-10 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-gray-400">Noch keine Ausschreibung importiert.<br>PDF oben hochladen, um Fristen, Stätte und Regeln automatisch zu extrahieren.</p>
                </div>
            </template>

        </div>

        {{-- Tab: Dokumente --}}
        <div x-show="activeTab === 'dokumente'" x-cloak>
            <div class="p-5 space-y-6">

                @foreach(\App\Models\CompetitionDocument::CATEGORIES as $catKey => $catLabel)
                @php $catDocs = $documents->get($catKey, collect()); @endphp
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-3 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">{{ $catLabel }}</h3>
                        </div>
                        <form method="POST" action="{{ route('admin.competitions.documents.store', $competition) }}"
                              enctype="multipart/form-data" class="flex gap-2 items-center shrink-0">
                            @csrf
                            <input type="hidden" name="category" value="{{ $catKey }}">
                            <input type="file" name="file"
                                   class="text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
                            <button type="submit"
                                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-3 py-1.5 rounded-lg text-xs transition-colors whitespace-nowrap">
                                Hochladen
                            </button>
                        </form>
                    </div>

                    @if($catDocs->isEmpty())
                        <p class="text-xs text-gray-400 px-4 py-3">Noch keine {{ $catLabel }}-Datei hochgeladen.</p>
                    @else
                        <ul class="divide-y divide-gray-50">
                            @foreach($catDocs as $doc)
                            <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
                                <svg class="w-5 h-5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $doc->original_name }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $doc->file_size_formatted }}
                                        · {{ $doc->created_at->deBerlin('d.m.Y H:i') }}
                                        @if($doc->createdBy) · {{ $doc->createdBy->name }} @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ route('admin.competitions.documents.download', [$competition, $doc]) }}"
                                       class="text-xs text-primary hover:text-primary-dark font-medium">
                                        Download
                                    </a>
                                    <form method="POST" action="{{ route('admin.competitions.documents.destroy', [$competition, $doc]) }}"
                                          onsubmit="return confirm('Dokument wirklich löschen?')"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">Löschen</button>
                                    </form>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @endforeach

            </div>
        </div>

    </div>{{-- end tab container --}}

</div>
@endsection

@push('styles')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
  /* Quill-Editor in Tab-Kontext */
  .ql-container { font-family: inherit; font-size: 14px; border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; }
  .ql-toolbar { border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; border-color: #d1d5db !important; background: #f9fafb; }
  .ql-container { border-color: #d1d5db !important; }
  .ql-editor { min-height: 280px; line-height: 1.65; }
  .ql-editor p { margin-bottom: 0.5em; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('auswertungEditor', () => ({
        loading: false,
        saving:  false,
        error:   '',
        saveMsg: '',
        quill:   null,

        _generateUrl: '{{ route('admin.competitions.analysis', $competition) }}',
        _saveUrl:     '{{ route('admin.competitions.analysis.save', $competition) }}',
        _csrf:        '{{ csrf_token() }}',
        _savedHtml:   {!! json_encode($competition->analysis_text ?? '') !!},

        init() {
            this.quill = new Quill(this.$refs.editorContainer, {
                theme: 'snow',
                placeholder: 'KI-Text generieren oder hier direkt eingeben…',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ header: [2, 3, false] }],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['clean']
                    ]
                }
            });
            if (this._savedHtml) {
                this.quill.root.innerHTML = this._savedHtml;
            }
        },

        async generate() {
            this.loading = true;
            this.error   = '';
            try {
                const r = await fetch(this._generateUrl, {
                    method:  'POST',
                    headers: { 'X-CSRF-TOKEN': this._csrf, 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.error) {
                    this.error = d.error;
                } else {
                    // Plain text → HTML paragraphs via Quill clipboard API
                    const paras = d.text.split(/\n\n+/);
                    const html  = paras.map(p => '<p>' + p.trim().replace(/\n/g, '<br>') + '</p>').join('');
                    this.quill.clipboard.dangerouslyPasteHTML(0, html);
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async save() {
            this.saving  = true;
            this.saveMsg = '';
            this.error   = '';
            try {
                const r = await fetch(this._saveUrl, {
                    method:  'POST',
                    headers: {
                        'X-CSRF-TOKEN':  this._csrf,
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({ text: this.quill.root.innerHTML })
                });
                const d = await r.json();
                if (d.success) {
                    this.saveMsg = 'Gespeichert.';
                    setTimeout(() => { this.saveMsg = ''; }, 3000);
                } else {
                    this.error = d.message || 'Fehler beim Speichern.';
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.saving = false;
            }
        }
    }));
});
</script>
@endpush

@extends('layouts.app')
@section('title', 'Ergebnisse importieren – Vorschau')
@section('page-title', 'Ergebnisse importieren')

@section('content')
@php
    $meet        = $parsed['meets'][0];
    $allClubs    = $meet['clubs'];
    $clubCount   = count($allClubs);
    // Build JS array of suggested club indices for Alpine initialisation
    $suggestedJs = '[' . implode(',', array_map('intval', $suggestedClubs)) . ']';
@endphp

<div class="mt-2 space-y-6"
     x-data="{
         meetIndex: 0,
         visibleClubs: {{ $suggestedJs }},
         toggleClub(ci) {
             if (this.visibleClubs.includes(ci)) {
                 this.visibleClubs = this.visibleClubs.filter(i => i !== ci);
             } else {
                 this.visibleClubs.push(ci);
             }
         },
         allSelected() { return this.visibleClubs.length === {{ $clubCount }}; },
         toggleAll() {
             this.visibleClubs = this.allSelected()
                 ? []
                 : Array.from({length: {{ $clubCount }}}, (_, i) => i);
         }
     }">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.competitions.index') }}" class="hover:text-primary">Wettkämpfe</a>
        <span>›</span>
        <a href="{{ route('admin.competitions.show', $competition) }}" class="hover:text-primary">{{ $competition->name }}</a>
        <span>›</span>
        <span class="text-gray-800">Ergebnisse importieren</span>
    </div>

    <x-ui.import-steps :current="2" />

    {{-- Datums-Mismatch Warnung --}}
    @if($mismatch)
        <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-700 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-amber-800">{{ $mismatch }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.competitions.results-import.execute', $competition) }}" class="space-y-6">
        @csrf

        {{-- Nur der erste Wettkampf der Datei: Vereine und Zuordnungen unten gehoeren zu ihm.
             Vorher liess sich ein anderer waehlen, der dann mit diesen Zuordnungen gespeichert wurde. --}}
        <input type="hidden" name="meet_index" value="0">
        @if(count($parsed['meets']) > 1)
            <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                Die Datei enthält {{ count($parsed['meets']) }} Wettkämpfe. Übernommen wird der erste:
                <strong>{{ $meet['name'] }}</strong> ({{ $meet['startdate'] }}, {{ $meet['city'] }}).
                Für einen der anderen bitte eine Datei nur mit diesem Wettkampf verwenden.
            </div>
        @endif

        {{-- Wettkampf-Info --}}
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-800">
            Ergebnisse werden dem Wettkampf <strong>{{ $competition->name }}</strong>
            ({{ $competition->date->format('d.m.Y') }}) zugeordnet.
        </div>

        {{-- Vereinsauswahl --}}
        @if($clubCount > 1)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-800 text-sm">Vereine in der Datei</h3>
                <button type="button" @click="toggleAll()"
                        class="text-xs text-primary hover:underline"
                        x-text="allSelected() ? 'Alle ausblenden' : 'Alle einblenden'"></button>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($allClubs as $ci => $club)
                    @php
                        $indivCount  = collect($club['athletes'])->where('is_relay', false)->count();
                        $relayCount  = collect($club['athletes'])->where('is_relay', true)->count();
                        $matchedCount = collect($club['athletes'])->where('is_relay', false)->filter(fn($a) => $a['matched_user_id'])->count();
                    @endphp
                    <label class="flex items-center gap-1.5 cursor-pointer px-3 py-1.5 rounded-lg border text-sm transition-colors"
                           :class="visibleClubs.includes({{ $ci }})
                               ? 'bg-blue-50 border-blue-300 text-blue-800'
                               : 'bg-gray-50 border-gray-200 text-gray-500'">
                        <input type="checkbox" class="hidden"
                               :checked="visibleClubs.includes({{ $ci }})"
                               @change="toggleClub({{ $ci }})">
                        <span class="w-3 h-3 rounded border flex-shrink-0 flex items-center justify-center"
                              :class="visibleClubs.includes({{ $ci }}) ? 'bg-blue-500 border-blue-500' : 'border-gray-400'">
                            <svg x-show="visibleClubs.includes({{ $ci }})" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        <span class="font-medium">{{ $club['name'] }}</span>
                        <span class="text-xs opacity-70">
                            {{ $matchedCount }}/{{ $indivCount }}
                            @if($relayCount > 0) · {{ $relayCount }} Staffel @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Athleten-Zuordnung je Verein --}}
        @foreach($allClubs as $ci => $club)
        <div x-show="visibleClubs.includes({{ $ci }})" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

            {{-- Vereinskopf --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-semibold text-gray-800">{{ $club['name'] }}</h3>
                <div class="text-right">
                    @php
                        $indivAthletes = collect($club['athletes'])->where('is_relay', false);
                        $relayEntries  = collect($club['athletes'])->where('is_relay', true);
                        $matched       = $indivAthletes->filter(fn($a) => $a['matched_user_id'])->count();
                        $totalIndiv    = $indivAthletes->count();
                        $totalResults  = $indivAthletes->sum(fn($a) => count($a['results']));
                    @endphp
                    <p class="text-sm font-semibold text-gray-700">{{ $matched }}/{{ $totalIndiv }} erkannt</p>
                    <p class="text-xs text-gray-600">
                        {{ $totalResults }} Einzelergebnisse
                        @if($relayEntries->count() > 0)
                            · {{ $relayEntries->count() }} Staffel
                        @endif
                    </p>
                </div>
            </div>

            <x-ui.table :card="false" caption="Ergebnisse zur Übernahme"
            :columns="['Athlet in Datei', 'Ergebnisse', 'Portal-Zuordnung']">
                    @foreach($club['athletes'] as $ai => $athlete)
                        @if($athlete['is_relay'] ?? false)
                            {{-- Staffel-Eintrag (kein Dropdown, nur Info) --}}
                            <tr class="bg-purple-50/40">
                                <x-ui.td>
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded">Staffel</span>
                                        <span class="font-medium text-gray-700">{{ $athlete['firstname'] }}</span>
                                    </div>
                                    @if(!empty($athlete['relay_members']))
                                        <div class="mt-1 ml-1 space-y-0.5">
                                            @foreach($athlete['relay_members'] as $member)
                                                <p class="text-xs text-gray-600">
                                                    {{ $member['firstname'] }} {{ $member['lastname'] }}
                                                    @if($member['birthyear']) ({{ $member['birthyear'] }}) @endif
                                                    @if($member['splittime']) <span class="font-mono">{{ $member['splittime'] }}</span> @endif
                                                </p>
                                            @endforeach
                                        </div>
                                    @endif
                                </x-ui.td>
                                <x-ui.td>
                                    <div class="space-y-0.5">
                                        @foreach($athlete['results'] as $r)
                                            <p class="text-xs text-gray-500">
                                                {{ $r['distance'] }} m
                                                {{ $r['discipline'] }}
                                                <span class="font-mono text-purple-600 font-medium">{{ $r['swimtime'] }}</span>
                                                @if($r['place'] ?? null) <span class="text-gray-600">Pl.&nbsp;{{ $r['place'] }}</span> @endif
                                            </p>
                                        @endforeach
                                    </div>
                                </x-ui.td>
                                <x-ui.td>
                                    <p class="text-xs text-purple-600 font-medium">Staffelergebnis – wird importiert</p>
                                </x-ui.td>
                            </tr>
                        @else
                            {{-- Einzelathlet --}}
                            <tr class="hover:bg-gray-50/50">
                                <x-ui.td>
                                    <p class="font-medium text-gray-800">
                                        {{ $athlete['firstname'] }}
                                        <span class="font-semibold">{{ $athlete['lastname'] }}</span>
                                    </p>
                                    @if($athlete['birthdate'])
                                        <p class="text-xs text-gray-600 mt-0.5">
                                            Jg. {{ $athlete['birthdate'] }}
                                            @if(in_array($athlete['gender'], \App\Support\Gender::PERSON, true)) · {{ \App\Support\Gender::short($athlete['gender']) }} @endif
                                        </p>
                                    @endif
                                </x-ui.td>
                                <x-ui.td>
                                    <div class="space-y-1">
                                        @foreach($athlete['results'] as $r)
                                            <div class="text-xs text-gray-500">
                                                <span>{{ $r['distance'] }} m {{ $r['discipline'] }}</span>
                                                @if($r['round_type'] ?? '') <span class="text-gray-600">{{ ['V'=>'VL','F'=>'Fin','E'=>'E','Z'=>'ZL'][$r['round_type']] ?? $r['round_type'] }}</span> @endif
                                                <span class="font-mono text-primary font-medium">{{ $r['swimtime'] }}</span>
                                                @if($r['place'] ?? null) <span class="text-gray-600">Pl.&nbsp;{{ $r['place'] }}</span> @endif
                                                @if(!empty($r['wertungen']))
                                                    <span class="ml-1 inline-flex flex-wrap gap-0.5">
                                                        @foreach($r['wertungen'] as $w)
                                                            <span class="px-1 py-0.5 bg-indigo-50 text-indigo-600 rounded text-xs">{{ $w }}</span>
                                                        @endforeach
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </x-ui.td>
                                <x-ui.td>
                                    @if($athlete['matched_user_id'])
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 bg-green-400 rounded-full flex-shrink-0"></span>
                                            <select name="mappings[{{ $ci }}][{{ $ai }}]" aria-label="Portal-Schwimmer für {{ $athlete['firstname'] }} {{ $athlete['lastname'] }}"
                                                    class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                                <option value="0">– überspringen –</option>
                                                @foreach($swimmers as $sw)
                                                    <option value="{{ $sw->id }}"
                                                            {{ $athlete['matched_user_id'] === $sw->id ? 'selected' : '' }}>
                                                        {{ $sw->firstname }} {{ $sw->lastname }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <p class="text-xs text-green-700 mt-0.5 ml-4">Automatisch erkannt</p>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 bg-amber-400 rounded-full flex-shrink-0"></span>
                                            <select name="mappings[{{ $ci }}][{{ $ai }}]" aria-label="Portal-Schwimmer für {{ $athlete['firstname'] }} {{ $athlete['lastname'] }}"
                                                    class="flex-1 px-3 py-1.5 border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-amber-50">
                                                <option value="0">– überspringen –</option>
                                                @foreach($swimmers as $sw)
                                                    <option value="{{ $sw->id }}">{{ $sw->firstname }} {{ $sw->lastname }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <p class="text-xs text-amber-700 mt-0.5 ml-4">Bitte manuell zuordnen</p>
                                    @endif
                                </x-ui.td>
                            </tr>
                        @endif
                    @endforeach
                </x-ui.table>
        </div>
        @endforeach

        {{-- Aktions-Leiste --}}
        <x-ui.import-bar :cancel="route('admin.competitions.show', $competition)"
                         count="select[name^='mappings'] option:checked:not([value='0'])"
                         singular="zugeordneten Schwimmer" plural="zugeordnete Schwimmer">
                @php
                    $totalAthletes = collect($allClubs)->sum(fn($c) => collect($c['athletes'])->where('is_relay', false)->count());
                    $autoMatched   = collect($allClubs)->sum(fn($c) =>
                        collect($c['athletes'])->where('is_relay', false)->filter(fn($a) => $a['matched_user_id'])->count()
                    );
                    $totalResults  = collect($allClubs)->sum(fn($c) =>
                        collect($c['athletes'])->where('is_relay', false)->sum(fn($a) => count($a['results']))
                    );
                    $totalRelay    = collect($allClubs)->sum(fn($c) =>
                        collect($c['athletes'])->where('is_relay', true)->count()
                    );
                @endphp
                <strong>{{ $autoMatched }}/{{ $totalAthletes }}</strong> Athleten automatisch erkannt ·
                <strong>{{ $totalResults }}</strong> Einzelergebnisse bereit
                @if($totalRelay > 0)
                    · <strong>{{ $totalRelay }}</strong> Staffelergebnis{{ $totalRelay !== 1 ? 'se' : '' }} (werden importiert)
                @endif
        </x-ui.import-bar>

    </form>
</div>
@endsection

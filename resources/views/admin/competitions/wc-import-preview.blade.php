@extends('layouts.app')
@section('title', 'WebClub CSV – Import-Vorschau')
@section('page-title', 'WebClub CSV – Ergebnisse importieren')

@section('content')
@php
    $meet     = $parsed['meets'][0];
    $allClubs = $meet['clubs'];
@endphp

<div class="mt-2 space-y-6">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.competitions.index') }}" class="hover:text-primary">Wettkämpfe</a>
        <span>›</span>
        <a href="{{ route('admin.competitions.show', $competition) }}" class="hover:text-primary">{{ $competition->name }}</a>
        <span>›</span>
        <span class="text-gray-800">WebClub CSV Import</span>
    </div>

    {{-- Datums-Warnung --}}
    @if($mismatch)
        <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-amber-800">{{ $mismatch }}</p>
        </div>
    @endif

    {{-- Datei-Metadaten --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-800">
        <strong>{{ $meet['name'] }}</strong> ·
        {{ \Carbon\Carbon::parse($meet['startdate'])->format('d.m.Y') }}
        @if($meet['enddate'] !== $meet['startdate']) – {{ \Carbon\Carbon::parse($meet['enddate'])->format('d.m.Y') }} @endif
        · {{ $meet['city'] }} · {{ $meet['course'] }}
        <br>
        <span class="text-blue-600 text-xs">Ergebnisse werden dem Wettkampf <strong>{{ $competition->name }}</strong> ({{ $competition->date->format('d.m.Y') }}) zugeordnet.</span>
    </div>

    <form method="POST" action="{{ route('admin.competitions.wc-import.execute', $competition) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="meet_index" value="0">

        @foreach($allClubs as $ci => $club)
        @php
            $indivAthletes = collect($club['athletes'])->where('is_relay', false);
            $relayEntries  = collect($club['athletes'])->where('is_relay', true);
            $matched       = $indivAthletes->filter(fn($a) => $a['matched_user_id'])->count();
            $totalIndiv    = $indivAthletes->count();
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
                <div>
                    <h3 class="font-semibold text-gray-800">Athleten ({{ $totalIndiv }})</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Automatisch erkannt über Namensabgleich mit Portal-Schwimmern
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-700">{{ $matched }}/{{ $totalIndiv }} erkannt</p>
                    @if($relayEntries->count() > 0)
                        <p class="text-xs text-gray-400">+ {{ $relayEntries->count() }} Staffel{{ $relayEntries->count() !== 1 ? 'n' : '' }}</p>
                    @endif
                </div>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-gray-50/50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 w-56">Athlet in Datei</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Ergebnisse</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 w-64">Portal-Zuordnung</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">

                    {{-- Individual athletes --}}
                    @foreach($club['athletes'] as $ai => $athlete)
                        @if($athlete['is_relay'] ?? false)
                            {{-- Staffel --}}
                            <tr class="bg-purple-50/40">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded">Staffel</span>
                                        <span class="font-medium text-gray-700 text-sm">{{ $athlete['firstname'] }}</span>
                                    </div>
                                    @if(!empty($athlete['relay_members']))
                                        <div class="mt-1 space-y-0.5">
                                            @foreach($athlete['relay_members'] as $m)
                                                <p class="text-xs text-gray-400">
                                                    {{ $m['leg'] }}. {{ $m['firstname'] }} {{ $m['lastname'] }}
                                                    @if($m['birthyear']) ({{ $m['birthyear'] }}) @endif
                                                </p>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @foreach($athlete['results'] as $r)
                                        <p class="text-xs text-gray-500">
                                            {{ $r['distance'] }} m {{ $r['discipline'] }}
                                            <span class="font-mono text-purple-600 font-medium">{{ $r['swimtime'] }}</span>
                                            @if($r['place'] ?? null) <span class="text-gray-400">Pl.&nbsp;{{ $r['place'] }}</span> @endif
                                        </p>
                                    @endforeach
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-xs text-purple-600 font-medium">Staffelergebnis – wird importiert</p>
                                </td>
                            </tr>
                        @else
                            {{-- Individual --}}
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-800 text-sm">
                                        {{ $athlete['firstname'] }}
                                        <span class="font-semibold">{{ $athlete['lastname'] }}</span>
                                    </p>
                                    @if($athlete['birthdate'])
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            Jg. {{ $athlete['birthdate'] }}
                                            @if($athlete['gender'] === 'F') · w @elseif($athlete['gender'] === 'M') · m @endif
                                        </p>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="space-y-0.5">
                                        @foreach($athlete['results'] as $r)
                                            <p class="text-xs text-gray-600">
                                                {{ $r['distance'] }} m {{ $r['discipline'] }}
                                                @if($r['round_type']) <span class="text-gray-400">{{ ['V'=>'VL','F'=>'Fin','E'=>'Ent','Z'=>'ZL'][$r['round_type']] ?? $r['round_type'] }}</span> @endif
                                                <span class="font-mono text-primary font-medium">{{ $r['swimtime'] }}</span>
                                                @if($r['place'] ?? null) <span class="text-gray-400">Pl.&nbsp;{{ $r['place'] }}</span> @endif
                                                {{-- PBZ/SBZ/SR badges --}}
                                                @if(!empty($r['rek']))
                                                    @php $rek = $r['rek']; @endphp
                                                    @if($rek === 'PBZ')
                                                        <span class="ml-1 px-1 py-0.5 bg-green-100 text-green-700 rounded text-xs font-semibold">PBZ</span>
                                                    @elseif($rek === 'SBZ')
                                                        <span class="ml-1 px-1 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-semibold">SBZ</span>
                                                    @elseif(str_contains($rek, 'SR') || str_contains($rek, 'VR'))
                                                        <span class="ml-1 px-1 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-semibold">{{ $rek }}</span>
                                                    @elseif(str_contains($rek, 'LR'))
                                                        <span class="ml-1 px-1 py-0.5 bg-red-100 text-red-700 rounded text-xs font-semibold">LR</span>
                                                    @else
                                                        <span class="ml-1 text-xs text-gray-400">{{ $rek }}</span>
                                                    @endif
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if($athlete['matched_user_id'])
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 bg-green-400 rounded-full flex-shrink-0"></span>
                                            <select name="mappings[{{ $ci }}][{{ $ai }}]"
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
                                        <p class="text-xs text-green-600 mt-0.5 ml-4">Automatisch erkannt</p>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 bg-amber-400 rounded-full flex-shrink-0"></span>
                                            <select name="mappings[{{ $ci }}][{{ $ai }}]"
                                                    class="flex-1 px-3 py-1.5 border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-amber-50">
                                                <option value="0">– überspringen –</option>
                                                @foreach($swimmers as $sw)
                                                    <option value="{{ $sw->id }}">{{ $sw->firstname }} {{ $sw->lastname }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <p class="text-xs text-amber-600 mt-0.5 ml-4">Nicht erkannt – manuell zuordnen</p>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach

        {{-- Legende --}}
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 text-xs text-gray-500 flex flex-wrap gap-4">
            <span class="font-semibold text-gray-600">Rek-Kennzeichnung:</span>
            <span><span class="px-1 py-0.5 bg-green-100 text-green-700 rounded font-semibold">PBZ</span> = Persönliche Bestzeit → <code>is_personal_best</code></span>
            <span><span class="px-1 py-0.5 bg-blue-100 text-blue-700 rounded font-semibold">SBZ</span> = Saisonbestzeit → <code>is_season_best</code></span>
            <span><span class="px-1 py-0.5 bg-amber-100 text-amber-700 rounded font-semibold">SR/VR</span> = Vereinsrekord → <code>breaks_vereinsrekord</code></span>
            <span><span class="px-1 py-0.5 bg-red-100 text-red-700 rounded font-semibold">LR</span> = Landesrekord → <code>breaks_landesrekord</code></span>
        </div>

        {{-- Aktions-Leiste --}}
        @php
            $totalIndivAll = collect($allClubs)->sum(fn($c) => collect($c['athletes'])->where('is_relay', false)->count());
            $autoMatchedAll = collect($allClubs)->sum(fn($c) =>
                collect($c['athletes'])->where('is_relay', false)->filter(fn($a) => $a['matched_user_id'])->count()
            );
            $totalResultsAll = collect($allClubs)->sum(fn($c) =>
                collect($c['athletes'])->where('is_relay', false)->sum(fn($a) => count($a['results']))
            );
            $totalRelayAll = collect($allClubs)->sum(fn($c) =>
                collect($c['athletes'])->where('is_relay', true)->count()
            );
            $pbzCount = collect($allClubs)->sum(fn($c) =>
                collect($c['athletes'])->where('is_relay', false)->sum(fn($a) =>
                    collect($a['results'])->where('is_personal_best', true)->count()
                )
            );
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-wrap items-center justify-between gap-4">
            <div class="text-sm text-gray-500">
                <strong>{{ $autoMatchedAll }}/{{ $totalIndivAll }}</strong> Athleten erkannt ·
                <strong>{{ $totalResultsAll }}</strong> Einzelergebnisse
                @if($pbzCount > 0) · <strong class="text-green-700">{{ $pbzCount }} PBZ</strong> @endif
                @if($totalRelayAll > 0) · <strong>{{ $totalRelayAll }}</strong> Staffel{{ $totalRelayAll !== 1 ? 'n' : '' }} @endif
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.competitions.show', $competition) }}"
                   class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </a>
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Ergebnisse importieren
                </button>
            </div>
        </div>

    </form>
</div>
@endsection

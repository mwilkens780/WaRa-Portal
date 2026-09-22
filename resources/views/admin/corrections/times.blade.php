@extends('layouts.app')
@section('title', 'Korrektur: Unmögliche Zeiten')
@section('page-title', 'Korrektur: Unmögliche Zeiten')

@php
    use App\Models\SwimmingTime;
    use App\Services\TimePlausibility;
    $discLabels = ['F' => 'Freistil', 'R' => 'Rücken', 'B' => 'Brust', 'S' => 'Schmetterling', 'L' => 'Lagen'];
    $total = $results->count() + $records->count() + $entries->count();
@endphp

@section('content')
<div class="mt-2 space-y-5" x-data="{ chosen: {} }">

    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zu den Einstellungen
    </a>

    @if(session('error'))
        <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-sm text-gray-600 space-y-2">
        <p>
            Hier steht, was über seine Strecke nicht möglich ist – nicht, was langsam ist. Eine 50-m-Zeit, die als
            200-m-Ergebnis gespeichert wurde, ist keine schnelle 200 m, sondern ein Datenfehler. Solche Zeilen
            wandern sonst unbemerkt in Vereinsrekorde und Bestenlisten.
        </p>
        <p>
            Die Schranke ist eine Mindestzeit je 100 m und Lage und liegt unter jeder Weltrekord-Geschwindigkeit,
            damit nie eine echte Zeit hier auftaucht:
            @foreach(TimePlausibility::MIN_MS_PER_100M as $disc => $ms)
                <span class="inline-block mr-3 whitespace-nowrap">
                    <strong>{{ $discLabels[$disc] ?? $disc }}</strong> {{ SwimmingTime::formatMs($ms) }}
                </span>
            @endforeach
            je 100 m.
        </p>
        <p class="text-gray-500">
            Der Import speichert solche Zeiten inzwischen gar nicht mehr. Diese Seite ist für den Altbestand
            und für von Hand gepflegte Einträge.
        </p>
    </div>

    @if($total === 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Keine unmöglichen Zeiten gefunden.
        </div>
    @else

    <form method="POST" action="{{ route('admin.corrections.times.destroy') }}"
          @submit="if (!confirm(`${Object.values(chosen).filter(Boolean).length} Einträge endgültig löschen und danach Rekorde und Bestenlisten neu berechnen?`)) $event.preventDefault()">
        @csrf @method('DELETE')

        {{-- Wettkampfergebnisse --}}
        @if($results->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3">
                <h2 class="text-sm font-semibold text-gray-800">Wettkampfergebnisse</h2>
                <span class="text-xs text-gray-400">{{ $results->count() }}</span>
                <button type="button" class="ml-auto text-xs text-gray-500 hover:text-primary"
                        @click="@foreach($results as $r) chosen['r{{ $r->id }}'] = true; @endforeach">alle auswählen</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm table-fixed">
                    <colgroup>
                        <col class="w-10"><col class="w-28"><col class="w-64"><col class="w-48">
                        <col class="w-32"><col class="w-28"><col class="w-32"><col>
                    </colgroup>
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-3 py-2.5"></th>
                            <th class="px-3 py-2.5 text-left font-medium">Datum</th>
                            <th class="px-3 py-2.5 text-left font-medium">Wettkampf</th>
                            <th class="px-3 py-2.5 text-left font-medium">Schwimmer</th>
                            <th class="px-3 py-2.5 text-left font-medium">Strecke</th>
                            <th class="px-3 py-2.5 text-right font-medium">Zeit</th>
                            <th class="px-3 py-2.5 text-right font-medium">möglich ab</th>
                            <th class="px-3 py-2.5 text-left font-medium">Herkunft</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($results as $r)
                            <tr class="hover:bg-gray-50" :class="chosen['r{{ $r->id }}'] ? 'bg-red-50/40' : ''">
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="results[]" value="{{ $r->id }}"
                                           x-model="chosen['r{{ $r->id }}']" class="rounded text-primary">
                                </td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $r->competition?->date?->format('d.m.Y') ?? '–' }}</td>
                                <td class="px-3 py-2 truncate" title="{{ $r->competition?->name }}">
                                    @if($r->competition)
                                        <a href="{{ route('admin.competitions.show', $r->competition) }}" target="_blank"
                                           class="text-gray-800 hover:text-primary hover:underline">{{ $r->competition->name }}</a>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-700 truncate">{{ $r->user?->name ?? '–' }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    {{ $r->distance }} m {{ $discLabels[$r->discipline] ?? $r->discipline }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-mono font-bold text-red-600">
                                    {{ SwimmingTime::formatMs($r->time_ms) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-mono text-gray-400">
                                    {{ SwimmingTime::formatMs(TimePlausibility::minTimeMs($r->discipline, $r->distance)) }}
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500 truncate"
                                    title="{{ $r->notes }}">
                                    @if($r->relay_leadoff)
                                        <span class="text-amber-700">Staffel-Startabschnitt</span>
                                    @else
                                        {{ $r->source ?? 'manuell' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Rekorde --}}
        @if($records->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3">
                <h2 class="text-sm font-semibold text-gray-800">Rekorde</h2>
                <span class="text-xs text-gray-400">{{ $records->count() }}</span>
                <button type="button" class="ml-auto text-xs text-gray-500 hover:text-primary"
                        @click="@foreach($records as $rec) chosen['k{{ $rec->id }}'] = true; @endforeach">alle auswählen</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm table-fixed">
                    <colgroup>
                        <col class="w-10"><col class="w-32"><col class="w-64"><col class="w-32">
                        <col class="w-24"><col class="w-28"><col class="w-32"><col>
                    </colgroup>
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-3 py-2.5"></th>
                            <th class="px-3 py-2.5 text-left font-medium">Art</th>
                            <th class="px-3 py-2.5 text-left font-medium">Name</th>
                            <th class="px-3 py-2.5 text-left font-medium">Strecke</th>
                            <th class="px-3 py-2.5 text-left font-medium">Bahn</th>
                            <th class="px-3 py-2.5 text-right font-medium">Zeit</th>
                            <th class="px-3 py-2.5 text-right font-medium">möglich ab</th>
                            <th class="px-3 py-2.5 text-left font-medium">Datum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($records as $rec)
                            <tr class="hover:bg-gray-50" :class="chosen['k{{ $rec->id }}'] ? 'bg-red-50/40' : ''">
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="records[]" value="{{ $rec->id }}"
                                           x-model="chosen['k{{ $rec->id }}']" class="rounded text-primary">
                                </td>
                                <td class="px-3 py-2 text-gray-600 text-xs">
                                    {{ $rec->type === 'vereinsrekord' ? 'Vereinsrekord' : 'Landesrekord' }}
                                </td>
                                <td class="px-3 py-2 text-gray-700 truncate">{{ $rec->swimmer_name }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    {{ $rec->distance }} m {{ $discLabels[$rec->discipline] ?? $rec->discipline }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $rec->course }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-mono font-bold text-red-600">
                                    {{ SwimmingTime::formatMs($rec->time_ms) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-mono text-gray-400">
                                    {{ SwimmingTime::formatMs(TimePlausibility::minTimeMs($rec->discipline, $rec->distance)) }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $rec->set_date?->format('d.m.Y') ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Bestenlisten-Eintraege --}}
        @if($entries->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3">
                <h2 class="text-sm font-semibold text-gray-800">Bestenlisten-Einträge (historisch / von Hand)</h2>
                <span class="text-xs text-gray-400">{{ $entries->count() }}</span>
                <button type="button" class="ml-auto text-xs text-gray-500 hover:text-primary"
                        @click="@foreach($entries as $e) chosen['e{{ $e->id }}'] = true; @endforeach">alle auswählen</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm table-fixed">
                    <colgroup>
                        <col class="w-10"><col class="w-64"><col class="w-32"><col class="w-24">
                        <col class="w-28"><col class="w-32"><col class="w-24"><col>
                    </colgroup>
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-3 py-2.5"></th>
                            <th class="px-3 py-2.5 text-left font-medium">Name</th>
                            <th class="px-3 py-2.5 text-left font-medium">Strecke</th>
                            <th class="px-3 py-2.5 text-left font-medium">Bahn</th>
                            <th class="px-3 py-2.5 text-right font-medium">Zeit</th>
                            <th class="px-3 py-2.5 text-right font-medium">möglich ab</th>
                            <th class="px-3 py-2.5 text-left font-medium">Jahr</th>
                            <th class="px-3 py-2.5 text-left font-medium">Veranstaltung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($entries as $e)
                            <tr class="hover:bg-gray-50" :class="chosen['e{{ $e->id }}'] ? 'bg-red-50/40' : ''">
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="entries[]" value="{{ $e->id }}"
                                           x-model="chosen['e{{ $e->id }}']" class="rounded text-primary">
                                </td>
                                <td class="px-3 py-2 text-gray-700 truncate">{{ $e->swimmer_name }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    {{ $e->distance }} m {{ $discLabels[$e->discipline] ?? $e->discipline }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $e->course }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-mono font-bold text-red-600">
                                    {{ SwimmingTime::formatMs($e->time_ms) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-mono text-gray-400">
                                    {{ SwimmingTime::formatMs(TimePlausibility::minTimeMs($e->discipline, $e->distance)) }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $e->set_year ?? '–' }}</td>
                                <td class="px-3 py-2 text-gray-400 text-xs truncate" title="{{ $e->location }}">{{ $e->location ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="flex items-center gap-4">
            <button type="submit" :disabled="Object.values(chosen).filter(Boolean).length === 0"
                    class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                Ausgewählte löschen und neu berechnen
            </button>
            <span class="text-sm text-gray-500">
                <strong x-text="Object.values(chosen).filter(Boolean).length"></strong> von {{ $total }} ausgewählt
            </span>
        </div>
    </form>
    @endif
</div>
@endsection

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
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-600">
            Keine unmöglichen Zeiten gefunden.
        </div>
    @else

    <form method="POST" action="{{ route('admin.corrections.times.destroy') }}"
          @submit.prevent="if (await $confirm({ title: `${Object.values(chosen).filter(Boolean).length} Einträge endgültig löschen?`, text: 'Danach werden Rekorde und Bestenlisten neu berechnet.', confirmLabel: 'Löschen', danger: true })) $el.submit()">
        @csrf @method('DELETE')

        {{-- Wettkampfergebnisse --}}
        @if($results->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3">
                <h2 class="text-sm font-semibold text-gray-800">Wettkampfergebnisse</h2>
                <span class="text-xs text-gray-600">{{ $results->count() }}</span>
                <button type="button" class="ml-auto text-xs text-gray-500 hover:text-primary"
                        @click="@foreach($results as $r) chosen['r{{ $r->id }}'] = true; @endforeach">alle auswählen</button>
            </div>
            <x-ui.table :card="false" caption="Auffällige Zeiten" class="table-fixed"
            :columns="[['label' => 'Aktionen', 'sr' => true, 'class' => 'w-10'], ['label' => 'Datum', 'class' => 'w-28'], ['label' => 'Wettkampf', 'class' => 'w-64'], ['label' => 'Schwimmer', 'class' => 'w-48'], ['label' => 'Strecke', 'class' => 'w-32'], ['label' => 'Zeit', 'align' => 'right', 'class' => 'w-28'], ['label' => 'möglich ab', 'align' => 'right', 'class' => 'w-32'], 'Herkunft']">
                        @foreach($results as $r)
                            <tr class="hover:bg-gray-50" :class="chosen['r{{ $r->id }}'] ? 'bg-red-50/40' : ''">
                                <x-ui.td>
                                    <input type="checkbox" name="results[]" value="{{ $r->id }}"
                                           aria-label="Ergebnis auswählen: {{ $r->competition?->name ?? 'ohne Wettkampf' }}, {{ $r->competition?->date?->format('d.m.Y') }}"
                                           x-model="chosen['r{{ $r->id }}']" class="rounded text-primary">
                                </x-ui.td>
                                <x-ui.td muted class="whitespace-nowrap">{{ $r->competition?->date?->format('d.m.Y') ?? '–' }}</x-ui.td>
                                <x-ui.td class="truncate" title="{{ $r->competition?->name }}">
                                    @if($r->competition)
                                        <a href="{{ route('admin.competitions.show', $r->competition) }}" target="_blank"
                                           class="text-gray-800 hover:text-primary hover:underline">{{ $r->competition->name }}</a>
                                    @else
                                        <span class="text-gray-600">–</span>
                                    @endif
                                </x-ui.td>
                                <x-ui.td class="text-gray-700 truncate">{{ $r->user?->name ?? '–' }}</x-ui.td>
                                <x-ui.td muted>
                                    {{ $r->distance }} m {{ $discLabels[$r->discipline] ?? $r->discipline }}
                                </x-ui.td>
                                <x-ui.td align="right" class="tabular-nums font-mono font-bold text-red-600">
                                    {{ SwimmingTime::formatMs($r->time_ms) }}
                                </x-ui.td>
                                <x-ui.td align="right" muted class="tabular-nums font-mono">
                                    {{ SwimmingTime::formatMs(TimePlausibility::minTimeMs($r->discipline, $r->distance)) }}
                                </x-ui.td>
                                <x-ui.td muted class="text-xs truncate" title="{{ $r->notes }}">
                                    @if($r->relay_leadoff)
                                        <span class="text-amber-700">Staffel-Startabschnitt</span>
                                    @else
                                        {{ $r->source ?? 'manuell' }}
                                    @endif
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
        </div>
        @endif

        {{-- Rekorde --}}
        @if($records->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3">
                <h2 class="text-sm font-semibold text-gray-800">Rekorde</h2>
                <span class="text-xs text-gray-600">{{ $records->count() }}</span>
                <button type="button" class="ml-auto text-xs text-gray-500 hover:text-primary"
                        @click="@foreach($records as $rec) chosen['k{{ $rec->id }}'] = true; @endforeach">alle auswählen</button>
            </div>
            <x-ui.table :card="false" caption="Auffällige Zeiten" class="table-fixed"
            :columns="[['label' => 'Aktionen', 'sr' => true, 'class' => 'w-10'], ['label' => 'Art', 'class' => 'w-32'], ['label' => 'Name', 'class' => 'w-64'], ['label' => 'Strecke', 'class' => 'w-32'], ['label' => 'Bahn', 'class' => 'w-24'], ['label' => 'Zeit', 'align' => 'right', 'class' => 'w-28'], ['label' => 'möglich ab', 'align' => 'right', 'class' => 'w-32'], 'Datum']">
                        @foreach($records as $rec)
                            <tr class="hover:bg-gray-50" :class="chosen['k{{ $rec->id }}'] ? 'bg-red-50/40' : ''">
                                <x-ui.td>
                                    <input type="checkbox" name="records[]" value="{{ $rec->id }}"
                                           aria-label="Rekord auswählen: {{ $rec->swimmer_name }}"
                                           x-model="chosen['k{{ $rec->id }}']" class="rounded text-primary">
                                </x-ui.td>
                                <x-ui.td muted class="text-xs">
                                    {{ $rec->type === 'vereinsrekord' ? 'Vereinsrekord' : 'Landesrekord' }}
                                </x-ui.td>
                                <x-ui.td class="text-gray-700 truncate">{{ $rec->swimmer_name }}</x-ui.td>
                                <x-ui.td muted>
                                    {{ $rec->distance }} m {{ $discLabels[$rec->discipline] ?? $rec->discipline }}
                                </x-ui.td>
                                <x-ui.td muted class="text-xs">{{ $rec->course }}</x-ui.td>
                                <x-ui.td align="right" class="tabular-nums font-mono font-bold text-red-600">
                                    {{ SwimmingTime::formatMs($rec->time_ms) }}
                                </x-ui.td>
                                <x-ui.td align="right" muted class="tabular-nums font-mono">
                                    {{ SwimmingTime::formatMs(TimePlausibility::minTimeMs($rec->discipline, $rec->distance)) }}
                                </x-ui.td>
                                <x-ui.td muted class="text-xs">{{ $rec->set_date?->format('d.m.Y') ?? '–' }}</x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
        </div>
        @endif

        {{-- Bestenlisten-Eintraege --}}
        @if($entries->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3">
                <h2 class="text-sm font-semibold text-gray-800">Bestenlisten-Einträge (historisch / von Hand)</h2>
                <span class="text-xs text-gray-600">{{ $entries->count() }}</span>
                <button type="button" class="ml-auto text-xs text-gray-500 hover:text-primary"
                        @click="@foreach($entries as $e) chosen['e{{ $e->id }}'] = true; @endforeach">alle auswählen</button>
            </div>
            <x-ui.table :card="false" caption="Auffällige Zeiten" class="table-fixed"
            :columns="[['label' => 'Aktionen', 'sr' => true, 'class' => 'w-10'], ['label' => 'Name', 'class' => 'w-64'], ['label' => 'Strecke', 'class' => 'w-32'], ['label' => 'Bahn', 'class' => 'w-24'], ['label' => 'Zeit', 'align' => 'right', 'class' => 'w-28'], ['label' => 'möglich ab', 'align' => 'right', 'class' => 'w-32'], ['label' => 'Jahr', 'class' => 'w-24'], 'Veranstaltung']">
                        @foreach($entries as $e)
                            <tr class="hover:bg-gray-50" :class="chosen['e{{ $e->id }}'] ? 'bg-red-50/40' : ''">
                                <x-ui.td>
                                    <input type="checkbox" name="entries[]" value="{{ $e->id }}"
                                           aria-label="Eintrag auswählen: {{ $e->swimmer_name }}"
                                           x-model="chosen['e{{ $e->id }}']" class="rounded text-primary">
                                </x-ui.td>
                                <x-ui.td class="text-gray-700 truncate">{{ $e->swimmer_name }}</x-ui.td>
                                <x-ui.td muted>
                                    {{ $e->distance }} m {{ $discLabels[$e->discipline] ?? $e->discipline }}
                                </x-ui.td>
                                <x-ui.td muted class="text-xs">{{ $e->course }}</x-ui.td>
                                <x-ui.td align="right" class="tabular-nums font-mono font-bold text-red-600">
                                    {{ SwimmingTime::formatMs($e->time_ms) }}
                                </x-ui.td>
                                <x-ui.td align="right" muted class="tabular-nums font-mono">
                                    {{ SwimmingTime::formatMs(TimePlausibility::minTimeMs($e->discipline, $e->distance)) }}
                                </x-ui.td>
                                <x-ui.td muted class="text-xs">{{ $e->set_year ?? '–' }}</x-ui.td>
                                <x-ui.td muted class="text-xs truncate" title="{{ $e->location }}">{{ $e->location ?? '–' }}</x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
        </div>
        @endif

        <div class="flex items-center gap-4">
            <button type="submit" :disabled="Object.values(chosen).filter(Boolean).length === 0"
                    class="bg-accent hover:bg-accent-dark text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
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

@extends('layouts.app')
@section('title', 'WA Punktetabellen')
@section('page-title', 'WA Punktetabellen')

@section('content')
<div class="space-y-6">

    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zu den Einstellungen
    </a>

    {{-- Filter / Jahr-Auswahl --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Jahr</label>
                <select aria-label="Jahr" name="year" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>WA {{ $y }}</option>
                    @endforeach
                    <option value="{{ date('Y') }}" {{ !$years->contains(date('Y')) ? 'selected' : '' }}>WA {{ date('Y') }} (neu)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Bahnlänge</label>
                <select aria-label="Bahnlänge" name="pool_length" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="50" {{ $poolLength == 50 ? 'selected' : '' }}>Langbahn (50m)</option>
                    <option value="25" {{ $poolLength == 25 ? 'selected' : '' }}>Kurzbahn (25m)</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                Laden
            </button>
        </div>
    </form>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Bulk-Edit Tabelle --}}
    <form method="POST" action="{{ route('admin.wa-scoring.bulk-store') }}">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="pool_length" value="{{ $poolLength }}">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-800">WA {{ $year }} – {{ $poolLength }}m Basiszeiten</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Formel: Punkte = 1000 × (Basiszeit / Schwimmzeit)³ &nbsp;·&nbsp; Format: M:SS,cs oder SS,cs</p>
                </div>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                    Alle speichern
                </button>
            </div>

            @php
                $genders     = ['M' => 'Männer', 'F' => 'Frauen'];
                $disciplineLabels = $disciplines;
                $distancesByDisc = [
                    'F' => [50,100,200,400,800,1500],
                    'B' => [50,100,200],
                    'R' => [50,100,200],
                    'S' => [50,100,200],
                    'L' => [100,200,400],
                ];
            @endphp

            @foreach($genders as $gCode => $gLabel)
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">{{ $gLabel }}</h3>
                </div>
                <x-ui.table :card="false" caption="WA-Basiszeiten">
<x-slot:head>
                                <x-ui.th>Disziplin</x-ui.th>
                                @foreach([50,100,200,400,800,1500] as $d)
                                    <x-ui.th align="center">{{ $d }}m</x-ui.th>
                                @endforeach
                            </x-slot:head>
                            @foreach($disciplineLabels as $dCode => $dLabel)
                                <tr class="hover:bg-gray-50">
                                    <x-ui.td class="font-medium text-gray-700 whitespace-nowrap">{{ $dLabel }}</x-ui.td>
                                    @foreach([50,100,200,400,800,1500] as $dist)
                                        <x-ui.td align="center">
                                            @if(in_array($dist, $distancesByDisc[$dCode] ?? []))
                                                @php $key = "{$gCode}_{$dCode}_{$dist}"; $entry = $entries[$key] ?? null; @endphp
                                                <input type="text"
                                                       name="times[{{ $key }}]"
                                                       value="{{ $entry?->formatted_base_time ?? '' }}"
                                                       placeholder="–"
                                                       class="w-20 text-center px-2 py-1 border border-gray-300 rounded text-xs outline-none focus:ring-2 focus:ring-blue-400 font-mono {{ $entry ? 'bg-green-50 border-green-300' : '' }}">
                                            @else
                                                <span class="text-gray-600">–</span>
                                            @endif
                                        </x-ui.td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </x-ui.table>
            @endforeach

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                    Alle speichern
                </button>
            </div>
        </div>
    </form>

    {{-- Einzeleinträge löschen --}}
    @if($entries->count() > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-800">Gespeicherte Einträge WA {{ $year }} / {{ $poolLength }}m</h2>
            </div>
            <x-ui.table :card="false" caption="WA-Punktetabelle"
            :columns="['Geschlecht', 'Disziplin', ['label' => 'Distanz', 'align' => 'right'], ['label' => 'Basiszeit', 'align' => 'right'], ['label' => 'Aktionen', 'sr' => true]]">
                        @foreach($entries as $entry)
                            <tr class="hover:bg-gray-50">
                                <x-ui.td class="text-gray-700">{{ $entry->gender === 'M' ? 'Männer' : 'Frauen' }}</x-ui.td>
                                <x-ui.td class="text-gray-700">{{ $disciplines[$entry->discipline] ?? $entry->discipline }}</x-ui.td>
                                <x-ui.td align="right" class="text-gray-700">{{ $entry->distance_m }} m</x-ui.td>
                                <x-ui.td align="right" class="font-mono text-gray-900">{{ $entry->formatted_base_time }}</x-ui.td>
                                <x-ui.td align="right">
                                    <form method="POST" action="{{ route('admin.wa-scoring.destroy', $entry) }}"
                                          data-confirm="Basiszeit löschen?" data-confirm-label="Löschen" data-confirm-danger>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-700 text-xs">Löschen</button>
                                    </form>
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
        </div>
    @endif

</div>
@endsection

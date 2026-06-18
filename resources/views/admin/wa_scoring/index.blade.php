@extends('layouts.app')
@section('title', 'WA Punktetabellen')
@section('page-title', 'WA Punktetabellen')

@section('content')
<div class="space-y-6">

    {{-- Filter / Jahr-Auswahl --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Jahr</label>
                <select name="year" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>WA {{ $y }}</option>
                    @endforeach
                    <option value="{{ date('Y') }}" {{ !$years->contains(date('Y')) ? 'selected' : '' }}>WA {{ date('Y') }} (neu)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Bahnlänge</label>
                <select name="pool_length" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="50" {{ $poolLength == 50 ? 'selected' : '' }}>Langbahn (50m)</option>
                    <option value="25" {{ $poolLength == 25 ? 'selected' : '' }}>Kurzbahn (25m)</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                Laden
            </button>
        </div>
    </form>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
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
                    'L' => [200,400],
                ];
            @endphp

            @foreach($genders as $gCode => $gLabel)
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">{{ $gLabel }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold">Disziplin</th>
                                @foreach([50,100,200,400,800,1500] as $d)
                                    <th class="px-3 py-2.5 text-center font-semibold">{{ $d }}m</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($disciplineLabels as $dCode => $dLabel)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">{{ $dLabel }}</td>
                                    @foreach([50,100,200,400,800,1500] as $dist)
                                        <td class="px-3 py-2 text-center">
                                            @if(in_array($dist, $distancesByDisc[$dCode] ?? []))
                                                @php $key = "{$gCode}_{$dCode}_{$dist}"; $entry = $entries[$key] ?? null; @endphp
                                                <input type="text"
                                                       name="times[{{ $key }}]"
                                                       value="{{ $entry?->formatted_base_time ?? '' }}"
                                                       placeholder="–"
                                                       class="w-20 text-center px-2 py-1 border border-gray-300 rounded text-xs outline-none focus:ring-2 focus:ring-blue-400 font-mono {{ $entry ? 'bg-green-50 border-green-300' : '' }}">
                                            @else
                                                <span class="text-gray-300">–</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold">Geschlecht</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Disziplin</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Distanz</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Basiszeit</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($entries as $entry)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-700">{{ $entry->gender === 'M' ? 'Männer' : 'Frauen' }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $disciplines[$entry->discipline] ?? $entry->discipline }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ $entry->distance_m }} m</td>
                                <td class="px-4 py-2 text-right font-mono text-gray-900">{{ $entry->formatted_base_time }}</td>
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('admin.wa-scoring.destroy', $entry) }}"
                                          onsubmit="return confirm('Basiszeit löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection

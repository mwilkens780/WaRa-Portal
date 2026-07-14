@extends('layouts.app')
@section('title', 'Bestenliste importieren – Vorschau')
@section('page-title', 'Bestenliste importieren – Vorschau')

@section('content')
@php
    $disciplineLabels = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
@endphp
<div class="mt-2 space-y-4">

    <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3 rounded-xl">
        <strong>{{ count($rows) }} Einträge erkannt</strong>
        · {{ $type === 'eternal' ? 'Ewige Bestenliste' : 'Jahresbestenliste' . ($year ? " $year" : '') }}
        · {{ $course }}
        <br>
        Rot markierte Zeilen haben fehlende oder fehlerhafte Daten — korrigiere sie oder entferne den Haken.
    </div>

    <form method="POST" action="{{ route('admin.bestlist.import.execute') }}">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-3 py-2.5">
                                <input type="checkbox" id="selectAll" class="rounded"
                                       onclick="document.querySelectorAll('[name$=\'[include]\']').forEach(cb => cb.checked = this.checked)">
                            </th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Disziplin</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Distanz</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Gesch.</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Jahrgang</th>
                            @if($type === 'annual')
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Jahr</th>
                            @endif
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Zeit</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[160px]">Name</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Datum</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Ort</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($rows as $i => $row)
                            @php
                                $hasIssue = !$row['discipline'] || !$row['distance'] || !$row['gender']
                                    || !$row['birth_year'] || $row['time_ms'] <= 0 || !$row['swimmer_name'];
                            @endphp
                            <tr class="{{ $hasIssue ? 'bg-red-50/40' : 'hover:bg-gray-50' }}">
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="rows[{{ $i }}][include]" value="1"
                                           {{ !$hasIssue ? 'checked' : '' }} class="rounded">
                                </td>
                                {{-- Discipline --}}
                                <td class="px-3 py-2">
                                    <select name="rows[{{ $i }}][discipline]"
                                            class="px-2 py-1 border {{ !$row['discipline'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">– wählen –</option>
                                        @foreach($disciplineLabels as $val => $label)
                                            <option value="{{ $val }}" {{ $row['discipline'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                {{-- Distance --}}
                                <td class="px-3 py-2">
                                    <select name="rows[{{ $i }}][distance]"
                                            class="px-2 py-1 border {{ !$row['distance'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">– wählen –</option>
                                        @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                                            <option value="{{ $d }}" {{ (int)$row['distance'] === $d ? 'selected' : '' }}>{{ $d }} m</option>
                                        @endforeach
                                    </select>
                                </td>
                                {{-- Gender --}}
                                <td class="px-3 py-2">
                                    <select name="rows[{{ $i }}][gender]"
                                            class="px-2 py-1 border {{ !$row['gender'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">–</option>
                                        <option value="M" {{ $row['gender'] === 'M' ? 'selected' : '' }}>M</option>
                                        <option value="F" {{ $row['gender'] === 'F' ? 'selected' : '' }}>W</option>
                                    </select>
                                </td>
                                {{-- Birth year --}}
                                <td class="px-3 py-2">
                                    <input type="number" name="rows[{{ $i }}][birth_year]"
                                           value="{{ $row['birth_year'] }}" min="1900" max="{{ now()->year }}"
                                           class="w-20 px-2 py-1 border {{ !$row['birth_year'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </td>
                                @if($type === 'annual')
                                {{-- Set year --}}
                                <td class="px-3 py-2">
                                    <input type="number" name="rows[{{ $i }}][set_year]"
                                           value="{{ $row['set_date'] ? (int) substr($row['set_date'], 0, 4) : $year }}"
                                           min="1900" max="{{ now()->year }}"
                                           class="w-20 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </td>
                                @endif
                                {{-- Time --}}
                                <td class="px-3 py-2">
                                    <input type="hidden" name="rows[{{ $i }}][time_ms]" value="{{ $row['time_ms'] }}">
                                    <span class="font-mono {{ $row['time_ms'] <= 0 ? 'text-red-500' : 'text-primary font-semibold' }}">
                                        {{ $row['time_ms'] > 0 ? $row['time_str'] : '–' }}
                                    </span>
                                </td>
                                {{-- Swimmer name --}}
                                <td class="px-3 py-2">
                                    <input type="text" name="rows[{{ $i }}][swimmer_name]"
                                           value="{{ $row['swimmer_name'] }}"
                                           class="w-full min-w-[140px] px-2 py-1 border {{ !$row['swimmer_name'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </td>
                                {{-- Date --}}
                                <td class="px-3 py-2">
                                    <input type="date" name="rows[{{ $i }}][set_date]"
                                           value="{{ $row['set_date'] ?? '' }}"
                                           class="px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </td>
                                {{-- Location --}}
                                <td class="px-3 py-2">
                                    <input type="text" name="rows[{{ $i }}][location]"
                                           value="{{ $row['location'] ?? '' }}"
                                           placeholder="Ort / Wettkampf"
                                           class="w-32 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-3 mt-2">
            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Markierte Einträge importieren
            </button>
            <a href="{{ route('admin.records.index', ['tab' => $type === 'eternal' ? 'eternal' : 'annual']) }}"
               class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                Abbrechen
            </a>
        </div>
    </form>

    {{-- Template-Hinweis --}}
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs text-gray-500 space-y-1">
        <p class="font-medium text-gray-700">Erwartetes Tabellenformat (Spaltenüberschriften werden automatisch erkannt):</p>
        <p class="font-mono">Disziplin | Distanz | Geschlecht | Jahrgang | Name | Zeit | Datum | Ort</p>
        <p>Disziplin: F / Freistil, B / Brust, R / Rücken, S / Schmetterling, L / Lagen</p>
        <p>Geschlecht: M / Männlich oder W / F / Weiblich</p>
        <p>Zeit: z.B. <code class="bg-gray-100 px-1 rounded">1:02,34</code> oder <code class="bg-gray-100 px-1 rounded">58,23</code></p>
    </div>

</div>
@endsection

@extends('layouts.app')
@section('title', 'Rekorde importieren – Vorschau')
@section('page-title', 'Rekorde importieren – Vorschau')

@section('content')
<div class="mt-2 space-y-4">
    @php
        $unvollstaendig = collect($rows)->filter(fn($r) => !$r['discipline'] || !$r['distance'] || !$r['gender'] || $r['time_ms'] <= 0)->count();
    @endphp
    <x-ui.import-steps :current="2" />

    <x-ui.import-summary :items="[
        ['label' => 'Zeilen erkannt', 'count' => count($rows), 'tone' => 'neutral', 'hint' => $type === 'vereinsrekord' ? 'Vereinsrekorde' : 'Landesrekorde'],
        ['label' => 'vollständig', 'count' => count($rows) - $unvollstaendig, 'tone' => 'success', 'hint' => 'vorausgewählt'],
        ['label' => 'zu prüfen', 'count' => $unvollstaendig, 'tone' => 'warning', 'hint' => 'rot markiert, nicht ausgewählt'],
    ]" />

    <p class="text-sm text-gray-700">
        Bahnlänge und Klasse kommen je Zeile aus der Blockstruktur der Datei. Fehlerhafte Erkennungen hier korrigieren –
        nur ausgewählte Zeilen werden übernommen.
    </p>

    <form method="POST" action="{{ route('admin.records.import.execute') }}">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <x-ui.table :card="false" caption="Rekorde zur Übernahme">
<x-slot:head>
                            <x-ui.th>
                                <input type="checkbox" id="selectAll" class="rounded" aria-label="Alle Zeilen auswählen"
                                       {{-- Vorher \" im Attribut: HTML kennt das nicht, der Handler brach ab --}}
                                       onclick="document.querySelectorAll('input[type=checkbox][name$=\'[include]\']').forEach(cb => cb.checked = this.checked)">
                            </x-ui.th>
                            <x-ui.th>Disziplin</x-ui.th>
                            <x-ui.th>Distanz</x-ui.th>
                            <x-ui.th>Geschl.</x-ui.th>
                            <x-ui.th>Altersklasse</x-ui.th>
                            <x-ui.th>Bahn</x-ui.th>
                            <x-ui.th>Zeit</x-ui.th>
                            <x-ui.th class="min-w-[160px]">Name</x-ui.th>
                            <x-ui.th>Datum / Jahr</x-ui.th>
                            <x-ui.th>Ort</x-ui.th>
                        </x-slot:head>
                        @foreach($rows as $i => $row)
                            @php
                                $hasIssue = !$row['discipline'] || !$row['distance'] || !$row['gender'] || $row['time_ms'] <= 0;
                            @endphp
                            <tr class="{{ $hasIssue ? 'bg-red-50/40' : 'hover:bg-gray-50' }}">
                                <x-ui.td>
                                    <input type="checkbox" name="rows[{{ $i }}][include]" value="1"
                                           aria-label="Zeile {{ $i + 1 }} übernehmen{{ $row['swimmer_name'] ? ': ' . $row['swimmer_name'] : '' }}"
                                           {{ !$hasIssue ? 'checked' : '' }} class="rounded">
                                </x-ui.td>
                                {{-- Discipline --}}
                                <x-ui.td>
                                    <select name="rows[{{ $i }}][discipline]" aria-label="Disziplin, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border {{ !$row['discipline'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">– wählen –</option>
                                        @foreach(['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'] as $val => $label)
                                            <option value="{{ $val }}" {{ $row['discipline'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </x-ui.td>
                                {{-- Distance --}}
                                <x-ui.td>
                                    <select name="rows[{{ $i }}][distance]" aria-label="Distanz, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border {{ !$row['distance'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">– wählen –</option>
                                        @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                                            <option value="{{ $d }}" {{ (int)$row['distance'] === $d ? 'selected' : '' }}>{{ $d }} m</option>
                                        @endforeach
                                    </select>
                                </x-ui.td>
                                {{-- Gender --}}
                                <x-ui.td>
                                    <select name="rows[{{ $i }}][gender]" aria-label="Geschlecht, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border {{ !$row['gender'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">–</option>
                                        <option value="M" {{ $row['gender'] === 'M' ? 'selected' : '' }}>M</option>
                                        <option value="F" {{ $row['gender'] === 'F' ? 'selected' : '' }}>W</option>
                                        <option value="D" {{ $row['gender'] === 'D' ? 'selected' : '' }}>D</option>
                                    </select>
                                </x-ui.td>
                                {{-- Age group --}}
                                <x-ui.td>
                                    <input type="text" name="rows[{{ $i }}][age_group]" aria-label="Altersklasse, Zeile {{ $i + 1 }}"
                                           value="{{ $row['age_group'] ?? '' }}"
                                           placeholder="Offen"
                                           class="w-20 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </x-ui.td>
                                {{-- Course --}}
                                <x-ui.td>
                                    <select name="rows[{{ $i }}][course]" aria-label="Bahn, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="Langbahn" {{ ($row['course'] ?? 'Langbahn') === 'Langbahn' ? 'selected' : '' }}>Langbahn</option>
                                        <option value="Kurzbahn" {{ ($row['course'] ?? 'Langbahn') === 'Kurzbahn' ? 'selected' : '' }}>Kurzbahn</option>
                                    </select>
                                </x-ui.td>
                                {{-- Time (hidden ms + displayed string) --}}
                                <x-ui.td>
                                    <input type="hidden" name="rows[{{ $i }}][time_ms]" value="{{ $row['time_ms'] }}">
                                    <span class="font-mono {{ $row['time_ms'] <= 0 ? 'text-red-600' : 'text-primary font-semibold' }}">
                                        {{ $row['time_ms'] > 0 ? \App\Models\SwimmingTime::formatMs($row['time_ms']) : '–' }}
                                    </span>
                                </x-ui.td>
                                {{-- Swimmer name --}}
                                <x-ui.td>
                                    <input type="text" name="rows[{{ $i }}][swimmer_name]" aria-label="Name, Zeile {{ $i + 1 }}"
                                           value="{{ $row['swimmer_name'] }}"
                                           class="w-full min-w-[140px] px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </x-ui.td>
                                {{-- Date --}}
                                <x-ui.td>
                                    <input type="hidden" name="rows[{{ $i }}][birth_year]" value="{{ $row['birth_year'] ?? '' }}">
                                    @if(empty($row['set_date']) && !empty($row['set_year']))
                                        {{-- Liste nennt nur das Jahr --}}
                                        <input type="number" name="rows[{{ $i }}][set_year]" aria-label="Jahr, Zeile {{ $i + 1 }}" value="{{ $row['set_year'] }}"
                                               min="1900" max="{{ now()->year }}" title="Nur das Jahr ist bekannt"
                                               class="w-20 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                    @else
                                        <input type="date" name="rows[{{ $i }}][set_date]" aria-label="Datum, Zeile {{ $i + 1 }}"
                                               value="{{ $row['set_date'] ?? '' }}"
                                               class="px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                    @endif
                                </x-ui.td>
                                {{-- Location --}}
                                <x-ui.td>
                                    <input type="text" name="rows[{{ $i }}][location]" aria-label="Ort, Zeile {{ $i + 1 }}"
                                           value="{{ $row['location'] ?? '' }}"
                                           placeholder="Ort / Wettkampf"
                                           class="w-32 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
        </div>

        <x-ui.import-bar :cancel="route('admin.records.index')" count="input[name$='[include]']:checked"
                         singular="Rekord" plural="Rekorde" />
    </form>
</div>
@endsection

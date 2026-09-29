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
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-3 py-2.5 text-left">
                                <input type="checkbox" id="selectAll" class="rounded" aria-label="Alle Zeilen auswählen"
                                       {{-- Vorher \" im Attribut: HTML kennt das nicht, der Handler brach ab --}}
                                       onclick="document.querySelectorAll('input[type=checkbox][name$=\'[include]\']').forEach(cb => cb.checked = this.checked)">
                            </th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Disziplin</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Distanz</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Geschl.</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Altersklasse</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Bahn</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Zeit</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide min-w-[160px]">Name</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Datum / Jahr</th>
                            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Ort</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($rows as $i => $row)
                            @php
                                $hasIssue = !$row['discipline'] || !$row['distance'] || !$row['gender'] || $row['time_ms'] <= 0;
                            @endphp
                            <tr class="{{ $hasIssue ? 'bg-red-50/40' : 'hover:bg-gray-50' }}">
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="rows[{{ $i }}][include]" value="1"
                                           aria-label="Zeile {{ $i + 1 }} übernehmen{{ $row['swimmer_name'] ? ': ' . $row['swimmer_name'] : '' }}"
                                           {{ !$hasIssue ? 'checked' : '' }} class="rounded">
                                </td>
                                {{-- Discipline --}}
                                <td class="px-3 py-2">
                                    <select name="rows[{{ $i }}][discipline]" aria-label="Disziplin, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border {{ !$row['discipline'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">– wählen –</option>
                                        @foreach(['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'] as $val => $label)
                                            <option value="{{ $val }}" {{ $row['discipline'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                {{-- Distance --}}
                                <td class="px-3 py-2">
                                    <select name="rows[{{ $i }}][distance]" aria-label="Distanz, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border {{ !$row['distance'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">– wählen –</option>
                                        @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                                            <option value="{{ $d }}" {{ (int)$row['distance'] === $d ? 'selected' : '' }}>{{ $d }} m</option>
                                        @endforeach
                                    </select>
                                </td>
                                {{-- Gender --}}
                                <td class="px-3 py-2">
                                    <select name="rows[{{ $i }}][gender]" aria-label="Geschlecht, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border {{ !$row['gender'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">–</option>
                                        <option value="M" {{ $row['gender'] === 'M' ? 'selected' : '' }}>M</option>
                                        <option value="F" {{ $row['gender'] === 'F' ? 'selected' : '' }}>W</option>
                                    </select>
                                </td>
                                {{-- Age group --}}
                                <td class="px-3 py-2">
                                    <input type="text" name="rows[{{ $i }}][age_group]" aria-label="Altersklasse, Zeile {{ $i + 1 }}"
                                           value="{{ $row['age_group'] ?? '' }}"
                                           placeholder="Offen"
                                           class="w-20 px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </td>
                                {{-- Course --}}
                                <td class="px-3 py-2">
                                    <select name="rows[{{ $i }}][course]" aria-label="Bahn, Zeile {{ $i + 1 }}"
                                            class="px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="Langbahn" {{ ($row['course'] ?? 'Langbahn') === 'Langbahn' ? 'selected' : '' }}>Langbahn</option>
                                        <option value="Kurzbahn" {{ ($row['course'] ?? 'Langbahn') === 'Kurzbahn' ? 'selected' : '' }}>Kurzbahn</option>
                                    </select>
                                </td>
                                {{-- Time (hidden ms + displayed string) --}}
                                <td class="px-3 py-2">
                                    <input type="hidden" name="rows[{{ $i }}][time_ms]" value="{{ $row['time_ms'] }}">
                                    <span class="font-mono {{ $row['time_ms'] <= 0 ? 'text-red-600' : 'text-primary font-semibold' }}">
                                        {{ $row['time_ms'] > 0 ? \App\Models\SwimmingTime::formatMs($row['time_ms']) : '–' }}
                                    </span>
                                </td>
                                {{-- Swimmer name --}}
                                <td class="px-3 py-2">
                                    <input type="text" name="rows[{{ $i }}][swimmer_name]" aria-label="Name, Zeile {{ $i + 1 }}"
                                           value="{{ $row['swimmer_name'] }}"
                                           class="w-full min-w-[140px] px-2 py-1 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none">
                                </td>
                                {{-- Date --}}
                                <td class="px-3 py-2">
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
                                </td>
                                {{-- Location --}}
                                <td class="px-3 py-2">
                                    <input type="text" name="rows[{{ $i }}][location]" aria-label="Ort, Zeile {{ $i + 1 }}"
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

        <x-ui.import-bar :cancel="route('admin.records.index')" count="input[name$='[include]']:checked"
                         singular="Rekord" plural="Rekorde" />
    </form>
</div>
@endsection

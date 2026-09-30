@extends('layouts.app')
@section('title', 'Hallenbelegungsplan – Vorschau')
@section('page-title', 'Hallenbelegungsplan – Vorschau')

@section('content')
@php
    $days = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
             5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];

    $importable = collect($entries)->filter(fn($e) => empty($e['conflict']));
    $blocked    = collect($entries)->filter(fn($e) => !empty($e['conflict']));
    $needsInput = $importable->filter(fn($e) =>
        ($e['needs_session'] ?? false) && (empty($e['group_ids']) || empty($e['trainer_ids'])));
@endphp

<form method="POST" action="{{ route('trainer.hall.import.execute') }}"
      x-data="{ onlyOpen: false }" class="mt-2 space-y-5">
    @csrf

    <x-ui.import-steps :current="2" />

    <x-ui.import-summary :items="[
        ['label' => 'Einträge', 'count' => count($entries), 'tone' => 'neutral', 'hint' => 'Blatt: ' . $sheet],
        ['label' => 'neue Belegungen', 'count' => $preview['bookings'], 'tone' => 'success'],
        ['label' => 'neue Serien', 'count' => $preview['series'] ?? 0, 'tone' => 'brand', 'hint' => $preview['sessions'] . ' Termine; gleiche Zeit + Gruppe auf mehreren Bahnen = eine Serie'],
        ['label' => 'an bestehende Serie', 'count' => $preview['linked'] ?? 0, 'tone' => 'neutral', 'hint' => 'Serie für Gruppe und Zeit gibt es schon'],
        ['label' => 'übersprungen', 'count' => $blocked->count(), 'tone' => 'warning', 'hint' => 'Überschneidung mit Bestehendem'],
    ]" />

    @if($preview['sessions'] > 1000)
        <div class="px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <strong>{{ number_format($preview['sessions'], 0, ',', '.') }} Trainingseinheiten</strong>
            entstehen bei dieser Auswahl – über die Saison
            @if($season){{ $season->name }} bis {{ $season->end_date->format('d.m.Y') }}@endif.
            Bitte vor dem Speichern prüfen, ob das so gewollt ist.
        </div>
    @endif

    @if($needsInput->isNotEmpty())
        <div class="px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
            Bei <strong>{{ $needsInput->count() }}</strong> Zeilen fehlt Gruppe oder Trainer.
            Ohne Ergänzung entsteht dort nur die Belegung, keine Trainingsserie.
        </div>
    @endif

    @if($warnings)
        <div class="px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <p class="font-semibold mb-1">Hinweise aus der Datei</p>
            <ul class="space-y-0.5">
                @foreach($warnings as $w)<li>{{ $w }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Steuerung --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3 flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" x-model="onlyOpen" class="rounded text-primary">
            Nur Zeilen mit offener Zuordnung zeigen
        </label>
        <div class="ml-auto flex gap-2">
            <button type="button"
                    @click="$root.querySelectorAll('input[name^=selected]:not(:disabled)').forEach(c => c.checked = true)"
                    class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                Alle auswählen
            </button>
            <button type="button"
                    @click="$root.querySelectorAll('input[name^=selected]').forEach(c => c.checked = false)"
                    class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                Keine
            </button>
        </div>
    </div>

    {{-- Tabelle --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                    <th class="px-3 py-3 w-10"></th>
                    <th class="px-3 py-3 text-left font-medium">Tag / Zeit</th>
                    <th class="px-3 py-3 text-left font-medium">Ressource</th>
                    <th class="px-3 py-3 text-left font-medium">Kategorie</th>
                    <th class="px-3 py-3 text-left font-medium">Gruppe</th>
                    <th class="px-3 py-3 text-left font-medium">Trainer</th>
                    <th class="px-3 py-3 text-left font-medium">Serie</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($entries as $i => $e)
                    @php
                        $conflict = $e['conflict'] ?? null;
                        $openRow  = ($e['needs_session'] ?? false)
                                    && (empty($e['group_ids']) || empty($e['trainer_ids']));
                    @endphp
                    <tr class="{{ $conflict ? 'bg-amber-50/50' : 'hover:bg-gray-50' }}"
                        x-show="!onlyOpen || {{ $openRow ? 'true' : 'false' }}" x-cloak>

                        <td class="px-3 py-2">
                            <input type="checkbox" name="selected[]" value="{{ $i }}" aria-label="Zeile {{ $i + 1 }} übernehmen"
                                   {{ $conflict ? 'disabled' : 'checked' }}
                                   class="rounded text-primary disabled:opacity-30">
                        </td>

                        <td class="px-3 py-2 whitespace-nowrap">
                            <span class="text-gray-700">{{ $days[$e['day']] ?? $e['day'] }}</span>
                            <span class="text-gray-400 text-xs block">{{ $e['start'] }}–{{ $e['end'] }}</span>
                        </td>

                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $e['resource'] }}</td>

                        <td class="px-3 py-2">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                {{ $e['category_label'] }}
                            </span>
                        </td>

                        {{-- Gruppe --}}
                        <td class="px-3 py-2">
                            @if($e['group_ids'])
                                <span class="text-gray-700">
                                    {{ $groups->whereIn('id', $e['group_ids'])->pluck('name')->join(', ') }}
                                </span>
                            @elseif($e['needs_session'])
                                <select name="group[{{ $i }}]"
                                        class="text-xs border border-amber-300 rounded px-2 py-1 bg-amber-50 max-w-[190px]">
                                    <option value="">– „{{ $e['group_raw'] }}" zuordnen –</option>
                                    @foreach($groups as $g)
                                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="text-gray-500 text-xs">{{ $e['group_raw'] }}</span>
                            @endif
                        </td>

                        {{-- Trainer --}}
                        <td class="px-3 py-2">
                            @if($e['trainer_ids'])
                                <span class="text-gray-700">
                                    {{ $trainers->whereIn('id', $e['trainer_ids'])->pluck('firstname')->join(', ') }}
                                </span>
                            @elseif($e['needs_session'])
                                <select name="trainer[{{ $i }}]"
                                        class="text-xs border border-amber-300 rounded px-2 py-1 bg-amber-50 max-w-[190px]">
                                    <option value="">
                                        – {{ $e['trainers_raw'] ? '„'.implode(', ', $e['trainers_raw']).'"' : 'offen' }} –
                                    </option>
                                    @foreach($trainers as $t)
                                        <option value="{{ $t->id }}">{{ $t->firstname }} {{ $t->lastname }}</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="text-gray-400 text-xs">{{ implode(', ', $e['trainers_raw']) }}</span>
                            @endif
                        </td>

                        {{-- Serie / Konflikt --}}
                        <td class="px-3 py-2 text-xs whitespace-nowrap">
                            @if($conflict)
                                <span class="text-amber-700" title="{{ $conflict['label'] ?? '' }}">
                                    belegt{{ isset($conflict['time']) ? ' ('.$conflict['time'].')' : '' }}
                                </span>
                            @elseif($e['session_ready'])
                                <span class="text-blue-700">Serie</span>
                            @elseif($e['needs_session'])
                                <span class="text-gray-400">nur Belegung</span>
                            @else
                                <span class="text-gray-300">–</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-ui.import-bar :cancel="route('trainer.hall.import.index')" count="input[name='selected[]']:checked"
                     singular="Eintrag" plural="Einträge" />
</form>
@endsection

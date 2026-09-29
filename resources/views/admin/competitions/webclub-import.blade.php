@extends('layouts.app')
@section('title', 'Veranstaltungen aus WebClub')
@section('page-title', 'Veranstaltungen aus WebClub importieren')

@section('content')
<div class="mt-2 space-y-5 max-w-5xl">
    <x-ui.import-steps :current="empty($rows) ? 1 : 2" />

    {{-- Back link --}}
    <a href="{{ route('admin.competitions.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Zurück zur Übersicht
    </a>


    @if(empty($rows))
        {{-- ── Upload form ────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">CSV-Datei aus WebClub hochladen</h2>
            <p class="text-sm text-gray-700 mb-5">
                Exportieren Sie die Saisonliste aus WebClub als CSV (Semikolon-getrennt).<br>
                Erwartetes Format: Zeile 1 = Saisonüberschrift, Zeile 2 = Spaltenüberschriften, ab Zeile 3 = Termine.
            </p>

            <x-ui.upload-form :action="route('admin.competitions.webclub-import.preview')">
                <x-ui.file-drop name="csv_file" label="Terminliste (CSV)" accept=".csv,.txt" :max-mb="2" />
            </x-ui.upload-form>
        </div>

    @else
        {{-- ── Preview / import form ──────────────────────────────────── --}}
        <form method="POST" action="{{ route('admin.competitions.webclub-import.import') }}" x-data="{ allSelected: true }" @submit.prevent="$el.submit()">
            @csrf

            {{-- Season + info bar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
                <div class="flex flex-wrap items-end gap-5">
                    <div class="flex-1 min-w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Saison zuordnen</label>
                        <select name="season_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="">— keine Saison —</option>
                            @foreach($seasons as $season)
                                <option value="{{ $season->id }}"
                                    @selected(isset($suggestedSeason) && $suggestedSeason?->id === $season->id)>
                                    {{ $season->name }}
                                </option>
                            @endforeach
                        </select>
                        @if(isset($csvSeasonName))
                            <p class="mt-1 text-xs text-gray-400">Aus CSV erkannt: <span class="font-medium">{{ $csvSeasonName }}</span></p>
                        @endif
                    </div>

                    <div class="text-sm text-gray-500">
                        <span class="font-semibold text-gray-700">{{ count($rows) }}</span> Termine gelesen
                        @php $existsCount = collect($rows)->where('exists', true)->count(); @endphp
                        @if($existsCount)
                            · <span class="text-amber-600 font-medium">{{ $existsCount }} bereits vorhanden</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Preview table --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left w-8">
                                    <input type="checkbox" checked
                                           x-model="allSelected"
                                           @change="$el.closest('form').querySelectorAll('input[type=checkbox][name$=\"[selected]\"]').forEach(cb => cb.checked = allSelected)"
                                           class="rounded border-gray-300 text-accent focus:ring-accent cursor-pointer">
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Datum</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Veranstaltungsname</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 hidden md:table-cell">Meldeschluss</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 hidden lg:table-cell">Ort</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 hidden lg:table-cell">Typ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($rows as $i => $row)
                                <tr class="{{ $row['exists'] ? 'bg-amber-50' : 'hover:bg-gray-50' }} transition-colors">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="rows[{{ $i }}][selected]" value="1"
                                               {{ $row['exists'] ? '' : 'checked' }}
                                               class="rounded border-gray-300 text-accent focus:ring-accent cursor-pointer">
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                        {{ $row['date_disp'] }}
                                        <input type="hidden" name="rows[{{ $i }}][date]" value="{{ $row['date'] }}">
                                        <input type="hidden" name="rows[{{ $i }}][date_end]" value="{{ $row['date_end'] ?? '' }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="{{ $row['exists'] ? 'text-amber-700' : 'text-gray-800' }} font-medium">{{ $row['name'] }}</span>
                                        @if($row['exists'])
                                            <span class="ml-2 text-xs bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded">Vorhanden</span>
                                        @endif
                                        <input type="hidden" name="rows[{{ $i }}][name]" value="{{ $row['name'] }}">
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 hidden md:table-cell whitespace-nowrap">
                                        {{ $row['melde_disp'] }}
                                        <input type="hidden" name="rows[{{ $i }}][meldeschluss]" value="{{ $row['meldeschluss'] ?? '' }}">
                                    </td>
                                    <td class="px-4 py-3 hidden lg:table-cell">
                                        <input type="text" name="rows[{{ $i }}][location]" value="{{ $row['location'] }}"
                                               placeholder="Ort"
                                               class="w-full border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-accent">
                                    </td>
                                    <td class="px-4 py-3 hidden lg:table-cell">
                                        <select name="rows[{{ $i }}][type]"
                                                class="border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-accent">
                                            @foreach(\App\Models\Competition::TYPE_LABELS as $val => $label)
                                                <option value="{{ $val }}" @selected($row['type'] === $val)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('admin.competitions.webclub-import.form') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">
                    Andere Datei wählen
                </a>
                <button type="submit"
                        class="flex items-center gap-2 bg-accent text-white px-6 py-2 rounded-lg text-sm font-semibold hover:bg-accent-dark transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Ausgewählte importieren
                </button>
            </div>
        </form>
    @endif

</div>
@endsection

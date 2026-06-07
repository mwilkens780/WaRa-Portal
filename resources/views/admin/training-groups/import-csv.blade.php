@extends('layouts.app')
@section('title', 'CSV-Import: ' . $trainingGroup->name)
@section('page-title', 'CSV-Import: ' . $trainingGroup->name)

@section('content')
<div class="mt-2 space-y-4">

    {{-- Info banner --}}
    <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3 rounded-xl flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <strong>{{ count($rows) }} CSV-Zeilen erkannt</strong> –
            {{ collect($rows)->where('status', 'matched')->count() }} gefunden,
            {{ collect($rows)->where('status', 'unmatched')->count() }} nicht gefunden,
            {{ collect($rows)->where('status', 'ambiguous')->count() }} mehrdeutig.
            @if(count($toRemove) > 0)
                <span class="ml-2 text-amber-700">{{ count($toRemove) }} Mitglieder nicht in CSV (werden entfernt).</span>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.training-groups.csv-execute', $trainingGroup) }}">
        @csrf

        {{-- CSV rows --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3">
                <h2 class="font-semibold text-gray-800 text-sm flex-1">CSV-Zeilen</h2>
                <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                    <input type="checkbox" id="selectAllInclude" class="rounded"
                           onclick="document.querySelectorAll('[data-matched] input[value=include]').forEach(r => r.checked = this.checked); document.querySelectorAll('[data-matched] input[value=include]').forEach(r => r.dispatchEvent(new Event('change')))">
                    Alle gefundenen auswählen
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">Status</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">CSV-Name</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">Jg.</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">DSV-Id</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">Aktiv</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500">Datenbank-Benutzer</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 min-w-[160px]">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($rows as $i => $row)
                        @php
                            $statusColor = match($row['status']) {
                                'matched'   => 'bg-green-100 text-green-700',
                                'ambiguous' => 'bg-amber-100 text-amber-700',
                                default     => 'bg-red-100 text-red-600',
                            };
                            $statusLabel = match($row['status']) {
                                'matched'   => '✓ Gefunden',
                                'ambiguous' => '? Mehrdeutig',
                                default     => '✗ Nicht gefunden',
                            };
                        @endphp
                        <tr class="{{ $row['status'] === 'matched' ? 'hover:bg-gray-50' : ($row['status'] === 'ambiguous' ? 'bg-amber-50/30' : 'bg-red-50/30') }}"
                            @if($row['status'] === 'matched') data-matched @endif>
                            {{-- Status --}}
                            <td class="px-4 py-2">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                @if($row['in_group'])
                                    <span class="text-xs text-gray-400 ml-1">bereits in Gruppe</span>
                                @endif
                            </td>
                            {{-- CSV Name --}}
                            <td class="px-4 py-2 font-medium text-gray-800">{{ $row['csv_name'] }}</td>
                            {{-- Jg. --}}
                            <td class="px-4 py-2 text-gray-500">{{ $row['csv_year'] ?: '–' }}</td>
                            {{-- DSV-Id --}}
                            <td class="px-4 py-2 text-gray-500 font-mono text-xs">{{ $row['csv_dsv_id'] ?: '–' }}</td>
                            {{-- Aktiv --}}
                            <td class="px-4 py-2">
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $row['csv_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $row['csv_active'] ? 'Ja' : 'Nein' }}
                                </span>
                            </td>
                            {{-- DB User --}}
                            <td class="px-4 py-2 text-gray-600">
                                @if($row['user_name'])
                                    {{ $row['user_name'] }}
                                    @if($row['user_year']) <span class="text-gray-400 text-xs">({{ $row['user_year'] }})</span> @endif
                                    <input type="hidden" name="rows[{{ $i }}][user_id]" value="{{ $row['user_id'] }}">
                                @elseif($row['status'] === 'unmatched')
                                    {{-- Neu anlegen: Felder --}}
                                    <div id="create-fields-{{ $i }}" class="hidden space-y-1 mt-1">
                                        <input type="text" name="rows[{{ $i }}][new_firstname]"
                                               placeholder="Vorname"
                                               class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                                        <input type="text" name="rows[{{ $i }}][new_lastname]"
                                               placeholder="Nachname"
                                               class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                                        <input type="email" name="rows[{{ $i }}][new_email]"
                                               placeholder="E-Mail (optional)"
                                               class="w-full px-2 py-1 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                                    </div>
                                @else
                                    <span class="text-xs text-amber-600">Mehrere Treffer</span>
                                @endif
                            </td>
                            {{-- Aktion --}}
                            <td class="px-4 py-2">
                                @if($row['status'] === 'matched')
                                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                                        <input type="radio" name="rows[{{ $i }}][action]" value="include" checked
                                               class="text-primary border-gray-300">
                                        <span class="text-gray-700">Einschließen</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs cursor-pointer mt-1">
                                        <input type="radio" name="rows[{{ $i }}][action]" value="skip"
                                               class="text-gray-400 border-gray-300">
                                        <span class="text-gray-500">Überspringen</span>
                                    </label>
                                @elseif($row['status'] === 'unmatched')
                                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                                        <input type="radio" name="rows[{{ $i }}][action]" value="skip" checked
                                               class="text-gray-400 border-gray-300"
                                               onchange="document.getElementById('create-fields-{{ $i }}').classList.add('hidden')">
                                        <span class="text-gray-500">Überspringen</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs cursor-pointer mt-1">
                                        <input type="radio" name="rows[{{ $i }}][action]" value="create"
                                               class="text-green-600 border-gray-300"
                                               onchange="document.getElementById('create-fields-{{ $i }}').classList.remove('hidden')">
                                        <span class="text-green-700 font-medium">Neu anlegen</span>
                                    </label>
                                @else
                                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                                        <input type="radio" name="rows[{{ $i }}][action]" value="skip" checked
                                               class="text-gray-400 border-gray-300">
                                        <span class="text-gray-500">Überspringen</span>
                                    </label>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Zu entfernende Mitglieder --}}
        @if(count($toRemove) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-red-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-red-100 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-800 text-sm">Nicht mehr in CSV – werden aus Gruppe entfernt</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Haken entfernen, um ein Mitglied zu behalten.</p>
                </div>
                <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">{{ count($toRemove) }}</span>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($toRemove as $j => $entry)
                <div class="flex items-center gap-3 px-5 py-2.5">
                    <input type="hidden" name="remove[{{ $j }}]" value="0">
                    <input type="checkbox" name="remove[{{ $j }}]" value="1" checked
                           class="w-4 h-4 rounded text-red-500 border-gray-300">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $entry['name'] }}</p>
                        @if($entry['year'])
                            <p class="text-xs text-gray-400">Jg. {{ $entry['year'] }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Actions --}}
        <div class="flex gap-3">
            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Import ausführen
            </button>
            <a href="{{ route('admin.training-groups.show', $trainingGroup) }}"
               class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                Abbrechen
            </a>
        </div>
    </form>
</div>
@endsection

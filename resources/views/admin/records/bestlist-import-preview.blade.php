@extends('layouts.app')
@section('title', 'Bestenliste importieren')
@section('page-title', 'Bestenliste importieren – Vorschau')

@php
    $discLabels = ['F' => 'Freistil', 'R' => 'Rücken', 'B' => 'Brust', 'S' => 'Schmetterling', 'L' => 'Lagen'];
    $withoutBirthYear = $entries->whereNull('birth_year')->count();
    $withoutYear      = $entries->whereNull('set_year')->count();
@endphp

@section('content')
<form method="POST" action="{{ route('admin.bestlist.import.execute') }}" class="mt-2 space-y-5">
    @csrf
    <x-ui.import-steps :current="2" />

    <x-ui.import-summary :items="[
        ['label' => 'Einträge', 'count' => $entries->count(), 'tone' => 'success', 'hint' => 'Bahn: ' . $courses->implode(', ')],
        ['label' => 'bisher importiert', 'count' => $existing, 'tone' => 'neutral', 'hint' => 'werden ersetzt, wenn unten gewählt'],
        ['label' => 'ohne Jahrgang', 'count' => $withoutBirthYear, 'tone' => 'warning'],
        ['label' => 'ohne Jahr', 'count' => $withoutYear, 'tone' => 'warning'],
    ]" />

    @if($warnings)
        <div class="px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <p class="font-semibold mb-1">Hinweise aus der Datei</p>
            <ul class="space-y-0.5 max-h-40 overflow-y-auto">
                @foreach($warnings as $w)<li>{{ $w }}</li>@endforeach
            </ul>
        </div>
    @endif

    <x-ui.card>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="replace" value="1" checked class="mt-1 rounded text-primary">
            <span class="text-sm text-gray-800">
                Früher importierte Einträge dieser Bahn ersetzen
                @if($existing > 0)
                    <span class="text-gray-600">({{ $existing }} vorhanden)</span>
                @endif
                <span class="block text-xs text-gray-600">
                    Von Hand angelegte Einträge und Wettkampfergebnisse bleiben in jedem Fall erhalten.
                    Korrekturen an einzelnen Zeilen sind nach dem Import direkt in der Bestenliste möglich.
                </span>
            </span>
        </label>
    </x-ui.card>

    {{-- Gelesene Daten --}}
    @foreach($entries->groupBy('gender') as $gender => $byGender)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-100 px-5 py-2">
                <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                    {{ $gender === 'M' ? 'Männlich' : 'Weiblich' }} · {{ $byGender->count() }} Einträge
                </p>
            </div>
            @foreach($byGender->groupBy(fn($e) => $e['discipline'] . '_' . $e['distance']) as $key => $rows)
                @php [$disc, $dist] = explode('_', $key); @endphp
                <div class="border-t border-gray-50">
                    <div class="bg-gray-50 px-5 py-1.5 text-xs font-semibold text-gray-600">
                        {{ $dist }} m {{ $discLabels[$disc] ?? $disc }} · {{ $rows->count() }} Zeiten
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm table-fixed min-w-[520px]">
                            <colgroup><col><col class="w-24"><col class="w-28"><col class="w-20"></colgroup>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($rows->sortBy('time_ms') as $e)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-1.5 text-gray-800 truncate">{{ $e['swimmer_name'] }}</td>
                                        <td class="px-5 py-1.5 text-xs {{ $e['birth_year'] ? 'text-gray-500' : 'text-amber-700' }}">
                                            {{ $e['birth_year'] ?? 'Jahrgang fehlt' }}
                                        </td>
                                        <td class="px-5 py-1.5 text-right tabular-nums font-mono text-gray-700">
                                            {{ \App\Models\SwimmingTime::formatMs($e['time_ms']) }}
                                        </td>
                                        <td class="px-5 py-1.5 text-xs text-gray-500">{{ $e['set_year'] ?? '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <x-ui.import-bar :cancel="route('admin.records.index', ['tab' => 'eternal'])" :total="$entries->count()"
                     singular="Eintrag" plural="Einträge" />
</form>
@endsection

@extends('layouts.app')
@section('title', 'Bestenliste importieren')
@section('page-title', 'Bestenliste importieren – Vorschau')

@php
    $discLabels = ['F' => 'Freistil', 'R' => 'Rücken', 'B' => 'Brust', 'S' => 'Schmetterling', 'L' => 'Lagen'];
    $withoutBirthYear = $entries->whereNull('birth_year')->count();
    $withoutYear      = $entries->whereNull('set_year')->count();
@endphp

@section('content')
<div class="mt-2 space-y-5">

    <a href="{{ route('admin.records.index', ['tab' => 'eternal']) }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zu Rekorde &amp; Bestenlisten
    </a>

    {{-- Zusammenfassung --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
            <p class="text-xs text-gray-500">Einträge</p>
            <p class="text-2xl font-bold text-gray-800">{{ $entries->count() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-blue-100 bg-blue-50 shadow-sm px-4 py-3 text-center">
            <p class="text-xs text-blue-600">Bahn</p>
            <p class="text-lg font-bold text-blue-700">{{ $courses->implode(', ') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
            <p class="text-xs text-gray-500">Ohne Jahrgang</p>
            <p class="text-2xl font-bold {{ $withoutBirthYear ? 'text-amber-600' : 'text-gray-800' }}">{{ $withoutBirthYear }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
            <p class="text-xs text-gray-500">Ohne Jahr</p>
            <p class="text-2xl font-bold {{ $withoutYear ? 'text-amber-600' : 'text-gray-800' }}">{{ $withoutYear }}</p>
        </div>
    </div>

    @if($warnings)
        <div class="px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <p class="font-semibold mb-1">Hinweise aus der Datei</p>
            <ul class="space-y-0.5 max-h-40 overflow-y-auto">
                @foreach($warnings as $w)<li>{{ $w }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bestlist.import.execute') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-3">
        @csrf
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="replace" value="1" checked class="mt-1 rounded text-primary">
            <span class="text-sm text-gray-700">
                Früher importierte Einträge dieser Bahn ersetzen
                @if($existing > 0)
                    <span class="text-gray-500">({{ $existing }} vorhanden)</span>
                @endif
                <span class="block text-xs text-gray-400">
                    Von Hand angelegte Einträge und Wettkampfergebnisse bleiben in jedem Fall erhalten.
                </span>
            </span>
        </label>
        <div class="flex items-center gap-3 pt-1">
            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                {{ $entries->count() }} Einträge importieren
            </button>
            <a href="{{ route('admin.records.index', ['tab' => 'eternal']) }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
        </div>
        <p class="text-xs text-gray-400">
            Korrekturen an einzelnen Zeilen sind nach dem Import direkt in der Bestenliste möglich.
        </p>
    </form>

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
                                        <td class="px-5 py-1.5 text-xs {{ $e['birth_year'] ? 'text-gray-500' : 'text-amber-600' }}">
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
</div>
@endsection

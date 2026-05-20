@extends('layouts.app')
@section('title', 'Meine Zeiten')
@section('page-title', 'Meine Zeiten')

@section('content')
<div class="mt-2 space-y-6">

    {{-- Filter-Tabs --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-1 flex gap-1 w-fit">
        @foreach([
            'all'    => 'Alle Zeiten',
            'year'   => now()->year,
            'season' => $seasonLabel,
        ] as $val => $label)
            <a href="{{ route('swimmer.times', ['filter' => $val]) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ $filter === $val ? 'bg-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Bestzeiten-Tabelle (abhängig vom Filter) --}}
    @if($bests->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">
                    @if($filter === 'year') Bestzeiten {{ now()->year }}
                    @elseif($filter === 'season') Bestzeiten {{ $seasonLabel }}
                    @else Persönliche Bestzeiten
                    @endif
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Disziplin</th>
                            @foreach([25, 50, 100, 200, 400, 800, 1500] as $dist)
                                <th class="text-right px-3 py-2.5 text-xs font-semibold text-gray-600">{{ $dist }} m</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach(['freistil' => 'Freistil', 'brust' => 'Brust', 'ruecken' => 'Rücken', 'schmetterling' => 'Schmetterling', 'lagen' => 'Lagen'] as $disc => $label)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-700">{{ $label }}</td>
                                @foreach([25, 50, 100, 200, 400, 800, 1500] as $dist)
                                    @php $key = $disc . '_' . $dist; $best = $bests->get($key); @endphp
                                    <td class="px-3 py-3 text-right font-mono {{ $best ? 'font-semibold text-primary' : 'text-gray-300' }}">
                                        {{ $best ? \App\Models\SwimmingTime::formatMs($best->best_ms) : '–' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Zeitenliste --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">
                @if($filter === 'year') Zeiten {{ now()->year }}
                @elseif($filter === 'season') Zeiten {{ $seasonLabel }}
                @else Alle erfassten Zeiten
                @endif
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Datum</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Training</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Disziplin</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Distanz</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-600">Zeit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($times as $time)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-500">
                                {{ $time->trainingSession?->date->format('d.m.Y') ?? '–' }}
                            </td>
                            <td class="px-5 py-3 text-gray-600 max-w-[150px] truncate">
                                {{ $time->trainingSession?->title ?? '–' }}
                            </td>
                            <td class="px-5 py-3 text-gray-700">{{ $time->discipline_label }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $time->distance }} m</td>
                            <td class="px-5 py-3 text-right">
                                <span class="font-mono font-semibold text-primary">{{ $time->formatted_time }}</span>
                                @if($time->is_personal_best)
                                    <span class="ml-1.5 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-sans font-medium">PB</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Keine Zeiten im gewählten Zeitraum.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($times->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $times->links() }}</div>
        @endif
    </div>
</div>
@endsection

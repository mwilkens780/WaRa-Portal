@extends('layouts.app')
@section('title', 'Mein Dashboard')
@section('page-title', 'Hallo, ' . auth()->user()->name . '!')

@section('content')
<div class="space-y-6 mt-2">

    {{-- Statistik --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Trainings gesamt</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['trainings_total'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Dieses Jahr</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['trainings_this_year'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Persönliche Bestzeiten</p>
            <p class="text-3xl font-bold text-accent mt-1">{{ $stats['personal_bests'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Wettkämpfe</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['competitions'] }}</p>
        </div>
    </div>

    {{-- Trainingsbeteiligung --}}
    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500 mb-1">Beteiligung gesamt</p>
            <div class="flex items-end gap-3">
                <p class="text-3xl font-bold text-primary">{{ $stats['participation_pct'] }} %</p>
                <p class="text-xs text-gray-400 pb-1">{{ $stats['trainings_total'] }} Trainings absolviert</p>
            </div>
            <div class="bg-gray-100 rounded-full h-2.5 mt-3">
                <div class="bg-primary h-2.5 rounded-full" style="width: {{ $stats['participation_pct'] }}%"></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500 mb-1">Beteiligung {{ now()->year }}</p>
            <div class="flex items-end gap-3">
                <p class="text-3xl font-bold text-primary">{{ $stats['participation_year'] }} %</p>
                <p class="text-xs text-gray-400 pb-1">{{ $stats['trainings_this_year'] }} Trainings dieses Jahr</p>
            </div>
            <div class="bg-gray-100 rounded-full h-2.5 mt-3">
                <div class="bg-primary h-2.5 rounded-full" style="width: {{ $stats['participation_year'] }}%"></div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Bestzeiten mit Tabs --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100" x-data="{ bestTab: 'alltime' }">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Meine Bestzeiten</h2>
                <a href="{{ route('swimmer.times') }}" class="text-sm text-primary hover:underline">Alle Zeiten</a>
            </div>
            {{-- Tab-Navigation --}}
            <div class="flex border-b border-gray-100 text-xs">
                <button @click="bestTab = 'alltime'"
                        :class="bestTab === 'alltime' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2.5 font-medium border-b-2 -mb-px transition-colors">
                    Allzeit
                </button>
                <button @click="bestTab = 'year'"
                        :class="bestTab === 'year' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2.5 font-medium border-b-2 -mb-px transition-colors">
                    {{ now()->year }}
                </button>
                <button @click="bestTab = 'season'"
                        :class="bestTab === 'season' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2.5 font-medium border-b-2 -mb-px transition-colors">
                    Saison
                </button>
            </div>

            {{-- Allzeit-Bestzeiten --}}
            <div x-show="bestTab === 'alltime'">
                @if($personal_bests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Noch keine Zeiten erfasst.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Bestzeit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($personal_bests as $label => $times)
                                    @php $best = $times->sortBy('time_ms')->first(); @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">
                                            {{ $best->formatted_time }}
                                            <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-sans font-medium ml-1">PB</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Jahres-Bestzeiten --}}
            <div x-show="bestTab === 'year'" x-cloak>
                @if($year_bests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Keine Zeiten in {{ now()->year }}.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Bestzeit {{ now()->year }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($year_bests as $key => $row)
                                    @php
                                        [$disc, $dist] = explode('_', $key, 2);
                                        $discLabels = ['freistil'=>'Freistil','brust'=>'Brust','ruecken'=>'Rücken','schmetterling'=>'Schmetterling','lagen'=>'Lagen'];
                                        $label = ($discLabels[$disc] ?? $disc) . ' ' . $dist . 'm';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">
                                            {{ \App\Models\SwimmingTime::formatMs($row->best_ms) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Saison-Bestzeiten --}}
            <div x-show="bestTab === 'season'" x-cloak>
                @if($season_bests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Keine Zeiten in der aktuellen Saison.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Saisonbest</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($season_bests as $key => $row)
                                    @php
                                        [$disc, $dist] = explode('_', $key, 2);
                                        $discLabels = ['freistil'=>'Freistil','brust'=>'Brust','ruecken'=>'Rücken','schmetterling'=>'Schmetterling','lagen'=>'Lagen'];
                                        $label = ($discLabels[$disc] ?? $disc) . ' ' . $dist . 'm';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">
                                            {{ \App\Models\SwimmingTime::formatMs($row->best_ms) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Letzte Trainings & Wettkämpfe --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Letzte Trainings</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recent_trainings as $att)
                        <a href="{{ route('swimmer.session.show', $att->session) }}"
                           class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                            <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[50px]">
                                <p class="text-xs font-semibold text-primary">{{ $att->session->date->format('d.M') }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $att->session->title }}</p>
                                <p class="text-xs text-gray-500">{{ $att->session->trainer->name }} · {{ $att->session->type_label }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-400 px-5 py-4 text-center">Noch keine Trainings.</p>
                    @endforelse
                </div>
            </div>

            @if($recent_results->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100">
                        <h2 class="font-semibold text-gray-800">Letzte Wettkampfergebnisse</h2>
                        <a href="{{ route('swimmer.competitions') }}" class="text-sm text-primary hover:underline">Alle</a>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($recent_results as $result)
                            <div class="flex items-center gap-4 px-5 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $result->competition->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $result->distance }}m {{ $result->discipline_label }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono font-bold text-primary text-sm">{{ $result->formatted_time }}</p>
                                    @if($result->placement)
                                        <p class="text-xs text-gray-500">Platz {{ $result->placement }}</p>
                                    @endif
                                </div>
                                @if($result->is_personal_best)
                                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">PB</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

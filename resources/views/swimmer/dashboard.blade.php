@extends('layouts.app')
@section('title', 'Mein Dashboard')
@section('page-title', 'Hallo, ' . auth()->user()->name . '!')

@section('content')
<div class="space-y-6 mt-2">

    {{-- Statistik --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <a href="{{ route('swimmer.sessions') }}"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Trainings gesamt</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['trainings_total'] }}</p>
        </a>
        <a href="{{ route('swimmer.sessions') }}"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Dieses Jahr</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['trainings_this_year'] }}</p>
        </a>
        <a href="#bestzeiten"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Persönliche Bestzeiten</p>
            <p class="text-3xl font-bold text-accent mt-1">{{ $stats['personal_bests'] }}</p>
        </a>
        <a href="{{ route('swimmer.competitions') }}"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Wettkämpfe</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['competitions'] }}</p>
        </a>
        <a href="{{ route('swimmer.goals.index') }}"
           class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition-shadow relative
                  {{ $goalsUnnotified > 0 ? 'border-green-300 bg-green-50/40' : 'border-gray-100' }}">
            @if($goalsUnnotified > 0)
                <span class="absolute top-3 right-3 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                </span>
            @endif
            <p class="text-sm text-gray-500">Meine Ziele</p>
            <p class="text-3xl font-bold {{ $goalsUnnotified > 0 ? 'text-green-600' : 'text-primary' }} mt-1">
                {{ $goalsAchieved }}<span class="text-lg font-normal text-gray-400">/{{ $goalsTotal }}</span>
            </p>
            @if($goalsTotal > 0)
                <div class="bg-gray-100 rounded-full h-1.5 mt-2">
                    <div class="{{ $goalsUnnotified > 0 ? 'bg-green-500' : 'bg-primary' }} h-1.5 rounded-full"
                         style="width: {{ $goalsTotal > 0 ? round($goalsAchieved / $goalsTotal * 100) : 0 }}%"></div>
                </div>
            @else
                <p class="text-xs text-gray-400 mt-2">Noch keine Ziele</p>
            @endif
        </a>
    </div>

    {{-- Trainingsbeteiligung --}}
    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500 mb-1">Beteiligung in dieser Saison</p>
            <div class="flex items-end gap-3">
                <p class="text-3xl font-bold text-primary">{{ $stats['participation_season'] }} %</p>
                <p class="text-xs text-gray-400 pb-1">{{ $stats['attended_season'] }} / {{ $stats['sessions_season'] }} Trainings</p>
            </div>
            <div class="bg-gray-100 rounded-full h-2.5 mt-3">
                <div class="bg-primary h-2.5 rounded-full" style="width: {{ min(100, $stats['participation_season']) }}%"></div>
            </div>
            @if($stats['season_label'])
                <p class="text-xs text-gray-400 mt-1">Saison {{ $stats['season_label'] }}</p>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500 mb-1">Beteiligung in dieser Woche</p>
            <div class="flex items-end gap-3">
                <p class="text-3xl font-bold text-primary">{{ $stats['participation_week'] }} %</p>
                <p class="text-xs text-gray-400 pb-1">{{ $stats['attended_week'] }} / {{ $stats['sessions_week'] }} Trainings</p>
            </div>
            <div class="bg-gray-100 rounded-full h-2.5 mt-3">
                <div class="bg-primary h-2.5 rounded-full" style="width: {{ min(100, $stats['participation_week']) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Nächster Wettkampf --}}
    @if($next_competition)
    <a href="{{ route('swimmer.competitions') }}"
       class="block bg-white rounded-xl shadow-sm border border-primary/20 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
            <div class="bg-primary/10 rounded-xl p-3 flex-shrink-0 text-center min-w-[64px]">
                <p class="text-2xl font-bold text-primary leading-none">{{ $next_competition->date->format('d') }}</p>
                <p class="text-xs font-semibold text-primary/70 mt-0.5">{{ $next_competition->date->isoFormat('MMM') }}</p>
                <p class="text-xs text-primary/50">{{ $next_competition->date->year }}</p>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-0.5">Nächster Wettkampf</p>
                <p class="font-semibold text-gray-800 truncate">{{ $next_competition->name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $next_competition->location }}
                    @if($next_competition->type_label) · {{ $next_competition->type_label }} @endif
                    @if($next_competition->course) · {{ $next_competition->course_label }} @endif
                </p>
            </div>
            <div class="flex-shrink-0">
                @php $daysUntil = today()->diffInDays($next_competition->date, false); @endphp
                @if($daysUntil === 0)
                    <span class="text-xs font-bold bg-red-100 text-red-700 px-2 py-1 rounded-full">Heute</span>
                @elseif($daysUntil <= 7)
                    <span class="text-xs font-bold bg-amber-100 text-amber-700 px-2 py-1 rounded-full">in {{ $daysUntil }} Tagen</span>
                @else
                    <span class="text-xs text-gray-400">in {{ $daysUntil }} Tagen</span>
                @endif
            </div>
        </div>
    </a>
    @endif

    {{-- Geplante Trainings nächste 2 Wochen --}}
    @if($upcoming_sessions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Trainings – nächste 2 Wochen</h2>
            <span class="text-xs text-gray-400">{{ $upcoming_sessions->count() }} Einheit(en)</span>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($upcoming_sessions as $session)
                @php $isAbsent = $my_pre_absences->has($session->id); @endphp
                <div class="flex items-center gap-4 px-5 py-3 {{ $isAbsent ? 'bg-red-50/50' : 'hover:bg-gray-50' }} transition-colors">
                    <a href="{{ route('swimmer.session.show', $session) }}" class="flex items-center gap-4 flex-1 min-w-0">
                        <div class="text-center {{ $isAbsent ? 'bg-red-100' : 'bg-primary/10' }} rounded-lg p-2 min-w-[54px]">
                            <p class="text-xs font-bold {{ $isAbsent ? 'text-red-600' : 'text-primary' }}">{{ $session->date->format('d.M') }}</p>
                            <p class="text-xs {{ $isAbsent ? 'text-red-400' : 'text-primary/60' }}">{{ $session->date->isoFormat('ddd') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $session->title }}</p>
                                @if($isAbsent)
                                    <span class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">Abgesagt</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ $session->start_time }} Uhr
                                @if($session->location) · {{ $session->location }} @endif
                                · <span class="{{ $session->type_color }} text-xs px-1.5 py-0.5 rounded-full">{{ $session->type_label }}</span>
                            </p>
                            @if($isAbsent && $my_pre_absences[$session->id])
                                <p class="text-xs text-red-500 mt-0.5 italic">{{ $my_pre_absences[$session->id] }}</p>
                            @endif
                        </div>
                    </a>
                    {{-- Absage/Rücknahme Button --}}
                    <form method="POST" action="{{ route('swimmer.session.cancel', $session) }}" class="flex-shrink-0"
                          x-data="{ open: false }">
                        @csrf
                        @if($isAbsent)
                            <button type="submit"
                                    class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                Absage zurücknehmen
                            </button>
                        @else
                            <div x-show="!open" @click.prevent="open = true">
                                <button type="button"
                                        class="text-xs text-red-600 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                    Absagen
                                </button>
                            </div>
                            <div x-show="open" x-cloak class="flex items-center gap-2">
                                <input type="text" name="note" placeholder="Grund (optional)"
                                       class="text-xs border border-gray-200 rounded px-2 py-1 w-36 focus:outline-none focus:ring-1 focus:ring-primary">
                                <button type="submit"
                                        class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 transition-colors">
                                    Bestätigen
                                </button>
                                <button type="button" @click="open = false"
                                        class="text-xs text-gray-400 hover:text-gray-600">
                                    ✕
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Bestzeiten mit Tabs --}}
        <div id="bestzeiten" class="bg-white rounded-xl shadow-sm border border-gray-100" x-data="{ bestTab: 'alltime' }">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Meine Bestzeiten</h2>
                <a href="{{ route('swimmer.times') }}" class="text-sm text-primary hover:underline">Alle Bestzeiten</a>
            </div>
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

            @php
                $bestsTable = function($bests, $emptyText) {
                    return $bests;
                };
            @endphp

            {{-- Allzeit --}}
            <div x-show="bestTab === 'alltime'">
                @if($allBests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Noch keine Zeiten erfasst.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Bestzeit</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($allBests as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $row->label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">{{ $row->formatted }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            @if($row->source === 'competition')
                                                <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Wettkampf</span>
                                            @elseif($row->source === 'training')
                                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Training</span>
                                            @else
                                                <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">PB</span>
                                            @endif
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
                @if($yearBests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Keine Zeiten in {{ now()->year }}.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Bestzeit {{ now()->year }}</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($yearBests as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $row->label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">{{ $row->formatted }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            @if($row->source === 'competition')
                                                <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Wettkampf</span>
                                            @elseif($row->source === 'training')
                                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Training</span>
                                            @else
                                                <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">PB</span>
                                            @endif
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
                @if($seasonBests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Keine Zeiten in der aktuellen Saison.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Saisonbest</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($seasonBests as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $row->label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">{{ $row->formatted }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            @if($row->source === 'competition')
                                                <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Wettkampf</span>
                                            @elseif($row->source === 'training')
                                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Training</span>
                                            @else
                                                <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">PB</span>
                                            @endif
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
                    @forelse($recent_sessions as $session)
                        @php $diary = $session->diaries->first(); @endphp
                        <a href="{{ route('swimmer.session.show', $session) }}"
                           class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                            <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[50px]">
                                <p class="text-xs font-semibold text-primary">{{ $session->date->format('d.M') }}</p>
                                <p class="text-xs text-primary/60">{{ $session->date->isoFormat('ddd') }}</p>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $session->title }}</p>
                                <p class="text-xs text-gray-500">{{ $session->trainer->name }} · {{ $session->type_label }}</p>
                            </div>
                            {{-- Tagebuch-Badge --}}
                            @if($diary)
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if($diary->mood)
                                        <span class="text-base leading-none">{{ $diary->mood_emoji }}</span>
                                    @endif
                                    @if($diary->perceived_intensity)
                                        <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">
                                            {{ $diary->perceived_intensity }}/10
                                        </span>
                                    @endif
                                    @if(!$diary->mood && !$diary->perceived_intensity)
                                        <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">
                                            <svg class="w-3 h-3 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Tagebuch
                                        </span>
                                    @endif
                                </div>
                            @endif
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
                        @foreach($recent_results as $swim)
                            <a href="{{ route('swimmer.competitions') }}"
                               class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $swim->competition?->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $swim->distance }}m {{ $swim->discipline_label }}
                                        @if($swim->is_final)
                                            <span class="ml-1 bg-purple-100 text-purple-700 px-1 py-0.5 rounded text-xs font-medium">Finale</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono font-bold text-primary text-sm">{{ $swim->formatted_time }}</p>
                                    @if($swim->best_placement)
                                        <p class="text-xs text-gray-500">Platz {{ $swim->best_placement }}</p>
                                    @endif
                                </div>
                                @if($swim->is_personal_best)
                                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">PB</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

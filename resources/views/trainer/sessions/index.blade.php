@extends('layouts.app')
@section('title', 'Trainingseinheiten')
@section('page-title', 'Trainingseinheiten')

@section('content')
<div class="mt-2 space-y-6">
    <div class="flex justify-end">
        <a href="{{ route('trainer.sessions.create') }}"
           class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Neue Einheit
        </a>
    </div>

    @php
        $typeColors = [
            'kondition'      => 'bg-orange-100 text-orange-700',
            'technik'        => 'bg-blue-100 text-blue-700',
            'wettkampf'      => 'bg-red-100 text-red-700',
            'ausdauer'       => 'bg-green-100 text-green-700',
            'krafttraining'  => 'bg-yellow-100 text-yellow-700',
            'physio'         => 'bg-pink-100 text-pink-700',
            'mentaltraining' => 'bg-purple-100 text-purple-700',
            'sonstiges'      => 'bg-gray-100 text-gray-600',
        ];

        // Wochentag-Kürzel Mo–Sa
        $dayLabels = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

        /**
         * Gruppiert eine Collection von Sessions nach Serien (recurrence_group_id).
         * Sortierung: Wochentag (Mo–Sa), dann Startzeit.
         * Rückgabe: Collection von ['rep' => Session, 'sessions' => Collection, 'dow' => int, 'is_series' => bool]
         */
        $buildSeries = function (\Illuminate\Support\Collection $sessions) {
            return $sessions
                ->groupBy(fn($s) => $s->recurrence_group_id ?? ('__single__' . $s->id))
                ->map(function ($group) {
                    $rep = $group->sortBy('date')->first();
                    return [
                        'rep'       => $rep,
                        'sessions'  => $group->sortBy('date'),
                        'dow'       => (int) $rep->date->dayOfWeekIso,   // 1=Mo … 7=So
                        'is_series' => $group->count() > 1,
                    ];
                })
                ->sortBy([['dow', 'asc'], [fn($a) => $a['rep']->start_time, 'asc']]);
        };
    @endphp

    @forelse($groups as $group)
        @php
            $tgc    = \App\Models\TrainingGroup::COLORS[$group->color] ?? \App\Models\TrainingGroup::COLORS['blue'];
            $series = $buildSeries($group->sessions);
        @endphp
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <span class="w-2.5 h-2.5 rounded-full {{ $tgc['dot'] }}"></span>
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">{{ $group->name }}</h2>
                <span class="text-xs text-gray-400">{{ $series->count() }} {{ Str::plural('Serie', $series->count()) }}</span>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-100">
                @foreach($series as $s)
                    @php
                        $rep  = $s['rep'];
                        $all  = $s['sessions'];
                        $isSeries = $s['is_series'];
                        $dow  = $dayLabels[$s['dow']] ?? '';
                    @endphp

                    <details class="group/series">
                        <summary class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors select-none list-none">
                            {{-- Wochentag-Badge --}}
                            <span class="w-9 text-center text-xs font-bold text-primary bg-blue-50 rounded-md py-1 shrink-0">{{ $dow }}</span>

                            {{-- Titel + Uhrzeit --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-gray-800 text-sm">{{ $rep->title }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$rep->type] ?? 'bg-gray-100' }}">
                                        {{ $rep->type_label }}
                                    </span>
                                    @if($isSeries)
                                        <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">
                                            {{ $all->count() }}×
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ substr($rep->start_time, 0, 5) }}@if($rep->end_time) – {{ substr($rep->end_time, 0, 5) }}@endif
                                    @if($rep->duration) · {{ $rep->duration }}@endif
                                    @if($rep->location) · {{ $rep->location }} @endif
                                    @if($isSeries)
                                        · {{ $all->first()->date->format('d.m.Y') }} – {{ $all->last()->date->format('d.m.Y') }}
                                    @else
                                        · {{ $rep->date->format('d.m.Y') }}
                                    @endif
                                </div>
                            </div>

                            {{-- Trainer --}}
                            @php $trainerNames = $rep->coTrainers->map(fn($t) => $t->firstname.' '.$t->lastname)->join(', '); @endphp
                            @if($trainerNames)
                                <span class="text-xs text-gray-400 hidden lg:block shrink-0">{{ $trainerNames }}</span>
                            @endif

                            {{-- Expand-Icon --}}
                            <svg class="w-4 h-4 text-gray-400 shrink-0 group-open/series:rotate-180 transition-transform"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>

                        {{-- Einzelne Iterationen --}}
                        <div class="bg-gray-50 border-t border-gray-100">
                            @foreach($all as $session)
                                @php
                                    $pCount = 0;
                                    foreach ($session->trainingGroups as $tg) {
                                        $pCount += $groupSwimmerCounts[$tg->id] ?? 0;
                                    }
                                    $pCount += $sessionIndividualCounts[$session->id] ?? 0;
                                    if ($session->recurrence_group_id) {
                                        $pCount += $seriesIndividualCounts[$session->recurrence_group_id] ?? 0;
                                    }
                                    $sessPreAbsent = $preAbsentCounts[$session->id] ?? 0;
                                    $overCap = $session->max_participants && $pCount > $session->max_participants;
                                @endphp
                                <div class="flex items-center gap-3 px-5 py-2.5 hover:bg-white transition-colors border-b border-gray-100 last:border-0
                                            {{ $overCap ? 'bg-red-50/60' : '' }}">
                                    <span class="w-9 shrink-0"></span>
                                    <div class="flex-1 min-w-0 text-sm">
                                        <span class="text-gray-700">{{ $session->date->isoFormat('dd, D. MMM YYYY') }}</span>
                                        @if(!$session->trainingPlan)
                                            <span class="ml-1.5 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">kein Plan</span>
                                        @endif
                                        @if($session->has_missing_trainer)
                                            @include('partials.no-trainer-badge')
                                        @endif
                                        @if($overCap)
                                            <span class="ml-1.5 text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full font-semibold">
                                                ⚠ Überfüllt
                                            </span>
                                        @endif
                                    </div>
                                    {{-- Teilnehmer-Zähler --}}
                                    <div class="shrink-0 text-xs {{ $overCap ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                        @if($session->max_participants)
                                            {{ $pCount }}/{{ $session->max_participants }}
                                            @if($sessPreAbsent > 0)
                                                <span class="text-gray-300 font-normal">(–{{ $sessPreAbsent }})</span>
                                            @endif
                                        @elseif($pCount > 0)
                                            {{ $pCount }} TN
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0 text-xs">
                                        <a href="{{ route('trainer.sessions.show', $session) }}"
                                           class="text-primary hover:text-primary-dark font-medium">Details</a>
                                        <a href="{{ route('trainer.sessions.edit', $session) }}"
                                           class="text-gray-500 hover:text-gray-700">Bearbeiten</a>
                                        <form method="POST" action="{{ route('trainer.sessions.destroy', $session) }}"
                                              onsubmit="return confirm('Trainingseinheit löschen?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700">Löschen</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    @empty
    @endforelse

    {{-- Einheiten ohne Gruppe --}}
    @if($ungroupedSessions->isNotEmpty())
        @php $series = $buildSeries($ungroupedSessions); @endphp
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Ohne Gruppe</h2>
                <span class="text-xs text-gray-400">{{ $series->count() }} {{ Str::plural('Serie', $series->count()) }}</span>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-100">
                @foreach($series as $s)
                    @php
                        $rep  = $s['rep'];
                        $all  = $s['sessions'];
                        $isSeries = $s['is_series'];
                        $dow  = $dayLabels[$s['dow']] ?? '';
                    @endphp
                    <details class="group/series">
                        <summary class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors select-none list-none">
                            <span class="w-9 text-center text-xs font-bold text-primary bg-blue-50 rounded-md py-1 shrink-0">{{ $dow }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-gray-800 text-sm">{{ $rep->title }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$rep->type] ?? 'bg-gray-100' }}">{{ $rep->type_label }}</span>
                                    @if($isSeries)
                                        <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">{{ $all->count() }}×</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ substr($rep->start_time, 0, 5) }}@if($rep->end_time) – {{ substr($rep->end_time, 0, 5) }}@endif
                                    @if($rep->duration) · {{ $rep->duration }}@endif
                                    @if($rep->location) · {{ $rep->location }} @endif
                                    @if($isSeries)
                                        · {{ $all->first()->date->format('d.m.Y') }} – {{ $all->last()->date->format('d.m.Y') }}
                                    @else
                                        · {{ $rep->date->format('d.m.Y') }}
                                    @endif
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 shrink-0 group-open/series:rotate-180 transition-transform"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="bg-gray-50 border-t border-gray-100">
                            @foreach($all as $session)
                                @php
                                    $pCount = ($sessionIndividualCounts[$session->id] ?? 0)
                                            + ($session->recurrence_group_id ? ($seriesIndividualCounts[$session->recurrence_group_id] ?? 0) : 0);
                                    $sessPreAbsent = $preAbsentCounts[$session->id] ?? 0;
                                    $overCap = $session->max_participants && $pCount > $session->max_participants;
                                @endphp
                                <div class="flex items-center gap-3 px-5 py-2.5 hover:bg-white transition-colors border-b border-gray-100 last:border-0
                                            {{ $overCap ? 'bg-red-50/60' : '' }}">
                                    <span class="w-9 shrink-0"></span>
                                    <div class="flex-1 text-sm text-gray-700">
                                        {{ $session->date->isoFormat('dd, D. MMM YYYY') }}
                                        @if(!$session->trainingPlan)
                                            <span class="ml-1.5 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">kein Plan</span>
                                        @endif
                                        @if($session->has_missing_trainer) @include('partials.no-trainer-badge') @endif
                                        @if($overCap)
                                            <span class="ml-1.5 text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full font-semibold">⚠ Überfüllt</span>
                                        @endif
                                    </div>
                                    @if($session->max_participants || $pCount > 0)
                                    <div class="shrink-0 text-xs {{ $overCap ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                        @if($session->max_participants)
                                            {{ $pCount }}/{{ $session->max_participants }}
                                        @else
                                            {{ $pCount }} TN
                                        @endif
                                    </div>
                                    @endif
                                    <div class="flex items-center gap-3 shrink-0 text-xs">
                                        <a href="{{ route('trainer.sessions.show', $session) }}" class="text-primary hover:text-primary-dark font-medium">Details</a>
                                        <a href="{{ route('trainer.sessions.edit', $session) }}" class="text-gray-500 hover:text-gray-700">Bearbeiten</a>
                                        <form method="POST" action="{{ route('trainer.sessions.destroy', $session) }}" onsubmit="return confirm('Trainingseinheit löschen?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700">Löschen</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    @endif

    @if($groups->isEmpty() && $ungroupedSessions->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Noch keine Trainingseinheiten vorhanden.
        </div>
    @endif
</div>
@endsection

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
    @endphp

    @forelse($groups as $group)
        @php $tgc = \App\Models\TrainingGroup::COLORS[$group->color] ?? \App\Models\TrainingGroup::COLORS['blue']; @endphp
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <span class="w-2.5 h-2.5 rounded-full {{ $tgc['dot'] }}"></span>
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">{{ $group->name }}</h2>
                <span class="text-xs text-gray-400">{{ $group->sessions->count() }} {{ Str::plural('Einheit', $group->sessions->count()) }}</span>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600">Datum</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600">Einheit</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Typ</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden lg:table-cell">Trainer</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Uhrzeit</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($group->sessions as $session)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3 text-gray-600 whitespace-nowrap">
                                        <div>{{ $session->date->format('d.m.Y') }}</div>
                                        <div class="text-xs text-gray-400">{{ $session->date->isoFormat('dddd') }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-1.5">
                                        <a href="{{ route('trainer.sessions.show', $session) }}"
                                           class="font-medium text-primary hover:underline">{{ $session->title }}</a>
                                        @if($session->has_missing_trainer)
                                            @include('partials.no-trainer-badge')
                                        @endif
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-0.5">
                                            @foreach($session->trainingGroups as $tg)
                                                @php $tc = \App\Models\TrainingGroup::COLORS[$tg->color] ?? \App\Models\TrainingGroup::COLORS['blue']; @endphp
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded-full {{ $tc['badge'] }}">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $tc['dot'] }}"></span>{{ $tg->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                        <p class="text-xs text-gray-400">{{ $session->location }}</p>
                                    </td>
                                    <td class="px-5 py-3 hidden md:table-cell">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$session->type] ?? 'bg-gray-100' }}">
                                            {{ $session->type_label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500 hidden lg:table-cell">{{ $session->coTrainers->map(fn($t) => $t->firstname.' '.$t->lastname)->join(', ') ?: '–' }}</td>
                                    <td class="px-5 py-3 text-gray-500 hidden md:table-cell whitespace-nowrap">
                                        {{ $session->start_time }}
                                        @if($session->end_time) – {{ $session->end_time }} @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2 justify-end">
                                            <a href="{{ route('trainer.sessions.show', $session) }}"
                                               class="text-primary hover:text-primary-dark text-xs font-medium">Details</a>
                                            <a href="{{ route('trainer.sessions.edit', $session) }}"
                                               class="text-gray-500 hover:text-gray-700 text-xs">Bearbeiten</a>
                                            <form method="POST" action="{{ route('trainer.sessions.destroy', $session) }}"
                                                  onsubmit="return confirm('Trainingseinheit löschen?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Löschen</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
    @endforelse

    @if($ungroupedSessions->isNotEmpty())
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Ohne Gruppe</h2>
                <span class="text-xs text-gray-400">{{ $ungroupedSessions->count() }} {{ Str::plural('Einheit', $ungroupedSessions->count()) }}</span>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600">Datum</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600">Einheit</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Typ</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden lg:table-cell">Trainer</th>
                                <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Uhrzeit</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($ungroupedSessions as $session)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3 text-gray-600 whitespace-nowrap">
                                        <div>{{ $session->date->format('d.m.Y') }}</div>
                                        <div class="text-xs text-gray-400">{{ $session->date->isoFormat('dddd') }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-1.5">
                                        <a href="{{ route('trainer.sessions.show', $session) }}"
                                           class="font-medium text-primary hover:underline">{{ $session->title }}</a>
                                        @if($session->has_missing_trainer)
                                            @include('partials.no-trainer-badge')
                                        @endif
                                        </div>
                                        <p class="text-xs text-gray-400">{{ $session->location }}</p>
                                    </td>
                                    <td class="px-5 py-3 hidden md:table-cell">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$session->type] ?? 'bg-gray-100' }}">
                                            {{ $session->type_label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500 hidden lg:table-cell">{{ $session->coTrainers->map(fn($t) => $t->firstname.' '.$t->lastname)->join(', ') ?: '–' }}</td>
                                    <td class="px-5 py-3 text-gray-500 hidden md:table-cell whitespace-nowrap">
                                        {{ $session->start_time }}
                                        @if($session->end_time) – {{ $session->end_time }} @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2 justify-end">
                                            <a href="{{ route('trainer.sessions.show', $session) }}"
                                               class="text-primary hover:text-primary-dark text-xs font-medium">Details</a>
                                            <a href="{{ route('trainer.sessions.edit', $session) }}"
                                               class="text-gray-500 hover:text-gray-700 text-xs">Bearbeiten</a>
                                            <form method="POST" action="{{ route('trainer.sessions.destroy', $session) }}"
                                                  onsubmit="return confirm('Trainingseinheit löschen?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Löschen</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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

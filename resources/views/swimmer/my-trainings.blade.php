@extends('layouts.app')
@section('title', 'Meine Trainings')
@section('page-title', 'Meine Trainings')

@section('content')
<div class="mt-2 space-y-6">

    {{-- Beteiligung --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center gap-6">
            <div>
                <p class="text-xs text-gray-500">Absolvierte Einheiten</p>
                <p class="text-2xl font-bold text-primary">{{ $totalAttended }} <span class="text-sm font-normal text-gray-400">/ {{ $totalSessions }}</span></p>
            </div>
            <div class="flex-1 max-w-xs">
                <p class="text-xs text-gray-500 mb-1">Beteiligung gesamt</p>
                <div class="bg-gray-100 rounded-full h-3">
                    <div class="bg-primary h-3 rounded-full" style="width: {{ $pct }}%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">{{ $pct }} %</p>
            </div>
        </div>
    </div>

    {{-- Trainings-Liste --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Alle absolvierten Trainings</h2>
        </div>

        @if($sessions->isEmpty())
            <p class="text-sm text-gray-400 px-5 py-10 text-center">Noch keine Trainingseinheiten absolviert.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($sessions as $session)
                    @php $diary = $session->diaries->first(); @endphp
                    <a href="{{ route('swimmer.session.show', $session) }}"
                       class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[56px]">
                            <p class="text-xs font-bold text-primary">{{ $session->date->format('d.M') }}</p>
                            <p class="text-xs text-primary/60">{{ $session->date->format('Y') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $session->title }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $session->date->isoFormat('dddd') }}
                                @if($session->start_time) · {{ $session->start_time }} Uhr @endif
                                · {{ $session->trainer->name }}
                                · <span class="inline-block px-1.5 py-0.5 rounded-full text-xs {{ $session->type_color }}">{{ $session->type_label }}</span>
                            </p>
                        </div>
                        {{-- Tagebuch-Badge --}}
                        @if($diary)
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                @if($diary->mood)
                                    <span class="text-base leading-none" title="{{ $diary->mood_label }}">{{ $diary->mood_emoji }}</span>
                                @endif
                                @if($diary->perceived_intensity)
                                    <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">
                                        {{ $diary->perceived_intensity }}/10
                                    </span>
                                @endif
                                @if(!$diary->mood && !$diary->perceived_intensity && $diary->body)
                                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">Tagebuch</span>
                                @endif
                            </div>
                        @endif
                        <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            </div>

            <div class="p-5 border-t border-gray-50">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>

    <a href="{{ route('swimmer.dashboard') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zum Dashboard
    </a>
</div>
@endsection

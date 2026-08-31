@extends('layouts.app')
@section('title', 'Motto der Woche – Trainer')
@section('page-title', 'Motto der Woche')

@section('content')
<div class="mt-2 space-y-6">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    @if($currentWeeks->isEmpty() && $upcomingWeeks->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <svg class="w-16 h-16 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            <p class="text-gray-500 font-medium">Keine Motto-Wochen gefunden.</p>
            <p class="text-sm text-gray-400 mt-1">Aktiviere das Feature für eine Gruppe und generiere die Saison-Wochen im Admin-Bereich.</p>
        </div>
    @else

    {{-- ── Aktuelle Woche ──────────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Aktuelle Woche (KW {{ $monday->weekOfYear }})</h2>
        @if($currentWeeks->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
                <p class="text-sm text-gray-400">Für diese Woche sind keine Motto-Wochen eingetragen.</p>
            </div>
        @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($currentWeeks as $week)
            @php $gc = \App\Models\TrainingGroup::COLORS[$week->group->color ?? 'blue'] ?? \App\Models\TrainingGroup::COLORS['blue']; @endphp
            <div class="bg-white rounded-xl shadow-sm border {{ $week->motto ? 'border-green-200' : 'border-amber-200' }} p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $gc['badge'] }}">{{ $week->group->name }}</span>
                        <p class="text-xs text-gray-400 mt-1">{{ $week->week_start->format('d.m.Y') }}</p>
                    </div>
                    @if($week->motto)
                        <span class="text-xs bg-green-100 text-green-700 font-medium px-2.5 py-1 rounded-full shrink-0">Eingetragen</span>
                    @else
                        <span class="text-xs bg-amber-100 text-amber-700 font-medium px-2.5 py-1 rounded-full shrink-0">Fehlt</span>
                    @endif
                </div>

                <div x-data="{ editing: false }">
                    @if($week->motto)
                        <blockquote class="border-l-4 border-primary pl-4" x-show="!editing">
                            <p class="text-sm font-medium text-gray-800 leading-relaxed">"{{ $week->motto }}"</p>
                            @if($week->user)
                                <p class="text-xs text-gray-500 mt-1.5">— {{ $week->user->firstname }} {{ $week->user->lastname }}</p>
                            @endif
                        </blockquote>
                    @else
                        <div class="space-y-2" x-show="!editing">
                            @if($week->generated_motto)
                                <div class="bg-violet-50 rounded-lg p-3 border border-violet-100">
                                    <p class="text-xs text-violet-600 font-semibold mb-1">KI-Vorschlag:</p>
                                    <p class="text-sm text-violet-800">"{{ $week->generated_motto }}"</p>
                                </div>
                            @endif
                            <div class="bg-amber-50 rounded-lg p-3 border border-amber-100">
                                @if($week->user)
                                    <p class="text-xs text-amber-800">
                                        <span class="font-semibold">{{ $week->user->firstname }} {{ $week->user->lastname }}</span>
                                        hat noch kein Motto eingetragen.
                                    </p>
                                @else
                                    <p class="text-xs text-amber-800">Niemand für diese Woche zugewiesen.</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('trainer.motto.activate', $week) }}">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                    {{ $week->generated_motto ? 'KI-Motto aktivieren' : 'KI-Motto generieren & aktivieren' }}
                                </button>
                            </form>
                        </div>
                    @endif

                    {{-- Inline-Edit --}}
                    <button @click="editing = !editing" type="button" x-show="!editing"
                            class="mt-3 text-xs text-primary hover:underline flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ $week->motto ? 'Motto bearbeiten' : 'Motto direkt eintragen' }}
                    </button>
                    <form method="POST" action="{{ route('trainer.motto.save', $week) }}"
                          x-show="editing" x-transition class="mt-3 space-y-2">
                        @csrf
                        <textarea name="motto" rows="3" maxlength="500" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                                  placeholder="Motto der Woche…">{{ $week->motto }}</textarea>
                        <div class="flex items-center gap-2">
                            <button type="submit"
                                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors">
                                Speichern
                            </button>
                            <button type="button" @click="editing = false"
                                    class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                                Abbrechen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── Nächste 4 Wochen ────────────────────────────────────────────────────── --}}
    @if($upcomingWeeks->isNotEmpty())
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Nächste 4 Wochen</h2>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-50">
            @foreach($upcomingWeeks as $week)
            @php
                $gc   = \App\Models\TrainingGroup::COLORS[$week->group->color ?? 'blue'] ?? \App\Models\TrainingGroup::COLORS['blue'];
                $days = $week->daysUntilStart();
            @endphp
            <div x-data="{ editing: false }">
            <div class="p-5 flex items-start gap-4 flex-wrap" x-show="!editing">
                {{-- Week Info --}}
                <div class="min-w-[120px]">
                    <p class="font-semibold text-gray-800 text-sm">KW {{ $week->week_start->weekOfYear }}</p>
                    <p class="text-xs text-gray-400">{{ $week->week_start->format('d.m.Y') }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">In {{ $days }} {{ $days === 1 ? 'Tag' : 'Tagen' }}</p>
                </div>

                {{-- Group + Person --}}
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $gc['badge'] }}">{{ $week->group->name }}</span>
                    @if($week->user)
                        <p class="text-sm text-gray-700 mt-1">{{ $week->user->firstname }} {{ $week->user->lastname }}</p>
                    @else
                        <p class="text-sm text-gray-400 italic mt-1">Niemand zugewiesen</p>
                    @endif
                </div>

                {{-- Status + Action --}}
                <div class="shrink-0 text-right space-y-2">
                    @if($week->motto)
                        <span class="inline-block text-xs bg-green-100 text-green-700 font-medium px-2.5 py-1 rounded-full">
                            Motto eingetragen
                        </span>
                        <p class="text-xs text-gray-500 max-w-xs text-left line-clamp-2">"{{ $week->motto }}"</p>
                    @else
                        <div class="flex items-center gap-3 justify-end">
                            @if($days <= 7)
                                <span class="text-xs bg-red-100 text-red-600 font-medium px-2.5 py-1 rounded-full">Kein Motto!</span>
                            @else
                                <span class="text-xs bg-amber-100 text-amber-600 font-medium px-2.5 py-1 rounded-full">Ausstehend</span>
                            @endif
                            <form method="POST" action="{{ route('trainer.motto.activate', $week) }}">
                                @csrf
                                <button type="submit"
                                        class="flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 text-white text-xs font-medium rounded-lg hover:bg-violet-700 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                    {{ $week->generated_motto ? 'KI aktivieren' : 'KI generieren' }}
                                </button>
                            </form>
                        </div>
                    @endif
                    <button @click="editing = true" type="button"
                            class="mt-1 text-xs text-primary hover:underline flex items-center gap-1 ml-auto">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ $week->motto ? 'Bearbeiten' : 'Direkt eintragen' }}
                    </button>
                </div>
            </div>
            {{-- Inline-Edit --}}
            <div class="px-5 pb-5" x-show="editing" x-transition>
                <form method="POST" action="{{ route('trainer.motto.save', $week) }}" class="space-y-2">
                    @csrf
                    <textarea name="motto" rows="3" maxlength="500" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                              placeholder="Motto der Woche…">{{ $week->motto }}</textarea>
                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors">
                            Speichern
                        </button>
                        <button type="button" @click="editing = false"
                                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @endif {{-- end not empty --}}

</div>
@endsection

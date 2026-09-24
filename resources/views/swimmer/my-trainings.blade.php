@extends('layouts.app')
@section('title', 'Mein Training')
@section('page-title', 'Mein Training')

@section('content')
<div class="mt-2 space-y-6">

    {{-- ── Statistik ──────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap gap-6 items-center">

            {{-- Teilnahme-Quote --}}
            <div class="flex items-center gap-4 flex-1 min-w-[200px]">
                <div class="relative w-16 h-16 flex-shrink-0">
                    <svg class="w-16 h-16 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f3f4f6" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor"
                                class="text-primary"
                                stroke-width="3"
                                stroke-dasharray="{{ $pct }}, 100"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-sm font-bold text-primary">{{ $pct }}%</span>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">{{ $totalAttended }} von {{ $totalRelevant }}</p>
                    <p class="text-xs text-gray-400">Trainings absolviert</p>
                </div>
            </div>

            <div class="hidden sm:block w-px h-10 bg-gray-100"></div>

            {{-- Ausstehende Tagebücher --}}
            <div class="flex items-center gap-3">
                @if($diaryPendingCount > 0)
                    <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-amber-700">{{ $diaryPendingCount }}</p>
                        <p class="text-xs text-gray-400">Tagebuch offen</p>
                    </div>
                @else
                    <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Vollständig</p>
                        <p class="text-xs text-gray-400">Tagebuch</p>
                    </div>
                @endif
            </div>

            <div class="hidden sm:block w-px h-10 bg-gray-100"></div>

            {{-- Bevorstehende Absagen --}}
            <div class="flex items-center gap-3">
                @php $absenceCount = $preAbsenceMap->count(); @endphp
                @if($absenceCount > 0)
                    <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-red-600">{{ $absenceCount }}</p>
                        <p class="text-xs text-gray-400">Vorab abgesagt</p>
                    </div>
                @else
                    <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">{{ $upcoming->count() }}</p>
                        <p class="text-xs text-gray-400">Bevorstehend</p>
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- ── Trainingsplanung ────────────────────────────────────────────────── --}}
    @if($trainingSeries->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
         x-data="collapsibleBlock('mytraining-series')">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-700">Trainingsplanung</h2>
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ $trainingSeries->count() }} Serien</span>
                @if($excludedSeriesIds->isNotEmpty())
                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">{{ $excludedSeriesIds->count() }} dauerhaft abgesagt</span>
                @endif
            </div>
            <button type="button" @click="toggle()" class="p-1 text-gray-400 hover:text-gray-600" :aria-expanded="open ? 'true' : 'false'">
                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>

        <div x-show="open" x-cloak class="divide-y divide-gray-50">
            @foreach($trainingSeries as $series)
            <div x-data="{ showExcludeForm: false, showPunctual: false }" class="px-4 py-3 {{ $series->is_excluded ? 'bg-red-50/30' : '' }}">

                {{-- Main row --}}
                <div class="flex items-center gap-3 flex-wrap">
                    {{-- Day badge --}}
                    <span class="flex-shrink-0 w-8 text-center text-xs font-bold {{ $series->is_excluded ? 'text-red-400' : 'text-primary' }}">
                        {{ $series->day_label }}
                    </span>

                    {{-- Title + type --}}
                    <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $series->type_color }} {{ $series->is_excluded ? 'opacity-50' : '' }}">
                        {{ $series->title }}
                    </span>

                    {{-- Time --}}
                    @if($series->start_time)
                        <span class="text-xs text-gray-500 flex-shrink-0">
                            {{ $series->start_time }}@if($series->end_time) – {{ $series->end_time }}@endif Uhr
                        </span>
                    @endif

                    {{-- Groups --}}
                    @if($series->groups->isNotEmpty())
                        <span class="text-xs text-gray-400 flex-shrink-0">{{ $series->groups->pluck('name')->join(', ') }}</span>
                    @endif

                    <div class="flex-1"></div>

                    @if($series->is_excluded)
                        {{-- Excluded: show status + action buttons --}}
                        <span class="text-xs font-semibold text-red-600 bg-red-100 px-2 py-0.5 rounded-full flex-shrink-0">Dauerhafte Absage</span>
                        <form method="POST" action="{{ route('swimmer.series.include', $series->recurrence_group_id) }}" class="flex-shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-green-700 border border-green-300 px-3 py-1 rounded-lg hover:bg-green-50 transition-colors">
                                Dauerhaft zusagen
                            </button>
                        </form>
                        <button type="button" @click="showPunctual = !showPunctual"
                                class="flex-shrink-0 text-xs border px-3 py-1 rounded-lg transition-colors"
                                :class="showPunctual ? 'text-primary border-primary/40 bg-primary/5' : 'text-gray-500 border-gray-200 hover:bg-gray-50'">
                            Punktuell zusagen
                        </button>
                    @else
                        {{-- Active: show exclude toggle --}}
                        <button type="button" @click="showExcludeForm = !showExcludeForm"
                                class="flex-shrink-0 text-xs border px-3 py-1 rounded-lg transition-colors"
                                :class="showExcludeForm ? 'text-red-600 border-red-300 bg-red-50' : 'text-gray-400 border-gray-200 hover:bg-gray-50'">
                            <span x-text="showExcludeForm ? 'Abbrechen' : 'Ausblenden'">Ausblenden</span>
                        </button>
                    @endif
                </div>

                {{-- Exclusion comment display (when excluded) --}}
                @if($series->is_excluded && $series->exclusion_comment)
                    <p class="mt-1.5 ml-11 text-xs text-red-500">Grund: {{ $series->exclusion_comment }}</p>
                @endif

                {{-- Exclude form (when not excluded, toggled) --}}
                @if(!$series->is_excluded)
                    <div x-show="showExcludeForm" x-cloak class="mt-3 ml-11">
                        <form method="POST" action="{{ route('swimmer.series.exclude', $series->recurrence_group_id) }}"
                              class="space-y-2">
                            @csrf
                            <textarea name="comment" rows="2" placeholder="Grund der dauerhaften Absage (optional)"
                                      class="w-full px-3 py-2 border border-red-200 rounded-lg text-sm focus:ring-2 focus:ring-red-300 outline-none resize-none bg-white"></textarea>
                            <button type="submit"
                                    class="text-sm text-red-600 border border-red-300 px-4 py-1.5 rounded-lg hover:bg-red-50 transition-colors font-medium">
                                Dauerhafte Absage bestätigen
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Punctual join (upcoming sessions of excluded series) --}}
                @if($series->is_excluded)
                    <div x-show="showPunctual" x-cloak class="mt-3 ml-11">
                        @if($series->upcoming_sessions && $series->upcoming_sessions->isNotEmpty())
                            <p class="text-xs text-gray-500 mb-2">Bevorstehende Einheiten dieser Serie – wähle eine, um punktuell teilzunehmen:</p>
                            <div class="space-y-1.5">
                                @foreach($series->upcoming_sessions as $upSession)
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-gray-600 w-36 flex-shrink-0">
                                            {{ $upSession->date->isoFormat('ddd, DD.MM.YYYY') }}
                                            @if($upSession->start_time) · {{ substr($upSession->start_time, 0, 5) }} Uhr @endif
                                        </span>
                                        <form method="POST" action="{{ route('swimmer.session.punctual.join', $upSession) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="text-xs text-primary border border-primary/30 px-3 py-1 rounded-lg hover:bg-primary/5 transition-colors">
                                                Beitreten
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-400">Keine bevorstehenden Einheiten dieser Serie.</p>
                        @endif
                    </div>
                @endif

            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Gasttraining ─────────────────────────────────────────────────────── --}}
    @if($guestSessions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-purple-100 overflow-hidden"
         x-data="collapsibleBlock('mytraining-guest')">
        <div class="px-5 py-3 bg-purple-50 border-b border-purple-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-purple-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <h2 class="text-sm font-semibold text-purple-700">Gasttraining verfügbar</h2>
            <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full font-semibold">{{ $guestSessions->count() }}</span>
            <span class="text-xs text-purple-400 ml-1">– Freie Plätze in anderen Gruppen</span>
            <button type="button" @click="toggle()" class="ml-auto p-1 text-purple-400 hover:text-purple-600" :aria-expanded="open ? 'true' : 'false'">
                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
        <div x-show="open" x-cloak class="divide-y divide-gray-50">
            @foreach($guestSessions as $g)
            @php $s = $g->session; @endphp
            <div class="px-4 py-3 flex items-center gap-3 flex-wrap {{ $g->booked ? 'bg-purple-50/40' : '' }}">
                <div class="flex-shrink-0 text-center">
                    <div class="text-xs font-bold text-gray-500">{{ $s->date->isoFormat('ddd') }}</div>
                    <div class="text-sm font-bold text-gray-800">{{ $s->date->format('d.m.') }}</div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-gray-800">{{ $s->title }}</span>
                        @if($s->start_time)
                            <span class="text-xs text-gray-400">{{ substr($s->start_time,0,5) }}@if($s->end_time) – {{ substr($s->end_time,0,5) }}@endif Uhr</span>
                        @endif
                        @if($s->location)
                            <span class="text-xs text-gray-400">· {{ $s->location }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        @if($g->booked)
                            <span class="text-xs text-purple-600 font-semibold bg-purple-100 px-2 py-0.5 rounded-full">Gebucht ✓</span>
                        @else
                            <span class="text-xs text-green-700 font-medium bg-green-100 px-2 py-0.5 rounded-full">
                                {{ $g->spots }} freie {{ $g->spots === 1 ? 'Platz' : 'Plätze' }}
                            </span>
                        @endif
                        @if($s->trainingGroups->isNotEmpty())
                            <span class="text-xs text-gray-400">Hauptgruppe: {{ $s->trainingGroups->pluck('name')->join(', ') }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex-shrink-0 flex gap-2">
                    @if($g->booked)
                        <form method="POST" action="{{ route('swimmer.session.cancel-guest', $s) }}">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-xs border border-red-200 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                Stornieren
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('swimmer.session.book-guest', $s) }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs bg-purple-600 hover:bg-purple-700 text-white font-semibold px-4 py-1.5 rounded-lg transition-colors">
                                Platz buchen
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-2.5 bg-purple-50/50 border-t border-purple-100">
            <p class="text-xs text-purple-400">
                Du erhältst automatisch eine E-Mail, wenn durch Absagen weitere Plätze frei werden. Buchungen erfolgen nach dem First-come-first-served-Prinzip.
            </p>
        </div>
    </div>
    @endif

    {{-- ── Bevorstehende Trainings (nächste 2 Wochen) ────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
         x-data="collapsibleBlock('mytraining-upcoming')">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2 flex-wrap">
            <h2 class="text-sm font-semibold text-gray-700">Bevorstehende Trainings</h2>
            <span class="text-xs text-gray-400">nächste 2 Wochen</span>
            @if($upcoming->count())
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold">{{ $upcoming->count() }}</span>
            @endif
            <button type="button" @click="toggle()" class="ml-auto p-1 text-gray-400 hover:text-gray-600" :aria-expanded="open ? 'true' : 'false'">
                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>

        <div x-show="open" x-cloak>
        @if($upcoming->isEmpty())
            <p class="text-sm text-gray-400 text-center py-8">Keine Einheiten in den nächsten 2 Wochen.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($upcoming as $session)
                    @php
                        $absence    = $preAbsenceMap->get($session->id);
                        $isAbsent   = $absence !== null;
                        $isRegistered = $myRegistrations->contains($session->id);
                        $regOpen    = $session->registration_open;
                        $spots      = $session->remainingSpots();
                        $noSpots    = $regOpen && $spots !== null && $spots <= 0 && !$isRegistered;
                    @endphp
                    <div x-data="{ showNote: false }" class="px-4 py-3 {{ $isAbsent ? 'bg-red-50/40' : ($regOpen ? 'bg-green-50/30' : '') }}">
                        <div class="flex items-start gap-3">

                            {{-- Date block --}}
                            <div class="text-center rounded-lg p-2 min-w-[52px] flex-shrink-0 {{ $isAbsent ? 'bg-red-100' : ($regOpen ? 'bg-green-100' : 'bg-primary/10') }}">
                                <p class="text-xs font-bold {{ $isAbsent ? 'text-red-600' : ($regOpen ? 'text-green-700' : 'text-primary') }}">{{ $session->date->format('d.M') }}</p>
                                <p class="text-[10px] {{ $isAbsent ? 'text-red-400' : ($regOpen ? 'text-green-500' : 'text-primary/60') }}">{{ $session->date->isoFormat('ddd') }}</p>
                            </div>

                            {{-- Session info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                    <p class="text-sm font-medium text-gray-800">{{ $session->title }}</p>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full {{ $session->type_color }}">{{ $session->type_label }}</span>
                                    @if($isAbsent)
                                        <span class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-semibold">Abgesagt</span>
                                    @endif
                                    @if($regOpen)
                                        <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-semibold">
                                            Anmeldung offen{{ $spots !== null ? ' · '.$spots.' Plätze frei' : '' }}
                                        </span>
                                    @endif
                                    @if($isRegistered)
                                        <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold">Angemeldet</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500">
                                    {{ $session->start_time }}@if($session->end_time) – {{ $session->end_time }}@endif Uhr
                                    · {{ $session->location }}
                                    · {{ $session->trainer?->name ?? '–' }}
                                </p>
                                @if($isAbsent && $absence->pre_absent_note)
                                    <p class="text-xs text-red-500 mt-0.5">Grund: {{ $absence->pre_absent_note }}</p>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="flex-shrink-0 flex flex-col items-end gap-1.5">
                                @if($regOpen && !$isAbsent)
                                    @if($isRegistered)
                                        <form method="POST" action="{{ route('swimmer.session.unregister', $session) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-blue-600 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors">
                                                Abmelden
                                            </button>
                                        </form>
                                    @elseif(!$noSpots)
                                        <form method="POST" action="{{ route('swimmer.session.register', $session) }}">
                                            @csrf
                                            <button type="submit" class="text-xs text-white bg-green-600 hover:bg-green-700 px-3 py-1.5 rounded-lg transition-colors font-semibold">
                                                Anmelden
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400 border border-gray-200 px-3 py-1.5 rounded-lg">Ausgebucht</span>
                                    @endif
                                @endif

                                @if($isAbsent)
                                    <form method="POST" action="{{ route('swimmer.session.cancel', $session) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                                            Zurücknehmen
                                        </button>
                                    </form>
                                @else
                                    <button type="button" @click="showNote = !showNote"
                                            class="text-xs text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                                            x-text="showNote ? 'Abbrechen' : 'Absagen'">
                                        Absagen
                                    </button>
                                @endif
                            </div>

                        </div>

                        @if(!$isAbsent)
                            <div x-show="showNote" x-cloak class="mt-3 ml-[64px]">
                                <form method="POST" action="{{ route('swimmer.session.cancel', $session) }}"
                                      class="flex items-center gap-2 flex-wrap">
                                    @csrf
                                    <input type="text" name="note" placeholder="Grund der Absage (optional)"
                                           class="flex-1 text-sm px-3 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary outline-none min-w-[160px]">
                                    <button type="submit"
                                            class="text-sm text-red-600 border border-red-200 px-4 py-1.5 rounded-lg hover:bg-red-50 transition-colors flex-shrink-0 font-medium">
                                        Absage bestätigen
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        @if($upcomingLaterCount > 0)
            <div class="px-5 py-3 border-t border-gray-50 text-center">
                <p class="text-xs text-gray-400">+ {{ $upcomingLaterCount }} weitere {{ $upcomingLaterCount === 1 ? 'Einheit' : 'Einheiten' }} in den Folgewochen</p>
            </div>
        @endif
        </div>
    </div>

    {{-- ── Selbsteinschätzung ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
         x-data="collapsibleBlock('mytraining-assessment')">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-700">Selbsteinschätzung</h2>
                <span class="text-xs text-gray-400">{{ $pastWindowLabel }}</span>
                <button type="button" @click="toggle()" class="p-1 text-gray-400 hover:text-gray-600" :aria-expanded="open ? 'true' : 'false'">
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- Filter tabs --}}
                <div class="flex gap-1 p-1 bg-white border border-gray-200 rounded-lg text-xs">
                    <a href="{{ route('swimmer.sessions', ['filter' => 'all', 'past_page' => 1]) }}"
                       class="px-2.5 py-1 rounded font-medium transition-colors {{ $filter === 'all' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-700' }}">
                        Alle
                    </a>
                    <a href="{{ route('swimmer.sessions', ['filter' => 'attended', 'past_page' => 1]) }}"
                       class="px-2.5 py-1 rounded font-medium transition-colors {{ $filter === 'attended' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-700' }}">
                        Anwesend
                    </a>
                </div>
                {{-- Window navigation --}}
                <div class="flex gap-1">
                    @if($pastHasNewer)
                        <a href="{{ route('swimmer.sessions', ['filter' => $filter, 'past_page' => $pastPage - 1]) }}"
                           class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            Neuer
                        </a>
                    @endif
                    @if($pastHasOlder)
                        <a href="{{ route('swimmer.sessions', ['filter' => $filter, 'past_page' => $pastPage + 1]) }}"
                           class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                            Älter
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div x-show="open" x-cloak>
        @if($pastSessions->isEmpty())
            <p class="text-sm text-gray-400 text-center py-10">Keine Einheiten in diesem Zeitraum.</p>
        @else
            @php
                $sessionsByMonth = $pastSessions->groupBy(fn($s) => $s->date->format('Y-m'));
            @endphp
            <div class="divide-y divide-gray-50">
                @foreach($sessionsByMonth as $monthKey => $monthSessions)
                    {{-- Month header --}}
                    <div class="px-5 py-2 bg-gray-50/70 border-b border-gray-100">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            {{ \Carbon\Carbon::parse($monthKey . '-01')->isoFormat('MMMM YYYY') }}
                        </span>
                    </div>

                    @foreach($monthSessions as $session)
                        @php
                            $att       = $session->attendances->first();
                            $isPresent = $att?->attended === true;
                            // Trainer documented non-attendance without swimmer cancellation
                            $trainerAbsent = $att !== null && $att->attended === false && !$att->pre_absent;
                            $diary     = $session->diaries->first();
                        @endphp
                        <div x-data="{ diaryOpen: false }" class="px-4 py-3">

                            <div class="flex items-start gap-3">

                                {{-- Attendance indicator --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($isPresent)
                                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center" title="Anwesend (bestätigt)">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                    @elseif($trainerAbsent)
                                        <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center" title="Vom Trainer als abwesend markiert">
                                            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center" title="Keine Erfassung">
                                            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                {{-- Date + session info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                        <span class="text-xs text-gray-400 font-medium">{{ $session->date->format('d.m.Y') }}</span>
                                        <span class="text-gray-200">·</span>
                                        <span class="text-xs text-gray-400">{{ $session->date->isoFormat('ddd') }}</span>
                                        <span class="text-xs px-1.5 py-0.5 rounded-full {{ $session->type_color }}">{{ $session->type_label }}</span>
                                        @if($trainerAbsent)
                                            <span class="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full font-semibold">Unentschuldigt gefehlt</span>
                                        @endif
                                    </div>
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $session->title }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $session->start_time }}@if($session->end_time) – {{ $session->end_time }}@endif Uhr
                                        · {{ $session->location }}
                                        · {{ $session->trainer?->name ?? '–' }}
                                    </p>

                                    @if($diary && $diary->self_score !== null)
                                        @php
                                            $sc = $diary->self_score;
                                            $scClass = $sc >= 8 ? 'bg-green-100 text-green-700' : ($sc >= 5 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
                                        @endphp
                                        <div class="mt-1.5 flex items-center gap-1.5">
                                            <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $scClass }}">{{ $sc }}/10</span>
                                            <span class="text-xs text-gray-400">Selbsteinschätzung</span>
                                            @if($diary->trainer_score !== null)
                                                <span class="text-xs text-gray-400">· Trainer: {{ $diary->trainer_score }}/10</span>
                                            @endif
                                        </div>
                                    @elseif($isPresent)
                                        <div class="mt-1.5">
                                            <span class="text-xs bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full font-medium">Noch nicht bewertet</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Selbsteinschätzung toggle (only for attended sessions) --}}
                                @if($isPresent)
                                    <button type="button" @click="diaryOpen = !diaryOpen"
                                            class="flex-shrink-0 flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border transition-colors"
                                            :class="diaryOpen ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        <span x-text="diaryOpen ? 'Schließen' : '{{ ($diary && $diary->self_score !== null) ? 'Bearbeiten' : 'Bewerten' }}'">{{ ($diary && $diary->self_score !== null) ? 'Bearbeiten' : 'Bewerten' }}</span>
                                    </button>
                                @endif

                            </div>

                            {{-- Inline Selbsteinschätzung --}}
                            @if($isPresent)
                                <div x-show="diaryOpen" x-cloak
                                     class="mt-3 ml-11 border border-gray-100 rounded-xl overflow-hidden">
                                    <form method="POST" action="{{ route('sessions.diary', $session) }}"
                                          x-data="scoreKnob({{ $diary?->self_score ?? 5 }})"
                                          @submit="$el.querySelector('[name=self_score]').value = value"
                                          class="p-4 bg-gray-50/60">
                                        @csrf

                                        <div class="flex flex-col items-center gap-3">
                                            {{-- Drehegler --}}
                                            <div class="select-none touch-none"
                                                 @mousedown.prevent="startDrag($event)"
                                                 @mousemove.window.prevent="onDrag($event)"
                                                 @mouseup.window="stopDrag()"
                                                 @touchstart.prevent="startDrag($event)"
                                                 @touchmove.window.prevent="onDrag($event)"
                                                 @touchend.window="stopDrag()">
                                                <svg viewBox="0 0 120 120" class="w-28 h-28 cursor-grab active:cursor-grabbing" style="filter:drop-shadow(0 2px 8px rgba(0,0,0,.08))">
                                                    <path :d="trackPath" fill="none" stroke="#e5e7eb" stroke-width="10" stroke-linecap="round"/>
                                                    <path :d="valuePath" fill="none" :stroke="arcColor" stroke-width="10" stroke-linecap="round"/>
                                                    <text x="60" y="55" text-anchor="middle" dominant-baseline="middle"
                                                          class="font-bold" style="font-size:28px;font-family:inherit" :fill="arcColor" x-text="value"></text>
                                                    <text x="60" y="78" text-anchor="middle" style="font-size:11px;fill:#9ca3af;font-family:inherit">von 10</text>
                                                </svg>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <button type="button" @click="decrement()"
                                                        class="w-8 h-8 rounded-full border border-gray-200 text-gray-600 text-lg font-bold hover:bg-gray-50 transition flex items-center justify-center">−</button>
                                                <span class="text-xs text-gray-400 w-24 text-center" x-text="label"></span>
                                                <button type="button" @click="increment()"
                                                        class="w-8 h-8 rounded-full border border-gray-200 text-gray-600 text-lg font-bold hover:bg-gray-50 transition flex items-center justify-center">+</button>
                                            </div>

                                            <input type="hidden" name="self_score" :value="value">

                                            <button type="submit"
                                                    class="bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-5 py-2 rounded-lg transition-colors">
                                                {{ ($diary && $diary->self_score !== null) ? 'Aktualisieren' : 'Speichern' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif

                        </div>
                    @endforeach
                @endforeach
            </div>

            {{-- Window navigation footer --}}
            @if($pastHasNewer || $pastHasOlder)
                <div class="px-5 py-3 border-t border-gray-100 flex justify-between items-center">
                    @if($pastHasNewer)
                        <a href="{{ route('swimmer.sessions', ['filter' => $filter, 'past_page' => $pastPage - 1]) }}"
                           class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-primary transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            Neuere Einheiten
                        </a>
                    @else
                        <span></span>
                    @endif
                    @if($pastHasOlder)
                        <a href="{{ route('swimmer.sessions', ['filter' => $filter, 'past_page' => $pastPage + 1]) }}"
                           class="inline-flex items-center gap-1 text-xs text-gray-600 hover:text-primary transition-colors">
                            Ältere Einheiten
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                </div>
            @endif
        @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
/**
 * Klappbare Bloecke. Der Zustand wird je Block im Browser gemerkt, damit man
 * nach jedem Seitenwechsel nicht wieder alles zuklappen muss. Der Zugriff auf
 * den Speicher ist abgesichert - in privaten Fenstern wirft er, und dann soll
 * der Block trotzdem funktionieren.
 */
function collapsibleBlock(key, standard = true) {
    return {
        open: standard,
        init() {
            try {
                const saved = localStorage.getItem('card:' + key);
                if (saved !== null) this.open = saved === '1';
            } catch (e) {}
        },
        toggle() {
            this.open = !this.open;
            try { localStorage.setItem('card:' + key, this.open ? '1' : '0'); } catch (e) {}
        },
    };
}
</script>
<script>
function scoreKnob(initial) {
    const CX = 60, CY = 60, R = 44;
    const START_DEG = 135, RANGE_DEG = 270;

    function polar(deg) {
        const rad = (deg - 90) * Math.PI / 180;
        return { x: CX + R * Math.cos(rad), y: CY + R * Math.sin(rad) };
    }

    function arcPath(fromDeg, toDeg) {
        const s = polar(fromDeg), e = polar(toDeg);
        const large = (toDeg - fromDeg) > 180 ? 1 : 0;
        return `M ${s.x.toFixed(2)} ${s.y.toFixed(2)} A ${R} ${R} 0 ${large} 1 ${e.x.toFixed(2)} ${e.y.toFixed(2)}`;
    }

    const LABELS = ['Sehr niedrig','Sehr niedrig','Niedrig','Niedrig','Mittel','Mittel','Gut','Gut','Hoch','Sehr hoch','Maximal'];

    return {
        value: Math.min(10, Math.max(0, initial)),
        dragging: false,
        startAngle: null,
        startValue: null,

        get trackPath() { return arcPath(START_DEG, START_DEG + RANGE_DEG); },
        get valuePath() {
            if (this.value === 0) return `M ${polar(START_DEG).x.toFixed(2)} ${polar(START_DEG).y.toFixed(2)}`;
            return arcPath(START_DEG, START_DEG + (this.value / 10) * RANGE_DEG);
        },
        get arcColor() {
            if (this.value >= 8) return '#16a34a';
            if (this.value >= 5) return '#f59e0b';
            return '#ef4444';
        },
        get label() { return LABELS[this.value] || ''; },

        angleFromEvent(e) {
            const rect = this.$el.getBoundingClientRect();
            const touch = e.touches ? e.touches[0] : e;
            const dx = touch.clientX - (rect.left + rect.width / 2);
            const dy = touch.clientY - (rect.top + rect.height / 2);
            return Math.atan2(dy, dx) * 180 / Math.PI;
        },

        startDrag(e) {
            this.dragging = true;
            this.startAngle = this.angleFromEvent(e);
            this.startValue = this.value;
        },
        onDrag(e) {
            if (!this.dragging) return;
            let delta = this.angleFromEvent(e) - this.startAngle;
            if (delta > 180) delta -= 360;
            if (delta < -180) delta += 360;
            const newVal = Math.round(this.startValue + delta / RANGE_DEG * 10);
            this.value = Math.min(10, Math.max(0, newVal));
        },
        stopDrag() { this.dragging = false; },
        increment() { if (this.value < 10) this.value++; },
        decrement() { if (this.value > 0) this.value--; },
    };
}
</script>
@endpush
@endsection

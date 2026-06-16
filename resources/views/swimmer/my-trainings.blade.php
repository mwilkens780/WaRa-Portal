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
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ showMuted: false }">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-700">Trainingsplanung</h2>
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ $trainingSeries->count() }} Serien</span>
                @if($excludedSeriesIds->isNotEmpty())
                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">{{ $excludedSeriesIds->count() }} ausgeblendet</span>
                @endif
            </div>
            @if($excludedSeriesIds->isNotEmpty())
            <button type="button" @click="showMuted = !showMuted"
                    class="text-xs text-gray-500 border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50 transition-colors"
                    x-text="showMuted ? 'Ausgeblendete verstecken' : 'Ausgeblendete anzeigen'">
            </button>
            @endif
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($trainingSeries as $series)
                @php $isExcluded = $series->is_excluded; @endphp
                <div x-show="{{ $isExcluded ? 'showMuted' : 'true' }}"
                     class="px-4 py-3 flex items-center gap-3 {{ $isExcluded ? 'opacity-50' : '' }}">
                    <span class="flex-shrink-0 inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $series->type_color }}">
                        {{ $series->title }}
                    </span>
                    <div class="flex-1 min-w-0">
                        @if($series->groups->isNotEmpty())
                            <p class="text-xs text-gray-400">{{ $series->groups->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                    @if($isExcluded)
                        <span class="text-xs text-amber-600 font-medium mr-2">ausgeblendet</span>
                        <form method="POST" action="{{ route('swimmer.series.include', $series->recurrence_group_id) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-primary border border-primary/30 px-3 py-1 rounded-lg hover:bg-primary/5 transition-colors">
                                Einblenden
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('swimmer.series.exclude', $series->recurrence_group_id) }}">
                            @csrf
                            <button type="submit" class="text-xs text-gray-400 border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50 transition-colors">
                                Ausblenden
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Bevorstehende Trainings ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
            <h2 class="text-sm font-semibold text-gray-700">Bevorstehende Trainings</h2>
            @if($upcoming->count())
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold">{{ $upcoming->count() }}</span>
            @endif
        </div>

        @if($upcoming->isEmpty())
            <p class="text-sm text-gray-400 text-center py-8">Keine bevorstehenden Einheiten geplant.</p>
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
                                {{-- Registration button --}}
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

                                {{-- Absence --}}
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

                        {{-- Inline absence form --}}
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
    </div>

    {{-- ── Vergangene Trainings ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-700">Vergangene Trainings</h2>
            {{-- Filter tabs --}}
            <div class="flex gap-1 p-1 bg-white border border-gray-200 rounded-lg text-xs">
                <a href="{{ route('swimmer.sessions', ['filter' => 'all']) }}"
                   class="px-2.5 py-1 rounded font-medium transition-colors {{ $filter === 'all' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-700' }}">
                    Alle
                </a>
                <a href="{{ route('swimmer.sessions', ['filter' => 'attended']) }}"
                   class="px-2.5 py-1 rounded font-medium transition-colors {{ $filter === 'attended' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-700' }}">
                    Anwesend
                </a>
                <a href="{{ route('swimmer.sessions', ['filter' => 'missed']) }}"
                   class="px-2.5 py-1 rounded font-medium transition-colors {{ $filter === 'missed' ? 'bg-primary text-white' : 'text-gray-500 hover:text-gray-700' }}">
                    Gefehlt
                </a>
            </div>
        </div>

        @if($pastSessions->isEmpty())
            <p class="text-sm text-gray-400 text-center py-10">Keine Einheiten gefunden.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($pastSessions as $session)
                    @php
                        $att       = $session->attendances->first();
                        $isPresent = $att?->attended === true;
                        $diary     = $session->diaries->first();
                    @endphp
                    <div x-data="{ diaryOpen: {{ $diary ? 'false' : 'false' }} }" class="px-4 py-3">

                        {{-- Session row --}}
                        <div class="flex items-start gap-3">

                            {{-- Attendance indicator --}}
                            <div class="flex-shrink-0 mt-0.5">
                                @if($isPresent)
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center" title="Anwesend (bestätigt)">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center" title="Nicht erfasst / Abwesend">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
                                </div>
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $session->title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $session->start_time }}@if($session->end_time) – {{ $session->end_time }}@endif Uhr
                                    · {{ $session->location }}
                                    · {{ $session->trainer?->name ?? '–' }}
                                </p>

                                {{-- Diary preview (when collapsed) --}}
                                @if($diary && !$isPresent === false)
                                    {{-- only show if actually attended --}}
                                @endif
                                @if($diary)
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        @if($diary->mood)
                                            <span class="text-sm" title="{{ $diary->mood_label }}">{{ $diary->mood_emoji }}</span>
                                        @endif
                                        @if($diary->perceived_intensity)
                                            <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">{{ $diary->perceived_intensity }}/10</span>
                                        @endif
                                        @if($diary->body)
                                            <span class="text-xs text-gray-500 truncate max-w-[200px]">{{ Str::limit($diary->body, 60) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Diary toggle button (only for attended sessions) --}}
                            @if($isPresent)
                                <button type="button" @click="diaryOpen = !diaryOpen"
                                        class="flex-shrink-0 flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border transition-colors"
                                        :class="diaryOpen ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50'">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span x-text="diaryOpen ? 'Schließen' : '{{ $diary ? 'Bearbeiten' : 'Tagebuch' }}'">{{ $diary ? 'Bearbeiten' : 'Tagebuch' }}</span>
                                </button>
                            @endif

                        </div>

                        {{-- Inline diary form --}}
                        @if($isPresent)
                            <div x-show="diaryOpen" x-cloak
                                 class="mt-3 ml-11 border border-gray-100 rounded-xl overflow-hidden">
                                <form method="POST" action="{{ route('sessions.diary', $session) }}"
                                      class="p-4 space-y-3 bg-gray-50/60">
                                    @csrf

                                    {{-- Mood --}}
                                    <div>
                                        <p class="text-xs font-medium text-gray-600 mb-1.5">Stimmung</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach(['sehr_gut' => ['😄','Sehr gut'], 'gut' => ['🙂','Gut'], 'mittel' => ['😐','Mittel'], 'schlecht' => ['😕','Schlecht'], 'sehr_schlecht' => ['😞','Sehr schlecht']] as $val => [$emoji, $label])
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="mood" value="{{ $val }}"
                                                           {{ $diary?->mood === $val ? 'checked' : '' }}
                                                           class="sr-only peer">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs border border-gray-200 bg-white transition-colors peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:text-primary font-medium hover:border-gray-300">
                                                        {{ $emoji }} {{ $label }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- Intensity --}}
                                    <div>
                                        <label class="text-xs font-medium text-gray-600">
                                            Wahrgenommene Intensität:
                                            <span id="int-{{ $session->id }}">{{ $diary?->perceived_intensity ?? 5 }}</span>/10
                                        </label>
                                        <input type="range" name="perceived_intensity" min="1" max="10"
                                               value="{{ $diary?->perceived_intensity ?? 5 }}"
                                               oninput="document.getElementById('int-{{ $session->id }}').textContent = this.value"
                                               class="w-full accent-primary mt-1">
                                        <div class="flex justify-between text-[10px] text-gray-400 mt-0.5">
                                            <span>Leicht</span><span>Mittel</span><span>Sehr intensiv</span>
                                        </div>
                                    </div>

                                    {{-- Notes --}}
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 block mb-1">Notizen</label>
                                        <textarea name="body" rows="3"
                                                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none resize-none bg-white"
                                                  placeholder="Wie war das Training? Was hat gut geklappt?">{{ $diary?->body }}</textarea>
                                    </div>

                                    <button type="submit"
                                            class="bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors">
                                        {{ $diary ? 'Aktualisieren' : 'Speichern' }}
                                    </button>
                                </form>
                            </div>
                        @endif

                    </div>
                @endforeach
            </div>

            @if($pastSessions->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $pastSessions->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection

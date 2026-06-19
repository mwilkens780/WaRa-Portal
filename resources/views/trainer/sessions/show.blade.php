@extends('layouts.app')
@section('title', $session->title)
@section('page-title', $session->title)

@section('content')
<div class="mt-2 space-y-6" x-data="{ activeTab: 'attendance' }">

    {{-- Session-Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1">
                <div>
                    <p class="text-xs text-gray-500">Datum</p>
                    <p class="font-semibold text-gray-800">{{ $session->date->format('d.m.Y') }}</p>
                    <p class="text-xs text-gray-400">{{ $session->date->isoFormat('dddd') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Uhrzeit</p>
                    <p class="font-semibold text-gray-800">{{ $session->start_time }}
                        @if($session->end_time) – {{ $session->end_time }} @endif
                    </p>
                    @if($session->duration)<p class="text-xs text-gray-400">{{ $session->duration }}</p>@endif
                </div>
                <div>
                    <p class="text-xs text-gray-500">Ort</p>
                    <p class="font-semibold text-gray-800">{{ $session->location }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Typ</p>
                    <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full {{ $session->type_color }}">{{ $session->type_label }}</span>
                    @if($session->coTrainers->isNotEmpty())
                    <p class="text-xs text-gray-400 mt-1">{{ $session->coTrainers->map(fn($t) => $t->firstname.' '.$t->lastname)->join(', ') }}</p>
                    @endif
                </div>
                @if($session->trainingGroups->isNotEmpty())
                <div class="col-span-2 sm:col-span-4">
                    <p class="text-xs text-gray-500 mb-1">Trainingsgruppen</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($session->trainingGroups as $tg)
                            @php $tgColors = $tg->colorDots; @endphp
                            <a href="{{ route('admin.training-groups.show', $tg) }}"
                               class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full {{ $tgColors['badge'] }} hover:opacity-80 transition-opacity">
                                <span class="w-2 h-2 rounded-full {{ $tgColors['dot'] }}"></span>
                                {{ $tg->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0 flex-wrap">
                <a href="{{ route('trainer.sessions.edit', $session) }}"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Bearbeiten
                </a>
                <form method="POST" action="{{ route('trainer.sessions.destroy', $session) }}"
                      onsubmit="return confirm('Einheit löschen?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-2 border border-red-200 rounded-lg text-sm text-red-600 hover:bg-red-50 transition-colors">
                        Löschen
                    </button>
                </form>
                @if($session->recurrence_group_id)
                    <form method="POST" action="{{ route('trainer.sessions.destroy-group', $session) }}"
                          onsubmit="return confirm('Alle {{ $siblings->count() + 1 }} Einheiten der Wiederholungsgruppe löschen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-3 py-2 border border-red-200 rounded-lg text-sm text-red-700 hover:bg-red-50 transition-colors">
                            Gruppe löschen
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Missing trainer warning --}}
        @if($session->has_missing_trainer)
        <div class="mt-4 pt-4 border-t border-gray-100 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <div>
                <p class="font-semibold text-amber-800">Kein Trainer zugewiesen</p>
                <p class="text-amber-700 text-xs mt-0.5">
                    Dieser Einheit ist kein aktiver Trainer zugeordnet.
                    <a href="{{ route('trainer.sessions.edit', $session) }}" class="underline font-medium">Trainer zuweisen →</a>
                </p>
            </div>
        </div>
        @endif

        {{-- Überfüllt-Banner --}}
        @if($isOverCapacity)
        <div class="mt-4 pt-4 border-t border-gray-100 flex items-start gap-3 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm">
            <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="font-semibold text-red-800">Kapazitätsgrenze überschritten</p>
                <p class="text-red-700 text-xs mt-0.5">
                    {{ $expectedCount }} von {{ $session->max_participants }} Plätzen belegt
                    ({{ $expectedCount - $session->max_participants }} zu viel).
                    <a href="{{ route('trainer.sessions.edit', $session) }}" class="underline font-medium">Limit anpassen →</a>
                </p>
            </div>
        </div>
        @elseif($session->max_participants)
        <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500 flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
            </svg>
            Teilnehmerzahl: <span class="font-semibold text-gray-700">{{ $expectedCount }} / {{ $session->max_participants }}</span>
            @if($preAbsentCount > 0)
                <span class="text-gray-400">(–{{ $preAbsentCount }} Absagen)</span>
            @endif
        </div>
        @endif

        {{-- Trainer --}}
        @if($session->coTrainers->isNotEmpty())
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-xs text-gray-500 mb-1.5">Trainer</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($session->coTrainers as $ct)
                    <span class="text-xs bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full">{{ $ct->firstname }} {{ $ct->lastname }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if($session->notes)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 mb-1">Notizen</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $session->notes }}</p>
            </div>
        @endif

        {{-- Beteiligung --}}
        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-6">
            <div>
                <p class="text-xs text-gray-500">Beteiligung</p>
                <p class="text-xl font-bold text-primary">{{ $participationPct }} %</p>
                <p class="text-xs text-gray-400">{{ $presentCount }} / {{ $totalSwimmers }} Schwimmer</p>
            </div>
            <div class="flex-1 bg-gray-100 rounded-full h-3 max-w-xs">
                <div class="bg-primary h-3 rounded-full" style="width: {{ $participationPct }}%"></div>
            </div>
        </div>

        {{-- Wiederholungsgruppe --}}
        @if($session->recurrence_group_id && $siblings->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 mb-2">Weitere Einheiten dieser Wiederholungsgruppe</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($siblings as $sib)
                        <a href="{{ route('trainer.sessions.show', $sib) }}"
                           class="text-xs px-2.5 py-1 rounded-full border border-primary/30 text-primary hover:bg-primary/5 transition-colors">
                            {{ $sib->date->format('d.m.Y') }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Individuelle Schwimmer-Zuweisung ─────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5" x-data="{ showAssignForm: false, assignScope: 'session' }">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Individuelle Schwimmer-Zuweisung</h2>
                <p class="text-xs text-gray-400 mt-0.5">Schwimmer unabhängig von ihrer Gruppe zuweisen</p>
            </div>
            <button @click="showAssignForm = !showAssignForm" type="button"
                    class="text-xs text-primary hover:underline font-medium" x-text="showAssignForm ? 'Schließen' : '+ Schwimmer zuweisen'"></button>
        </div>

        {{-- Add form --}}
        <div x-show="showAssignForm" x-transition class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="flex flex-wrap gap-3">
                {{-- Single session --}}
                <form method="POST" action="{{ route('trainer.sessions.swimmer.add', $session) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Schwimmer (nur diese Einheit)</label>
                        <select name="user_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">Wählen...</option>
                            @foreach($allSwimmersForAssign as $s)
                                <option value="{{ $s->id }}">{{ $s->lastname }}, {{ $s->firstname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors whitespace-nowrap">
                        Zu Einheit
                    </button>
                </form>

                @if($session->recurrence_group_id)
                {{-- Series --}}
                <form method="POST" action="{{ route('trainer.sessions.series.swimmer.add', $session->recurrence_group_id) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Schwimmer (zur ganzen Serie)</label>
                        <select name="user_id" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">Wählen...</option>
                            @foreach($allSwimmersForAssign as $s)
                                <option value="{{ $s->id }}">{{ $s->lastname }}, {{ $s->firstname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors whitespace-nowrap">
                        Zur Serie
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Individual assignments for this session --}}
        @if($individualSwimmers->isNotEmpty())
        <div class="mb-3">
            <p class="text-xs text-gray-500 font-medium mb-2">Nur diese Einheit:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($individualSwimmers as $assign)
                <div class="flex items-center gap-1.5 bg-blue-50 border border-blue-200 rounded-full px-3 py-1 text-xs">
                    <span class="font-medium text-blue-800">{{ $assign->user?->name }}</span>
                    <form method="POST" action="{{ route('trainer.sessions.swimmer.remove', [$session, $assign->user_id]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-blue-400 hover:text-red-500 ml-1 font-bold" title="Entfernen">×</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Series assignments --}}
        @if($seriesIndividualSwimmers->isNotEmpty())
        <div class="mb-3">
            <p class="text-xs text-gray-500 font-medium mb-2">Zur ganzen Serie:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($seriesIndividualSwimmers as $assign)
                <div class="flex items-center gap-1.5 bg-indigo-50 border border-indigo-200 rounded-full px-3 py-1 text-xs">
                    <span class="text-indigo-400 font-semibold">≈</span>
                    <span class="font-medium text-indigo-800">{{ $assign->user?->name }}</span>
                    <form method="POST" action="{{ route('trainer.sessions.series.swimmer.remove', [$session->recurrence_group_id, $assign->user_id]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-indigo-400 hover:text-red-500 ml-1 font-bold" title="Entfernen">×</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($individualSwimmers->isEmpty() && $seriesIndividualSwimmers->isEmpty())
            <p class="text-xs text-gray-400">Keine individuellen Zuweisungen.</p>
        @endif

        {{-- Registrations --}}
        @if($session->registration_open || $sessionRegistrations->isNotEmpty())
        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex items-center gap-2 mb-2">
                <p class="text-xs text-gray-500 font-medium">Anmeldungen</p>
                @if($session->registration_open)
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">offen</span>
                @else
                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">geschlossen</span>
                @endif
                @if($session->max_participants)
                    <span class="text-xs text-gray-400">{{ $sessionRegistrations->count() }}/{{ $session->max_participants }} Plätze</span>
                @else
                    <span class="text-xs text-gray-400">{{ $sessionRegistrations->count() }} angemeldet</span>
                @endif
            </div>
            @if($sessionRegistrations->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($sessionRegistrations as $reg)
                <span class="inline-flex items-center gap-1.5 bg-green-50 border border-green-200 rounded-full px-3 py-1 text-xs font-medium text-green-800">
                    {{ $reg->user?->name }}
                    <span class="text-green-400 text-[10px]">{{ $reg->registered_at->deBerlin('d.m. H:i') }}</span>
                </span>
                @endforeach
            </div>
            @else
                <p class="text-xs text-gray-400">Noch keine Anmeldungen.</p>
            @endif
        </div>
        @endif
    </div>

    {{-- Gastgruppe ──────────────────────────────────────────────────────────── --}}
    @if($session->guest_group_id || $guestBookings->isNotEmpty())
    @php
        $guestPreAbsent = $preAbsentCount ?? 0;
        $availableGuestSpots = $session->max_participants !== null
            ? max(0, $session->max_participants - ($expectedCount - $guestPreAbsent))
            : null;
    @endphp
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">Gastgruppe</h2>
            <a href="{{ route('trainer.sessions.edit', $session) }}" class="text-xs text-primary hover:underline">Bearbeiten</a>
        </div>

        @if($session->guestGroup)
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <span class="text-sm text-gray-700">
                Gastgruppe: <strong>{{ $session->guestGroup->name }}</strong>
            </span>
            @if($availableGuestSpots !== null)
                @if($availableGuestSpots > 0)
                    <span class="text-xs bg-green-100 text-green-700 px-2.5 py-0.5 rounded-full font-medium">
                        {{ $availableGuestSpots }} freie {{ $availableGuestSpots === 1 ? 'Platz' : 'Plätze' }}
                    </span>
                @else
                    <span class="text-xs bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full font-medium">
                        Keine freien Plätze
                    </span>
                @endif
            @else
                <span class="text-xs text-gray-400">(kein Teilnehmerlimit gesetzt – Gastfunktion inaktiv)</span>
            @endif
        </div>
        <p class="text-xs text-gray-400 mb-3">
            Mitglieder der Gastgruppe werden per E-Mail benachrichtigt, wenn durch Absagen Plätze frei werden.
        </p>
        @endif

        @if($guestBookings->isNotEmpty())
        <div>
            <p class="text-xs text-gray-500 font-medium mb-2">Gastbuchungen:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($guestBookings as $booking)
                <span class="inline-flex items-center gap-1.5 bg-purple-50 border border-purple-200 rounded-full px-3 py-1 text-xs font-medium text-purple-800">
                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    {{ $booking->user?->name }}
                </span>
                @endforeach
            </div>
        </div>
        @else
        <p class="text-xs text-gray-400">Noch keine Gastbuchungen.</p>
        @endif
    </div>
    @endif

    {{-- Bahnbelegung ──────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5"
         x-data="laneBookingApp()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Bahnbelegung</h2>
            <button @click="showForm = !showForm" type="button"
                    class="text-xs text-primary hover:underline font-medium" x-text="showForm ? 'Schließen' : 'Bahnen buchen'"></button>
        </div>

        {{-- Existing bookings linked to this session --}}
        @if($session->hallBookings->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-3">
            @foreach($session->hallBookings as $hb)
            <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-xs">
                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $hb->resource->color }}"></span>
                <span class="font-medium text-gray-700">{{ $hb->resource->name }}</span>
                <span class="text-gray-400">{{ $hb->formatted_time }}</span>
                <form method="POST" action="{{ route('trainer.sessions.remove-lane', [$session, $hb]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-400 hover:text-red-600 ml-1" title="Entfernen">×</button>
                </form>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-xs text-gray-400 mb-3">Noch keine Bahnen gebucht.</p>
        @endif

        {{-- Freie Bahnkapazitäten zum Trainingszeitpunkt --}}
        @if(!$session->end_time)
        <p class="text-xs text-gray-400 mb-4 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Endzeit setzen, um freie Bahnkapazitäten zu prüfen.
        </p>
        @elseif($freeResources->isEmpty())
        <div class="mb-4 flex items-center gap-2 text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            Keine freien Bahnen zum Trainingszeitpunkt ({{ substr($session->start_time,0,5) }}–{{ substr($session->end_time,0,5) }}).
        </div>
        @else
        <div class="mb-4">
            <p class="text-xs font-medium text-green-700 mb-1.5 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Freie Bahnen ({{ substr($session->start_time,0,5) }}–{{ substr($session->end_time,0,5) }}):
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($freeResources as $res)
                <span class="inline-flex items-center gap-1.5 text-xs bg-green-50 border border-green-200 text-green-700 px-2.5 py-1 rounded-full font-medium">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $res->color }}"></span>
                    {{ $res->name }}
                </span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Booking form --}}
        <div x-show="showForm" x-transition>
            <p class="text-xs text-gray-500 mb-3">Verfügbarkeit prüfen und Bahnen für {{ $session->date->isoFormat('dddd') }}, {{ $session->start_time }}–{{ $session->end_time ?? '?' }} buchen:</p>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-3">
                @foreach($allResources as $res)
                <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors"
                       :class="isBooked({{ $res->id }}) ? 'border-primary bg-primary/5' : ''">
                    <input type="checkbox" :value="{{ $res->id }}" x-model="selectedLanes"
                           @change="conflicts = []"
                           class="w-4 h-4 rounded text-primary border-gray-300">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:{{ $res->color }}"></span>
                    <span class="text-gray-700">{{ $res->name }}</span>
                    <span x-show="isBooked({{ $res->id }})" class="ml-auto text-[10px] text-primary font-medium">gebucht</span>
                </label>
                @endforeach
            </div>

            {{-- Conflicts --}}
            <div x-show="conflicts.length > 0" x-transition class="mb-3 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm">
                <p class="font-semibold text-red-700 mb-1">Belegungskonflikte:</p>
                <ul class="space-y-0.5">
                    <template x-for="c in conflicts" :key="c.resource">
                        <li class="text-red-600 text-xs flex gap-2">
                            <span class="font-mono bg-red-100 px-1.5 rounded" x-text="c.time"></span>
                            <span x-text="c.resource + ': ' + c.label"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="flex gap-2">
                <button @click="checkAndBook(false)" :disabled="selectedLanes.length === 0 || saving"
                        class="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50">
                    <span x-text="saving ? 'Wird gebucht…' : 'Bahnen buchen'"></span>
                </button>
                <button x-show="conflicts.length > 0" @click="checkAndBook(true)"
                        class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition-colors">
                    Trotzdem buchen
                </button>
            </div>
        </div>
    </div>

    <script>
    function laneBookingApp() {
        return {
            showForm: false,
            selectedLanes: [],
            conflicts: [],
            saving: false,
            bookedIds: @json($session->hallBookings->pluck('hall_resource_id')->toArray()),

            isBooked(id) { return this.bookedIds.includes(id); },

            async checkAndBook(force = false) {
                if (!this.selectedLanes.length) return;
                this.saving = true;
                try {
                    const r = await fetch('{{ route('trainer.sessions.book-lanes', $session) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                        body: JSON.stringify({ hall_resource_ids: this.selectedLanes.map(Number), force }),
                    });
                    const d = await r.json();
                    if (r.status === 409) { this.conflicts = d.conflicts ?? []; }
                    else if (r.status === 422) { alert(d.error ?? 'Fehler'); }
                    else if (r.ok) { window.location.reload(); }
                } finally { this.saving = false; }
            },
        };
    }
    </script>

    {{-- Trainingsplan --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Trainingsplan</h2>
            <div class="flex items-center gap-2">
                @if($session->trainingPlan)
                    <a href="{{ route('trainer.sessions.print', $session) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Drucken / PDF
                    </a>
                @endif
                <a href="{{ route('trainer.sessions.plan.builder', $session) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                          {{ $session->trainingPlan ? 'bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100' : 'bg-primary text-white hover:bg-primary-dark' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="{{ $session->trainingPlan ? 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' : 'M12 4v16m8-8H4' }}"/>
                    </svg>
                    {{ $session->trainingPlan ? 'Bearbeiten' : 'Trainingsplan erstellen' }}
                </a>
            </div>
        </div>

        @if($session->trainingPlan)
            @if($session->trainingPlan->description)
                <p class="text-sm text-gray-600 mb-4 whitespace-pre-line">{{ $session->trainingPlan->description }}</p>
            @endif

            @if($session->trainingPlan->attachment_path)
                <div class="flex items-center gap-3 p-3 bg-blue-50 rounded-lg border border-blue-100 mb-4">
                    <svg class="w-6 h-6 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <a href="{{ route('sessions.plan.attachment.download', $session) }}"
                       class="text-sm font-medium text-blue-700 hover:underline">Anhang herunterladen</a>
                </div>
            @endif

            @if($session->trainingPlan->blocks->isNotEmpty())
                @php
                    $planTotalMeters = $session->trainingPlan->blocks->sum(fn($b) => $b->total_repetitions * ($b->distance ?? 0));
                    $allMaterials = $session->trainingPlan->blocks->flatMap(fn($b) => $b->materials ?? [])->unique()->values();
                @endphp
                @if($planTotalMeters > 0 || $allMaterials->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        @if($planTotalMeters > 0)
                            <span class="text-xs text-gray-500">Gesamtdistanz: <span class="font-bold text-primary">{{ number_format($planTotalMeters) }} m</span></span>
                        @endif
                        @if($allMaterials->isNotEmpty())
                            @if($planTotalMeters > 0)<span class="text-gray-300">·</span>@endif
                            @foreach($allMaterials as $mat)
                                <span class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full font-medium">{{ $mat }}</span>
                            @endforeach
                        @endif
                    </div>
                @endif

                @foreach($session->trainingPlan->blocks as $block)
                    @php
                        $blockTimeRow = $blockTimesMap[$block->id] ?? [];
                        $iMin = $block->start_interval_seconds ? intdiv($block->start_interval_seconds, 60) : 0;
                        $iSec = $block->start_interval_seconds ? $block->start_interval_seconds % 60 : 0;
                        $rMin = $block->recovery_seconds ? intdiv($block->recovery_seconds, 60) : 0;
                        $rSec = $block->recovery_seconds ? $block->recovery_seconds % 60 : 0;
                    @endphp
                    <div class="border border-gray-100 rounded-xl overflow-hidden mb-3">
                        {{-- Block-Header --}}
                        <div class="bg-gray-50 px-4 py-2.5 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Block {{ $loop->iteration }}</span>
                            @if($block->label)
                                <span class="text-sm font-semibold text-gray-700">{{ $block->label }}</span>
                                <span class="text-gray-300">·</span>
                            @endif
                            @if($block->total_repetitions && $block->distance)
                                <span class="text-sm font-mono font-bold text-gray-800">{{ $block->repetitions_display }} × {{ $block->distance }} m</span>
                                <span class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-medium">= {{ number_format($block->total_repetitions * $block->distance) }} m</span>
                            @endif
                            @if($block->start_interval_seconds)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Intervall: {{ $iMin }}:{{ str_pad($iSec, 2, '0', STR_PAD_LEFT) }}</span>
                            @endif
                            @if($block->recovery_seconds)
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Pause: {{ $rMin }}:{{ str_pad($rSec, 2, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        </div>

                        {{-- Block-Body --}}
                        <div class="p-4 space-y-3">
                            {{-- Badges: Stilarten, Materialien, Zusätze --}}
                            @php $hasBadges = !empty($block->disciplines) || !empty($block->materials) || !empty($block->additions); @endphp
                            @if($hasBadges)
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($block->disciplines ?? [] as $disc)
                                        <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full font-medium">
                                            {{ \App\Models\TrainingPlanBlock::$disciplineLabels[$disc] ?? $disc }}
                                        </span>
                                    @endforeach
                                    @foreach($block->materials ?? [] as $mat)
                                        <span class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full font-medium">{{ $mat }}</span>
                                    @endforeach
                                    @foreach($block->additions ?? [] as $add)
                                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">{{ $add }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($block->comment)
                                <p class="text-sm text-gray-600 italic border-l-2 border-gray-200 pl-3">{{ $block->comment }}</p>
                            @endif

                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-sm text-gray-400">Keine Blöcke definiert.
                    <a href="{{ route('trainer.sessions.plan.builder', $session) }}" class="text-primary hover:underline">Blöcke hinzufügen</a>
                </p>
            @endif
        @else
            <div class="py-4 text-center">
                <p class="text-sm text-gray-400">Noch kein Trainingsplan erstellt.</p>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex border-b border-gray-100 overflow-x-auto">
            <button @click="activeTab = 'attendance'"
                    :class="activeTab === 'attendance' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap">
                Anwesenheit
                <span class="ml-1.5 bg-blue-100 text-blue-700 text-xs font-semibold px-1.5 py-0.5 rounded-full">{{ $registeredSwimmers->count() }}</span>
                @if($preAbsentCount > 0)
                    <span class="ml-1 bg-red-100 text-red-600 text-xs px-1.5 py-0.5 rounded-full font-bold">{{ $preAbsentCount }} Absage(n)</span>
                @endif
            </button>
            <button @click="activeTab = 'times'"
                    :class="activeTab === 'times' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap">
                Zeiten
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded-full">{{ $session->swimmingTimes->count() }}</span>
            </button>
            <button @click="activeTab = 'diary'"
                    :class="activeTab === 'diary' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap">
                Tagebuch
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded-full">{{ $session->diaries->count() }}</span>
            </button>
        </div>

        {{-- Anwesenheit Tab --}}
        <div x-show="activeTab === 'attendance'" class="p-5">
            @if($swimmers->isEmpty())
                <p class="text-sm text-gray-400 py-4 text-center">
                    {{ $session->trainingGroups->isNotEmpty() ? 'Dieser Einheit sind keine Schwimmer über die zugewiesene Gruppe zugeordnet.' : 'Noch keine Schwimmer im System.' }}
                </p>
            @else
            <form method="POST" action="{{ route('trainer.sessions.attendance', $session) }}">
                @csrf

                {{-- Angemeldet --}}
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Angemeldet</span>
                        <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $registeredSwimmers->count() }}</span>
                    </div>
                    @forelse($registeredSwimmers as $swimmer)
                        @php
                            $att      = $session->attendances->where('user_id', $swimmer->id)->first();
                            $isPresent = in_array($swimmer->id, $attendedIds);
                        @endphp
                        <div class="py-2.5 border-b border-gray-50 last:border-0">
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-3 flex-1 cursor-pointer min-w-0">
                                    <input type="checkbox"
                                           name="attendance[{{ $swimmer->id }}]"
                                           value="1"
                                           {{ $isPresent ? 'checked' : '' }}
                                           class="w-5 h-5 rounded border-gray-300 text-primary flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ strtoupper(substr($swimmer->firstname ?: $swimmer->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-gray-800">{{ $swimmer->name }}</span>
                                        @if($swimmer->birth_date)
                                            <span class="text-xs text-gray-400 ml-1">({{ $swimmer->age }} J.)</span>
                                        @endif
                                    </div>
                                </label>
                                <input type="text" name="notes[{{ $swimmer->id }}]"
                                       placeholder="Sichtbare Notiz..."
                                       value="{{ $att?->notes }}"
                                       class="text-sm px-3 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none w-36 md:w-48 flex-shrink-0">
                            </div>
                            <div class="mt-1.5 ml-16">
                                <input type="text" name="trainer_comment[{{ $swimmer->id }}]"
                                       placeholder="Trainer-Kommentar (intern)..."
                                       value="{{ $att?->trainer_comment }}"
                                       class="text-xs px-3 py-1.5 border border-amber-200 bg-amber-50/60 rounded-lg focus:ring-2 focus:ring-amber-300 outline-none w-full max-w-lg text-gray-700 placeholder-gray-400">
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 py-2">Alle Schwimmer haben vorab abgesagt.</p>
                    @endforelse
                </div>

                {{-- Abgemeldet --}}
                @if($cancelledSwimmers->isNotEmpty())
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Abgemeldet</span>
                        <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $cancelledSwimmers->count() }}</span>
                        <span class="text-xs text-gray-400">– vorab abgesagt, Anwesenheit trotzdem erfassbar</span>
                    </div>
                    @foreach($cancelledSwimmers as $swimmer)
                        @php
                            $att      = $session->attendances->where('user_id', $swimmer->id)->first();
                            $isPresent = in_array($swimmer->id, $attendedIds);
                        @endphp
                        <div class="py-2.5 border-b border-gray-50 last:border-0 bg-red-50/30 rounded-lg px-2 mb-1">
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-3 flex-1 cursor-pointer min-w-0">
                                    <input type="checkbox"
                                           name="attendance[{{ $swimmer->id }}]"
                                           value="1"
                                           {{ $isPresent ? 'checked' : '' }}
                                           class="w-5 h-5 rounded border-gray-300 text-primary flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ strtoupper(substr($swimmer->firstname ?: $swimmer->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-gray-700">{{ $swimmer->name }}</span>
                                        @if($swimmer->birth_date)
                                            <span class="text-xs text-gray-400 ml-1">({{ $swimmer->age }} J.)</span>
                                        @endif
                                        @if($att?->pre_absent_note)
                                            <span class="block text-xs text-red-500 mt-0.5">{{ $att->pre_absent_note }}</span>
                                        @endif
                                    </div>
                                </label>
                                <input type="text" name="notes[{{ $swimmer->id }}]"
                                       placeholder="Sichtbare Notiz..."
                                       value="{{ $att?->notes }}"
                                       class="text-sm px-3 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none w-36 md:w-48 flex-shrink-0">
                            </div>
                            <div class="mt-1.5 ml-16">
                                <input type="text" name="trainer_comment[{{ $swimmer->id }}]"
                                       placeholder="Trainer-Kommentar (intern)..."
                                       value="{{ $att?->trainer_comment }}"
                                       class="text-xs px-3 py-1.5 border border-amber-200 bg-amber-50/60 rounded-lg focus:ring-2 focus:ring-amber-300 outline-none w-full max-w-lg text-gray-700 placeholder-gray-400">
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif

                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                    Anwesenheit speichern
                </button>
            </form>
            @endif
        </div>

        {{-- Zeiten Tab --}}
        <div x-show="activeTab === 'times'" x-cloak class="p-5">

            {{-- Block-Zeitenmatrizen --}}
            @if($session->trainingPlan && $session->trainingPlan->blocks->filter(fn($b) => $b->total_repetitions > 0)->isNotEmpty() && $swimmers->isNotEmpty())
                @php $blockNum = 0; @endphp
                @foreach($session->trainingPlan->blocks as $block)
                    @if($block->total_repetitions > 0)
                        @php
                            $blockNum++;
                            $blockTimeRow = $blockTimesMap[$block->id] ?? [];
                            $totalReps    = $block->total_repetitions;
                        @endphp
                        <div class="mb-5">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Block {{ $blockNum }}</span>
                                @if($block->label)
                                    <span class="text-sm font-semibold text-gray-700">{{ $block->label }}</span>
                                    <span class="text-gray-300">·</span>
                                @endif
                                @if($block->distance)
                                    <span class="text-sm font-mono font-bold text-gray-800">{{ $block->repetitions_display }} × {{ $block->distance }} m</span>
                                @endif
                            </div>
                            <div class="overflow-x-auto rounded-lg border border-gray-100">
                                <table class="text-xs w-full min-w-max">
                                    <thead>
                                        <tr class="bg-blue-50 border-b border-blue-100">
                                            <th class="px-3 py-2 text-left text-gray-600 font-semibold sticky left-0 bg-blue-50 min-w-[110px]">Schwimmer</th>
                                            @for($i = 1; $i <= min($totalReps, 50); $i++)
                                                <th class="px-1 py-2 text-center text-gray-500 font-medium min-w-[74px]">{{ $i }}.</th>
                                            @endfor
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @foreach($swimmers as $sw)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-1.5 font-medium text-gray-700 sticky left-0 bg-white min-w-[110px] truncate max-w-[130px]">
                                                    {{ $sw->firstname }} {{ substr($sw->lastname ?? '', 0, 1) }}.
                                                </td>
                                                @for($i = 1; $i <= min($totalReps, 50); $i++)
                                                    @php $existingCs = $blockTimeRow[$sw->id][$i] ?? null; @endphp
                                                    <td class="px-1 py-1">
                                                        <input type="text"
                                                               class="block-time-input w-full text-center px-1 py-1.5 border border-gray-200 rounded focus:ring-2 focus:ring-blue-300 outline-none font-mono text-xs transition-colors"
                                                               placeholder="–"
                                                               value="{{ $existingCs ? \App\Models\TrainingBlockTime::format($existingCs) : '' }}"
                                                               data-block="{{ $block->id }}"
                                                               data-user="{{ $sw->id }}"
                                                               data-rep="{{ $i }}"
                                                               data-session="{{ $session->id }}">
                                                    </td>
                                                @endfor
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Format: <span class="font-mono">m:ss,zz</span> (z.B. <span class="font-mono">1:23,45</span>) · Leer lassen = nicht mitgeschwommen</p>
                        </div>
                    @endif
                @endforeach
                <div class="border-t border-gray-100 mb-5"></div>
            @endif

            <div class="bg-gray-50 rounded-lg p-4 mb-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Neue Zeit eintragen</h3>
                <form method="POST" action="{{ route('trainer.sessions.time', $session) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-xs text-gray-500 mb-1">Schwimmer</label>
                            <select name="user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">Wählen...</option>
                                @foreach($swimmers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Disziplin</label>
                            <select name="discipline" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="F">Freistil</option>
                                <option value="B">Brust</option>
                                <option value="R">Rücken</option>
                                <option value="S">Schmetterling</option>
                                <option value="L">Lagen</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Distanz (m)</label>
                            <select name="distance" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                                    <option value="{{ $d }}">{{ $d }} m</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Zeit (Min : Sek , 1/100)</label>
                            <div class="flex gap-1 items-center">
                                <input type="number" name="time_minutes" min="0" value="0" placeholder="0"
                                       class="w-14 px-2 py-2 border border-gray-300 rounded text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                                <span class="text-gray-400 font-semibold">:</span>
                                <input type="number" name="time_seconds" min="0" max="59" required value="0"
                                       class="w-14 px-2 py-2 border border-gray-300 rounded text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                                <span class="text-gray-400 font-semibold">,</span>
                                <input type="number" name="time_centiseconds" min="0" max="99" required value="0"
                                       class="w-14 px-2 py-2 border border-gray-300 rounded text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                    </div>
                    <button type="submit"
                            class="bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
                        Zeit speichern
                    </button>
                </form>
            </div>

            @if($session->swimmingTimes->isNotEmpty())
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Schwimmer</th>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Disziplin</th>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Distanz</th>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Zeit</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($session->swimmingTimes->sortBy('user.name') as $time)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5 font-medium text-gray-800">{{ $time->user->name }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $time->discipline_label }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $time->distance }} m</td>
                                <td class="px-4 py-2.5 font-mono font-semibold text-primary">
                                    {{ $time->formatted_time }}
                                    @if($time->is_personal_best)
                                        <span class="ml-1 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-sans font-medium">PB</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('trainer.times.destroy', $time) }}"
                                          onsubmit="return confirm('Zeit löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-gray-400 py-4 text-center">Noch keine Zeiten für diese Einheit eingetragen.</p>
            @endif
        </div>

        {{-- Tagebuch Tab --}}
        <div x-show="activeTab === 'diary'" x-cloak class="p-5">
            <div class="space-y-4">
                @forelse($session->diaries as $entry)
                    <div class="border border-gray-100 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold">
                                    {{ substr($entry->user->name, 0, 1) }}
                                </div>
                                <span class="text-sm font-semibold text-gray-800">{{ $entry->user->name }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                @if($entry->mood)
                                    <span class="{{ $entry->mood_color }} font-medium">
                                        {{ $entry->mood_emoji }} {{ $entry->mood_label }}
                                    </span>
                                @endif
                                @if($entry->perceived_intensity)
                                    <span class="text-gray-500">Intensität: <strong>{{ $entry->perceived_intensity }}/10</strong></span>
                                @endif
                            </div>
                        </div>
                        @if($entry->body)
                            <p class="text-sm text-gray-600 whitespace-pre-line">{{ $entry->body }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-4 text-center">Noch keine Tagebucheinträge für diese Einheit.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.block-time-input').forEach(function (input) {
        input.addEventListener('blur', function () { saveBlockTime(this); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
        });
    });
});

function parseTimeCs(raw) {
    if (!raw || !raw.trim() || raw.trim() === '–') return null;
    var v = raw.trim().replace('.', ',');
    var m;
    // m:ss,cc
    if ((m = v.match(/^(\d+):(\d{1,2})[,](\d{1,2})$/))) {
        return parseInt(m[1]) * 6000 + parseInt(m[2]) * 100 + parseInt((m[3] + '0').slice(0, 2));
    }
    // m:ss
    if ((m = v.match(/^(\d+):(\d{1,2})$/))) {
        return parseInt(m[1]) * 6000 + parseInt(m[2]) * 100;
    }
    // ss,cc
    if ((m = v.match(/^(\d{1,2})[,](\d{1,2})$/))) {
        return parseInt(m[1]) * 100 + parseInt((m[2] + '0').slice(0, 2));
    }
    return null;
}

function saveBlockTime(input) {
    var timeCs = parseTimeCs(input.value);
    var csrfToken = '{{ csrf_token() }}';
    input.classList.remove('border-green-300', 'border-red-300');
    input.classList.add('border-blue-300');

    fetch('/trainer/training/' + input.dataset.session + '/block-zeiten', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            block_id:   parseInt(input.dataset.block),
            user_id:    parseInt(input.dataset.user),
            repetition: parseInt(input.dataset.rep),
            time_cs:    timeCs,
        }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        input.classList.remove('border-blue-300');
        if (data.ok) {
            if (data.formatted) input.value = data.formatted;
            input.classList.add('border-green-300');
            setTimeout(function () {
                input.classList.remove('border-green-300');
                input.classList.add('border-gray-200');
            }, 1500);
        } else {
            input.classList.add('border-red-300');
        }
    })
    .catch(function () {
        input.classList.remove('border-blue-300');
        input.classList.add('border-red-300');
    });
}
</script>
@endpush
@endsection

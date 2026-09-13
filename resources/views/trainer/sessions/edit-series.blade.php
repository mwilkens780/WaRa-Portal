@extends('layouts.app')
@section('title', 'Trainingsserie bearbeiten')
@section('page-title', 'Trainingsserie bearbeiten')

@section('content')
@php
    $allGroupsJson = $allGroups->map(fn($g) => [
        'id'       => $g->id,
        'name'     => $g->name,
        'trainers' => $g->trainers->map(fn($t) => [
            'id'   => $t->id,
            'name' => $t->firstname . ' ' . $t->lastname,
        ])->values()->toArray(),
    ])->values()->toJson();
    $allTrainersJson = $allTrainers->map(fn($t) => [
        'id'   => $t->id,
        'name' => $t->lastname . ', ' . $t->firstname,
    ])->values()->toJson();
@endphp
<script>
function seriesEditForm() {
    return {
        allGroups: {!! $allGroupsJson !!},
        selected: @json(array_map('intval', old('groups', $groupIds))),
        selectedCoTrainers: @json(array_map('intval', old('co_trainer_ids', $coTrainerIds))),
        get suggestedTrainerIds() {
            const ids = new Set();
            this.allGroups.forEach(g => {
                if (this.selected.some(id => id == g.id)) {
                    g.trainers.forEach(t => ids.add(t.id));
                }
            });
            return [...ids];
        },
        onGroupToggle(groupId, checked) {
            if (!checked) return;
            const group = this.allGroups.find(g => g.id == groupId);
            if (group) {
                group.trainers.forEach(t => {
                    if (!this.selectedCoTrainers.includes(t.id)) {
                        this.selectedCoTrainers.push(t.id);
                    }
                });
            }
        },
    };
}
</script>

<div class="max-w-3xl mt-2 space-y-5">

    {{-- Info-Banner --}}
    <div class="{{ $isExpired ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200' }} border rounded-xl px-5 py-4">
        <div class="flex items-start gap-3">
            @if($isExpired)
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-amber-800">Diese Trainingsserie ist ausgelaufen.</p>
                    <p class="text-xs text-amber-700 mt-0.5">
                        Letzte Einheit: {{ $last->date->format('d.m.Y') }} &nbsp;·&nbsp; {{ $sessions->count() }} Einheiten gesamt.
                        <a href="{{ route('trainer.sessions.series.generate', $group) }}" class="font-semibold underline hover:text-amber-900">Neue Saison generieren</a>
                    </p>
                </div>
            @else
                <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-blue-800">Serie mit {{ $sessions->count() }} Einheiten
                        @if($rep->max_participants) &nbsp;·&nbsp; Max. {{ $rep->max_participants }} Teilnehmer @endif
                    </p>
                    <p class="text-xs text-blue-700 mt-0.5">
                        {{ $futureSessions->count() }} zukünftige Einheiten werden beim Speichern aktualisiert.
                        Vergangene Einheiten bleiben unverändert.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Hauptformular ──────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-sm font-bold text-gray-700 mb-4 pb-2 border-b border-gray-100">Seriendefinition</h2>
        <form method="POST" action="{{ route('trainer.sessions.series.update', $group) }}"
              class="space-y-5" x-data="seriesEditForm()">
            @csrf @method('PUT')

            {{-- Titel --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
                <input type="text" name="title" value="{{ old('title', $rep->title) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                       required>
                @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Typ --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Typ</label>
                <select name="type"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    @foreach(['kondition' => 'Kondition', 'technik' => 'Technik', 'wettkampf' => 'Wettkampfvorbereitung', 'ausdauer' => 'Ausdauer', 'krafttraining' => 'Krafttraining', 'physio' => 'Physiotherapie', 'mentaltraining' => 'Mentaltraining', 'sonstiges' => 'Sonstiges'] as $val => $label)
                        <option value="{{ $val }}" {{ old('type', $rep->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Zeiten --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Startzeit</label>
                    <input type="time" name="start_time" value="{{ old('start_time', substr($rep->start_time, 0, 5)) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                           required>
                    @error('start_time') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Endzeit</label>
                    <input type="time" name="end_time" value="{{ old('end_time', substr($rep->end_time ?? '', 0, 5)) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            {{-- Ort --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                <input type="text" name="location" value="{{ old('location', $rep->location) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                       required>
            </div>

            {{-- Notizen --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                <textarea name="notes" rows="2"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ old('notes', $rep->notes) }}</textarea>
            </div>

            {{-- Trainingsgruppen --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Trainingsgruppen</label>
                <div class="border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 max-h-48 overflow-y-auto">
                    @forelse($allGroups as $g)
                        <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="groups[]" value="{{ $g->id }}"
                                   x-model="selected"
                                   @change="onGroupToggle({{ $g->id }}, $event.target.checked)"
                                   :value="{{ $g->id }}"
                                   class="w-4 h-4 rounded text-primary border-gray-300">
                            <span class="text-sm text-gray-700">{{ $g->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-400 px-4 py-2.5 italic">Keine Gruppen verfügbar.</p>
                    @endforelse
                </div>
            </div>

            {{-- Trainer --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Trainer</label>
                <div class="border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 max-h-48 overflow-y-auto">
                    @foreach($allTrainers as $t)
                        <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer"
                               :class="suggestedTrainerIds.includes({{ $t->id }}) ? 'bg-blue-50/50' : ''">
                            <input type="checkbox" name="co_trainer_ids[]" value="{{ $t->id }}"
                                   x-model="selectedCoTrainers" :value="{{ $t->id }}"
                                   class="w-4 h-4 rounded text-primary border-gray-300">
                            <span class="text-sm text-gray-700">{{ $t->lastname }}, {{ $t->firstname }}</span>
                            <span x-show="suggestedTrainerIds.includes({{ $t->id }})"
                                  class="ml-auto text-xs text-blue-500 font-medium">Gruppen-Trainer</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- ── Bahnkapazität ─────────────────────────────────────────────── --}}
            <div class="border-t border-gray-100 pt-5 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Bahnkapazität & Anmeldung</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Max. Teilnehmer
                            <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input type="number" name="max_participants" min="1" max="999"
                               value="{{ old('max_participants', $rep->max_participants) }}"
                               placeholder="Unbegrenzt"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        @error('max_participants')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                        @if($expectedCount > 0)
                            <p class="text-xs text-gray-400 mt-1">
                                Aktuell {{ $expectedCount }} erwartete Teilnehmer (Gruppen minus dauerhafte Absagen)
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-col justify-center gap-3">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="hidden" name="registration_open" value="0">
                            <input type="checkbox" name="registration_open" value="1"
                                   {{ old('registration_open', $rep->registration_open) ? 'checked' : '' }}
                                   class="w-4 h-4 mt-0.5 rounded border-gray-300 text-blue-600 flex-shrink-0">
                            <span class="text-sm font-medium text-gray-700">
                                Anmeldung öffnen
                                <span class="block text-xs text-gray-400 font-normal">Schwimmer können sich selbst anmelden</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Gastgruppe
                        <span class="text-gray-400 font-normal">(nur bei gesetztem Teilnehmerlimit)</span>
                    </label>
                    <select name="guest_group_id"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        <option value="">— Keine Gastgruppe —</option>
                        @foreach($allGroups as $g)
                            <option value="{{ $g->id }}"
                                {{ old('guest_group_id', $rep->guest_group_id) == $g->id ? 'selected' : '' }}>
                                {{ $g->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Bei Absagen werden Mitglieder der Gastgruppe benachrichtigt und können freie Plätze buchen.
                    </p>
                </div>
            </div>

            {{-- ── Bahnverteilung ─────────────────────────────────────────── --}}
            <div class="border-t border-gray-100 pt-5 space-y-3">
                <h3 class="text-sm font-semibold text-gray-700">Bahnverteilung</h3>
                <input type="hidden" name="manage_lanes" value="1">
                @if($allResources->isEmpty())
                    <p class="text-xs text-gray-400">Keine aktiven Hallensegmente konfiguriert.</p>
                @else
                    <p class="text-xs text-gray-400">
                        Markierte Ressourcen werden für alle zukünftigen Einheiten dieser Serie gebucht und überschreiben bestehende Bahnbuchungen.
                    </p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach($allResources as $resource)
                            <label class="flex items-center gap-2.5 cursor-pointer px-3 py-2 rounded-lg border border-gray-100 hover:bg-gray-50 select-none">
                                <input type="checkbox"
                                       name="hall_resource_ids[]"
                                       value="{{ $resource->id }}"
                                       {{ in_array($resource->id, $bookedResourceIds) ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-2 focus:ring-primary/30 flex-shrink-0">
                                @if($resource->color)
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $resource->color }}"></span>
                                @endif
                                <span class="flex-1 text-sm text-gray-700 truncate">{{ $resource->name }}</span>
                                <span class="text-xs text-gray-400 flex-shrink-0">{{ $resource->type_label }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                    Zukünftige Einheiten aktualisieren
                </button>
                <a href="{{ route('trainer.sessions.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                @if(!$isExpired)
                    <a href="{{ route('trainer.sessions.series.generate', $group) }}"
                       class="ml-auto text-sm text-green-600 hover:text-green-800 font-medium">
                        + Neue Saison generieren
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ── Dauerhafte Serienabsagen ────────────────────────────────────────── --}}
    @if($exclusions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-orange-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-orange-100 flex items-center gap-2 bg-orange-50/50">
            <svg class="w-4 h-4 text-orange-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-700">
                Dauerhafte Serienabsagen ({{ $exclusions->count() }})
            </h3>
            <span class="ml-auto text-xs text-gray-400">Schwimmer haben die Serie dauerhaft abgesagt</span>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($exclusions as $ex)
                <div class="flex items-center gap-3 px-5 py-2.5 text-sm">
                    <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <span class="font-medium text-gray-800">
                        {{ $ex->user->lastname ?? '?' }}, {{ $ex->user->firstname ?? '' }}
                    </span>
                    @if($ex->comment)
                        <span class="text-gray-400 text-xs truncate max-w-xs">„{{ $ex->comment }}"</span>
                    @endif
                    <span class="ml-auto text-xs bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full font-medium">
                        Dauerhaft abgesagt
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-gray-400">Keine dauerhaften Serienabsagen</p>
    </div>
    @endif

    {{-- ── Einheitenübersicht ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-700">Alle Einheiten dieser Serie ({{ $sessions->count() }})</h3>
            @if($rep->max_participants)
                <span class="ml-auto text-xs text-gray-400">Max. {{ $rep->max_participants }} Plätze</span>
            @endif
        </div>
        <div class="divide-y divide-gray-50 max-h-[480px] overflow-y-auto">
            @foreach($sessions as $s)
                @php
                    $isPast          = $s->date->lt(today());
                    $preAbsent       = $preAbsentCounts[$s->id] ?? 0;
                    $attended        = $attendedCounts[$s->id] ?? null;
                    $cap             = $s->max_participants ?? $rep->max_participants;

                    if ($isPast) {
                        // Vergangene Einheit: reale Anwesenheit
                        $effectiveCount  = $attended ?? $expectedCount;
                        $showAttendance  = $attended !== null;
                    } else {
                        // Zukünftige Einheit: erwartet minus Absagen
                        $effectiveCount  = max(0, $expectedCount - $preAbsent);
                        $showAttendance  = false;
                    }

                    $isOverCap = $cap && $effectiveCount > $cap;
                    $isAtCap   = $cap && $effectiveCount === $cap;
                @endphp
                <div class="flex items-center gap-3 px-5 py-2.5 text-sm {{ $isPast ? 'text-gray-400' : 'text-gray-700' }}">
                    {{-- Datum + Uhrzeit --}}
                    <span class="font-medium w-36 shrink-0">{{ $s->date->isoFormat('dd, D. MMM YY') }}</span>
                    <span class="text-xs w-16 shrink-0">{{ substr($s->start_time, 0, 5) }}@if($s->end_time)–{{ substr($s->end_time, 0, 5) }}@endif</span>

                    {{-- Kapazitäts-/Anwesenheitsanzeige --}}
                    <div class="flex items-center gap-1.5 min-w-0 flex-1">
                        @if($isPast && $showAttendance)
                            {{-- Vergangene Einheit mit erfasster Anwesenheit --}}
                            <span class="text-xs {{ $isOverCap ? 'text-red-500 font-semibold' : 'text-gray-500' }}">
                                {{ $effectiveCount }}{{ $cap ? '/'.$cap : '' }} anwesend
                            </span>
                        @elseif(!$isPast && $cap)
                            {{-- Zukünftige Einheit mit Kapazitätslimit --}}
                            <span class="text-xs {{ $isOverCap ? 'text-red-500 font-semibold' : ($isAtCap ? 'text-amber-500' : 'text-gray-500') }}">
                                {{ $effectiveCount }}/{{ $cap }}
                                @if($isOverCap) · <span class="text-red-500">Überlastet</span>@endif
                            </span>
                        @elseif(!$isPast && $expectedCount > 0)
                            <span class="text-xs text-gray-400">{{ $effectiveCount }} erwartet</span>
                        @endif

                        {{-- Absage-Badge --}}
                        @if($preAbsent > 0)
                            <span class="text-xs bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">
                                {{ $preAbsent }} Absage{{ $preAbsent === 1 ? '' : 'n' }}
                            </span>
                        @endif
                    </div>

                    {{-- Status-Badge + Link --}}
                    @if($isPast)
                        <span class="text-xs bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded-full flex-shrink-0">vergangen</span>
                    @else
                        <span class="text-xs bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded-full flex-shrink-0">zukünftig</span>
                    @endif
                    <a href="{{ route('trainer.sessions.show', $s) }}"
                       class="text-xs text-primary hover:text-primary-dark flex-shrink-0">Details</a>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection

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

<div class="max-w-2xl mt-2 space-y-4">

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
                        Passe die Seriendefinition an und
                        <a href="{{ route('trainer.sessions.series.generate', $group) }}" class="font-semibold underline hover:text-amber-900">generiere eine neue Saison</a>.
                    </p>
                </div>
            @else
                <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-blue-800">Serie mit {{ $sessions->count() }} Einheiten</p>
                    <p class="text-xs text-blue-700 mt-0.5">
                        {{ $futureSessions->count() }} zukünftige Einheiten werden aktualisiert.
                        Vergangene Einheiten bleiben unverändert.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Formular --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
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

    {{-- Serienuebersicht --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-700">Alle Einheiten dieser Serie ({{ $sessions->count() }})</h3>
        </div>
        <div class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
            @foreach($sessions as $s)
                @php $isPast = $s->date->lt(today()); @endphp
                <div class="flex items-center gap-3 px-5 py-2 text-sm {{ $isPast ? 'text-gray-400' : 'text-gray-700' }}">
                    <span class="font-medium w-32 shrink-0">{{ $s->date->isoFormat('dd, D. MMM YYYY') }}</span>
                    <span class="text-xs">{{ substr($s->start_time, 0, 5) }}@if($s->end_time) – {{ substr($s->end_time, 0, 5) }}@endif</span>
                    @if($isPast)
                        <span class="ml-auto text-xs bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded-full">vergangen</span>
                    @else
                        <span class="ml-auto text-xs bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded-full">zukünftig</span>
                    @endif
                    <a href="{{ route('trainer.sessions.show', $s) }}"
                       class="text-xs text-primary hover:text-primary-dark">Details</a>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection

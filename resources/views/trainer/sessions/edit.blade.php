@extends('layouts.app')
@section('title', 'Trainingseinheit bearbeiten')
@section('page-title', 'Trainingseinheit bearbeiten')

@section('content')
@php
    $groupsEditJson = $groups->map(fn($g) => [
        'id'       => $g->id,
        'name'     => $g->name,
        'trainers' => $g->trainers->map(fn($t) => [
            'id'   => $t->id,
            'name' => $t->firstname . ' ' . $t->lastname,
        ])->values()->toArray(),
    ])->values()->toJson();
    $allTrainersEditJson = $allTrainers->map(fn($t) => [
        'id'   => $t->id,
        'name' => $t->lastname . ', ' . $t->firstname,
    ])->values()->toJson();
@endphp
<script>
function trainingEditForm() {
    return {
        editScope: @json(old('edit_scope', 'single')),
        groups: {!! $groupsEditJson !!},
        selected: @json(array_map('intval', old('groups', $session->trainingGroups->pluck('id')->toArray()))),
        selectedCoTrainers: @json(array_map('intval', old('co_trainer_ids', $coTrainerIds))),
        get suggestedTrainerIds() {
            const ids = new Set();
            this.groups.forEach(g => {
                if (this.selected.some(id => id == g.id)) {
                    g.trainers.forEach(t => ids.add(t.id));
                }
            });
            return [...ids];
        },
        onGroupToggle(groupId, checked) {
            if (!checked) return;
            const group = this.groups.find(g => g.id == groupId);
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
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('trainer.sessions.update', $session) }}" class="space-y-5"
              x-data="trainingEditForm()">
            @csrf @method('PUT')

            {{-- Scope-Auswahl (nur bei Wiederholungsserien) --}}
            @if($seriesCount > 1)
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 -mt-1">
                <p class="text-xs font-semibold text-amber-800 mb-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Wiederholungsserie ({{ $seriesCount }} Einheiten)
                </p>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="edit_scope" value="single"
                               x-model="editScope"
                               {{ old('edit_scope', 'single') === 'single' ? 'checked' : '' }}
                               class="w-4 h-4 text-primary border-gray-300">
                        <span class="font-medium text-gray-700">Nur diese Einheit</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="edit_scope" value="series"
                               x-model="editScope"
                               {{ old('edit_scope') === 'series' ? 'checked' : '' }}
                               class="w-4 h-4 text-primary border-gray-300">
                        <span class="font-medium text-gray-700">Alle {{ $seriesCount }} Einheiten der Serie</span>
                        <span class="text-xs text-amber-600 font-normal">(Datum je Einheit bleibt)</span>
                    </label>
                </div>
            </div>
            @endif

            <div class="grid md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $session->title) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Datum <span class="text-red-500">*</span>
                        <span x-show="editScope === 'series'" class="text-xs text-gray-400 font-normal ml-1">(Starttermin der Serie)</span>
                    </label>
                    <input type="date" name="date" value="{{ old('date', $session->date->format('Y-m-d')) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    <p x-show="editScope === 'series'" class="text-xs text-amber-600 mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Wochentag ändern → alle Serientermine werden neu berechnet (Ferienzeiten ausgespart).
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach(['technik' => 'Technik', 'ausdauer' => 'Ausdauer', 'wettkampf' => 'Wettkampfvorbereitung', 'sonstiges' => 'Sonstiges'] as $val => $label)
                            <option value="{{ $val }}" {{ old('type', $session->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beginn <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" value="{{ old('start_time', substr($session->start_time ?? '', 0, 5)) }}" required step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ende</label>
                    <input type="time" name="end_time" value="{{ old('end_time', substr($session->end_time ?? '', 0, 5)) }}" step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort <span class="text-red-500">*</span></label>
                    <input type="text" name="location" value="{{ old('location', $session->location) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                    <textarea name="notes" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ old('notes', $session->notes) }}</textarea>
                </div>
                @if($groups->isNotEmpty())
                @php $assignedGroupIds = old('groups', $session->trainingGroups->pluck('id')->map(fn($id) => (string)$id)->toArray()); @endphp
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trainingsgruppen</label>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($groups as $group)
                            @php $gColors = $group->colorDots; @endphp
                            <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50 border border-gray-100">
                                <input type="checkbox" name="groups[]" value="{{ $group->id }}"
                                       {{ in_array((string)$group->id, $assignedGroupIds) ? 'checked' : '' }}
                                       x-model="selected" :value="{{ $group->id }}"
                                       @change="onGroupToggle({{ $group->id }}, $event.target.checked)"
                                       class="w-4 h-4 text-primary rounded border-gray-300">
                                <span class="w-2.5 h-2.5 rounded-full {{ $gColors['dot'] }} flex-shrink-0"></span>
                                <span class="text-gray-700">{{ $group->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Trainer --}}
                @if($allTrainers->isNotEmpty())
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Trainer
                        <span class="text-xs text-gray-400 font-normal ml-1">– Trainer der gewählten Gruppen werden automatisch vorgeschlagen</span>
                    </label>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($allTrainers as $t)
                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50 border transition-colors"
                               :class="suggestedTrainerIds.includes({{ $t->id }}) ? 'border-blue-200 bg-blue-50/40' : 'border-gray-100'">
                            <input type="checkbox" name="co_trainer_ids[]" value="{{ $t->id }}"
                                   :checked="selectedCoTrainers.includes({{ $t->id }})"
                                   @change="selectedCoTrainers = $event.target.checked
                                       ? [...selectedCoTrainers, {{ $t->id }}]
                                       : selectedCoTrainers.filter(id => id !== {{ $t->id }})"
                                   class="w-4 h-4 rounded text-primary border-gray-300">
                            <span class="text-gray-700 flex-1 truncate">{{ $t->lastname }}, {{ $t->firstname }}</span>
                            <span x-show="suggestedTrainerIds.includes({{ $t->id }})"
                                  class="text-[10px] text-blue-600 font-semibold bg-blue-100 px-1.5 py-0.5 rounded-full flex-shrink-0">Gruppe</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Teilnehmerlimit & Anmeldung --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max. Teilnehmer <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="number" name="max_participants" min="1" max="999"
                           value="{{ old('max_participants', $session->max_participants) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                           placeholder="Unbegrenzt">
                    @error('max_participants')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center gap-3 pt-7">
                    <input type="hidden" name="registration_open" value="0">
                    <input type="checkbox" name="registration_open" id="registration_open_edit" value="1"
                           {{ old('registration_open', $session->registration_open) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="registration_open_edit" class="text-sm font-medium text-gray-700">
                        Anmeldung für Schwimmer öffnen
                        <span class="block text-xs text-gray-400 font-normal">Schwimmer können sich selbst anmelden (first come, first serve)</span>
                    </label>
                </div>

                {{-- Gastgruppe --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Gastgruppe <span class="text-gray-400 font-normal">(optional – nur bei gesetztem Teilnehmerlimit wirksam)</span>
                    </label>
                    <select name="guest_group_id"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">— Keine Gastgruppe —</option>
                        @foreach($allGroups as $g)
                            <option value="{{ $g->id }}"
                                {{ old('guest_group_id', $session->guest_group_id) == $g->id ? 'selected' : '' }}>
                                {{ $g->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Bei Absagen aus der Hauptgruppe werden Mitglieder der Gastgruppe benachrichtigt und können freie Plätze buchen (First come, first serve).
                    </p>
                </div>

            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Speichern
                </button>
                <a href="{{ route('trainer.sessions.show', $session) }}"
                   class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection

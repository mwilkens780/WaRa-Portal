@extends('layouts.app')
@section('title', 'Neue Trainingseinheit')
@section('page-title', 'Neue Trainingseinheit')

@section('content')
@php
    $groupsJson = $groups->map(fn($g) => [
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
function trainingCreateForm() {
    return {
        recurrence: @json(old('recurrence_type', 'none')),
        showRecurrence: false,
        showUntil: true,
        groups: {!! $groupsJson !!},
        selected: @json(array_map('intval', old('groups', []))),
        allTrainers: {!! $allTrainersJson !!},
        selectedCoTrainers: @json(array_map('intval', old('co_trainer_ids', []))),
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
        handleRecurrenceChange() {
            this.showRecurrence = this.recurrence !== 'none';
            this.showUntil = this.recurrence !== 'weekly_season_end';
        },
        init() {
            this.showRecurrence = this.recurrence !== 'none';
            this.showUntil = this.recurrence !== 'weekly_season_end';
        }
    };
}
</script>

<div class="max-w-2xl mt-2">

    @if(session('warning'))
        <div class="mb-4 flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('trainer.sessions.store') }}"
              enctype="multipart/form-data" class="space-y-5"
              x-data="trainingCreateForm()">
            @csrf

            <div class="grid md:grid-cols-2 gap-5">
                {{-- Titel --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           placeholder="z.B. Techniktraining Freistil"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('title') ? 'border-red-400' : '' }}">
                    @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Datum --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum <span class="text-red-500">*</span></label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('date')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Typ --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ <span class="text-red-500">*</span></label>
                    <select name="type" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach([
                            'technik'        => 'Technik',
                            'ausdauer'       => 'Ausdauer',
                            'wettkampf'      => 'Wettkampfvorbereitung',
                            'krafttraining'  => 'Krafttraining',
                            'physio'         => 'Physiotherapie',
                            'mentaltraining' => 'Mentaltraining',
                            'sonstiges'      => 'Sonstiges',
                        ] as $val => $label)
                            <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Zeiten --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beginn <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" value="{{ old('start_time', '07:00') }}" required step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('start_time')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ende</label>
                    <input type="time" name="end_time" value="{{ old('end_time', '08:30') }}" step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('end_time')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Ort --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainingsort <span class="text-red-500">*</span></label>
                    <input type="text" name="location" value="{{ old('location', 'Stadtbad Norderstedt') }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                {{-- Trainingsgruppen --}}
                @if($groups->isNotEmpty())
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trainingsgruppen</label>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($groups as $group)
                            @php $gColors = $group->colorDots; @endphp
                            <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50 border border-gray-100">
                                <input type="checkbox" name="groups[]" value="{{ $group->id }}"
                                       {{ in_array($group->id, old('groups', [])) ? 'checked' : '' }}
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


                {{-- Wiederholung --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wiederholung <span class="text-red-500">*</span></label>
                    <select name="recurrence_type" x-model="recurrence" @change="handleRecurrenceChange()"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="none">Einmalig</option>
                        <option value="weekly">Wöchentlich</option>
                        <option value="biweekly">Zweiwöchentlich</option>
                        <option value="monthly">Monatlich</option>
                        @if($currentSeason)
                        <option value="weekly_season_end">Wöchentlich bis Saisonende ({{ $currentSeason->end_date->format('d.m.Y') }})</option>
                        @endif
                    </select>
                    @error('recurrence_type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Wiederholung bis (nur wenn kein Saisonende gewählt) --}}
                <div class="md:col-span-2" x-show="showRecurrence && showUntil" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wiederholung bis <span class="text-red-500">*</span></label>
                    <input type="date" name="recurrence_until" value="{{ old('recurrence_until') }}"
                           :required="showRecurrence && showUntil"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('recurrence_until') ? 'border-red-400' : '' }}">
                    @error('recurrence_until')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Info-Text für Saisonende --}}
                <div class="md:col-span-2" x-show="showRecurrence && !showUntil" x-transition>
                    <div class="flex items-center gap-2 text-sm text-gray-600 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3">
                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>
                            Wöchentliche Wiederholung bis zum Saisonende am
                            <strong>{{ $currentSeason?->end_date->format('d.m.Y') }}</strong>.
                            Termine in eingetragenen Ferienzeiten werden automatisch ausgespart.
                        </span>
                    </div>
                </div>

                {{-- Ferienhinweis (allgemein) --}}
                <div class="md:col-span-2" x-show="showRecurrence && showUntil" x-transition>
                    <p class="text-xs text-gray-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Termine in eingetragenen Ferienzeiten werden automatisch ausgespart.
                    </p>
                </div>

                {{-- Notizen --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notizen / Trainingsplan</label>
                    <textarea name="notes" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                              placeholder="Beschreibung des Trainings, besondere Übungen, Ziele...">{{ old('notes') }}</textarea>
                </div>

                {{-- Teamplan Anhang --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teamplan (Anhang)</label>
                    <input type="file" name="team_plan" accept=".pdf,.doc,.docx,.jpg,.png"
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-1">PDF, Word, JPG oder PNG – max. 5 MB</p>
                    @error('team_plan')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Einheit anlegen
                </button>
                <a href="{{ route('trainer.sessions.index') }}"
                   class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

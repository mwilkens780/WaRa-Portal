@extends('layouts.app')
@section('title', 'Gruppe bearbeiten: ' . $trainingGroup->name)
@section('page-title', 'Trainingsgruppe bearbeiten')

@section('content')
<div class="mt-2 max-w-3xl space-y-6">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.training-groups.update', $trainingGroup) }}">
        @csrf
        @method('PUT')

        {{-- Basis --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Gruppendetails</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $trainingGroup->name) }}" required maxlength="100"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none @error('name') border-red-400 @enderror">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="description" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">{{ old('description', $trainingGroup->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gruppentyp <span class="text-red-500">*</span></label>
                <select name="group_type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none @error('group_type') border-red-400 @enderror">
                    @foreach(\App\Models\TrainingGroup::GROUP_TYPES as $key => $label)
                        <option value="{{ $key }}" {{ old('group_type', $trainingGroup->group_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('group_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-start gap-8">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Farbe <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Models\TrainingGroup::COLORS as $key => $cls)
                            <label class="cursor-pointer">
                                <input type="radio" name="color" value="{{ $key }}" class="sr-only peer"
                                       {{ old('color', $trainingGroup->color) === $key ? 'checked' : '' }}>
                                <span class="block w-7 h-7 rounded-full {{ $cls['dot'] }} ring-2 ring-transparent peer-checked:ring-offset-2 peer-checked:ring-gray-400 transition-all"></span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-6">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" id="active" value="1"
                           {{ old('active', $trainingGroup->active) ? 'checked' : '' }}
                           class="w-4 h-4 text-primary rounded border-gray-300">
                    <label for="active" class="text-sm text-gray-700">Aktiv</label>
                </div>
            </div>
        </div>

        {{-- Trainer (admin only) --}}
        @if(auth()->user()->isAdmin())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide mb-4">Trainer</h2>
            @if($trainers->isEmpty())
                <p class="text-sm text-gray-400">Keine aktiven Trainer vorhanden.</p>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach($trainers as $trainer)
                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50">
                            <input type="checkbox" name="trainers[]" value="{{ $trainer->id }}"
                                   {{ in_array($trainer->id, old('trainers', $assignedTrainers)) ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary rounded border-gray-300">
                            <span class="text-gray-700">{{ $trainer->lastname }}, {{ $trainer->firstname }}</span>
                            @if($trainer->role === 'admin')
                                <span class="text-xs text-gray-400">(Admin)</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        {{-- Schwimmer hinzufügen (nur keiner anderen Gruppe zugeordnete) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-data="{ search: '' }">
            <div class="flex items-center justify-between mb-1">
                <div>
                    <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Schwimmer hinzufügen</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Nur Schwimmer ohne Gruppenzuordnung · CSV-Import für vollständige Synchronisation</p>
                </div>
                <input type="text" x-model="search" placeholder="Suchen..."
                       class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 outline-none w-40">
            </div>
            @if($swimmers->isEmpty())
                <p class="text-sm text-gray-400 mt-3">Keine nicht zugeordneten Schwimmer verfügbar.</p>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto mt-3">
                    @foreach($swimmers as $swimmer)
                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50"
                               x-show="search === '' || '{{ strtolower($swimmer->lastname . ' ' . $swimmer->firstname) }}'.includes(search.toLowerCase())">
                            <input type="checkbox" name="swimmers[]" value="{{ $swimmer->id }}"
                                   {{ in_array($swimmer->id, old('swimmers', [])) ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary rounded border-gray-300">
                            <span class="text-gray-700">{{ $swimmer->lastname }}, {{ $swimmer->firstname }}</span>
                            @if($swimmer->birth_date)
                                <span class="text-xs text-gray-400 ml-auto">{{ $swimmer->birth_date->format('Y') }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Trainingseinheiten --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-data="{ tab: 'linked' }">
            <div class="flex items-center gap-4 mb-4 border-b border-gray-100 pb-3">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide flex-1">Trainingseinheiten</h2>
                <button type="button" @click="tab = 'linked'"
                        :class="tab === 'linked' ? 'text-primary border-b-2 border-primary' : 'text-gray-500'"
                        class="text-sm pb-1 font-medium transition-colors">
                    Zugeordnet ({{ $linkedSessions->count() }})
                </button>
                @if($availableSessions->isNotEmpty())
                <button type="button" @click="tab = 'available'"
                        :class="tab === 'available' ? 'text-primary border-b-2 border-primary' : 'text-gray-500'"
                        class="text-sm pb-1 font-medium transition-colors">
                    Verfügbar ({{ $availableSessions->count() }})
                </button>
                @endif
            </div>

            {{-- Zugeordnete Einheiten --}}
            <div x-show="tab === 'linked'">
                @if($linkedSessions->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-4">Keine Einheiten zugeordnet.</p>
                @else
                    <div class="space-y-1 max-h-60 overflow-y-auto">
                        @foreach($linkedSessions as $session)
                            <label class="flex items-center gap-3 text-sm cursor-pointer px-2 py-2 rounded-lg hover:bg-gray-50">
                                <input type="checkbox" name="unlink_sessions[]" value="{{ $session->id }}"
                                       class="w-4 h-4 text-red-500 rounded border-gray-300">
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-gray-800">{{ $session->title }}</span>
                                    <span class="text-gray-400 ml-2">{{ $session->date->format('d.m.Y') }}</span>
                                </div>
                                <span class="text-xs text-gray-400">Anh. entfernen</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Häkchen setzen = Zuordnung entfernen beim Speichern</p>
                @endif
            </div>

            {{-- Verfügbare Einheiten --}}
            @if($availableSessions->isNotEmpty())
            <div x-show="tab === 'available'">
                <div class="space-y-1 max-h-60 overflow-y-auto">
                    @foreach($availableSessions as $session)
                        <label class="flex items-center gap-3 text-sm cursor-pointer px-2 py-2 rounded-lg hover:bg-gray-50">
                            <input type="checkbox" name="link_sessions[]" value="{{ $session->id }}"
                                   class="w-4 h-4 text-primary rounded border-gray-300">
                            <div class="flex-1 min-w-0">
                                <span class="font-medium text-gray-800">{{ $session->title }}</span>
                                <span class="text-gray-400 ml-2">{{ $session->date->format('d.m.Y') }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-2">Häkchen setzen = dieser Gruppe zuordnen beim Speichern</p>
            </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-6 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
                Speichern
            </button>
            <a href="{{ route('admin.training-groups.show', $trainingGroup) }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                Abbrechen
            </a>
        </div>
    </form>

</div>
@endsection

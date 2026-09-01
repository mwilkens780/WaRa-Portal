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

            @if(auth()->user()->isAdmin())
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">WebClub-ID</label>
                <input type="number" name="webclub_id" value="{{ old('webclub_id', $trainingGroup->webclub_id) }}"
                       min="1" placeholder="Wird automatisch per Crawler gesetzt"
                       class="w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none @error('webclub_id') border-red-400 @enderror">
                <p class="text-xs text-gray-400 mt-1">Numerische Gruppen-ID aus WebClub (Stammdaten → Gruppen). Wird vom Crawler automatisch befüllt.</p>
                @error('webclub_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @endif

            @php
                $typeColorMap    = \App\Models\TrainingGroup::TYPE_COLORS;
                $customColorKeys = \App\Models\TrainingGroup::CUSTOM_COLORS;
                $allColors       = \App\Models\TrainingGroup::COLORS;
                $initType        = old('group_type', $trainingGroup->group_type ?? 'breitensport');
                $typeDefault     = $typeColorMap[$initType] ?? 'blue';
                $initColor       = old('color', $trainingGroup->color ?? $typeDefault);
                $initCustomColor = in_array($initColor, $customColorKeys) ? $initColor : '';
            @endphp
            <div x-data="{
                    groupType: '{{ $initType }}',
                    customColor: '{{ $initCustomColor }}',
                    typeColors: @js($typeColorMap),
                    get typeColor() { return this.typeColors[this.groupType] || 'blue'; },
                    get activeColor() { return this.customColor || this.typeColor; }
                }">
                <input type="hidden" name="color" :value="activeColor">

                {{-- Gruppentyp --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gruppentyp <span class="text-red-500">*</span></label>
                    <select name="group_type" x-model="groupType" @change="customColor = ''"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none @error('group_type') border-red-400 @enderror">
                        @foreach(\App\Models\TrainingGroup::GROUP_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('group_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Automatische Standardfarbe --}}
                <div class="mb-3">
                    <p class="text-xs font-medium text-gray-600 mb-1.5">Standardfarbe dieses Typs</p>
                    <div class="flex items-center gap-2 h-7">
                        @foreach($typeColorMap as $type => $colorKey)
                        @php $cls = $allColors[$colorKey]; @endphp
                        <div x-show="groupType === '{{ $type }}'" class="flex items-center gap-2" style="display:none">
                            <span class="w-5 h-5 rounded-full flex-shrink-0 {{ $cls['dot'] }}"></span>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $cls['badge'] }}">{{ $cls['label'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Individuelle Abweichungsfarbe --}}
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-1.5">Abweichende Farbe <span class="text-gray-400 font-normal">(optional)</span></p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="customColor = ''" title="Standardfarbe verwenden"
                                class="w-8 h-8 rounded-full bg-white border-2 flex items-center justify-center transition-all"
                                :class="customColor === '' ? 'border-gray-500 ring-2 ring-offset-1 ring-gray-400' : 'border-dashed border-gray-300'">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        @foreach($customColorKeys as $colorKey)
                        @php $cls = $allColors[$colorKey]; @endphp
                        <label class="cursor-pointer" title="{{ $cls['label'] }}">
                            <input type="radio" x-model="customColor" value="{{ $colorKey }}" class="sr-only peer">
                            <span class="block w-7 h-7 rounded-full {{ $cls['dot'] }} ring-2 ring-transparent peer-checked:ring-offset-2 peer-checked:ring-gray-400 transition-all"></span>
                        </label>
                        @endforeach
                    </div>
                    @error('color') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1">Nur wählen, wenn du von der Standardfarbe des Typs abweichen möchtest.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" id="active" value="1"
                       {{ old('active', $trainingGroup->active) ? 'checked' : '' }}
                       class="w-4 h-4 text-primary rounded border-gray-300">
                <label for="active" class="text-sm text-gray-700">Aktiv</label>
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

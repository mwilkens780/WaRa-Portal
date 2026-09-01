@extends('layouts.app')
@section('title', 'Trainingsgruppe anlegen')
@section('page-title', 'Trainingsgruppe anlegen')

@section('content')
<div class="mt-2 max-w-3xl">

    <form method="POST" action="{{ route('admin.training-groups.store') }}" class="space-y-6">
        @csrf

        {{-- Basis --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Gruppendetails</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none @error('name') border-red-400 @enderror">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="description" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none @error('description') border-red-400 @enderror">{{ old('description') }}</textarea>
            </div>

            @php
                $typeColorMap   = \App\Models\TrainingGroup::TYPE_COLORS;
                $customColorKeys = \App\Models\TrainingGroup::CUSTOM_COLORS;
                $allColors       = \App\Models\TrainingGroup::COLORS;
                $initType        = old('group_type', 'breitensport');
                $initColor       = old('color', '');
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
                       {{ old('active', '1') ? 'checked' : '' }}
                       class="w-4 h-4 text-primary rounded border-gray-300">
                <label for="active" class="text-sm text-gray-700">Aktiv</label>
            </div>
        </div>

        {{-- Trainer --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide mb-4">Trainer zuweisen</h2>
            @if($trainers->isEmpty())
                <p class="text-sm text-gray-400">Keine aktiven Trainer vorhanden.</p>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach($trainers as $trainer)
                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50">
                            <input type="checkbox" name="trainers[]" value="{{ $trainer->id }}"
                                   {{ in_array($trainer->id, old('trainers', [])) ? 'checked' : '' }}
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

        {{-- Schwimmer --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-data="{ search: '' }">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Schwimmer zuweisen</h2>
                <input type="text" x-model="search" placeholder="Suchen..." class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 outline-none w-40">
            </div>
            @if($swimmers->isEmpty())
                <p class="text-sm text-gray-400">Keine aktiven Schwimmer vorhanden.</p>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto">
                    @foreach($swimmers as $swimmer)
                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50"
                               x-show="search === '' || '{{ strtolower($swimmer->lastname . ' ' . $swimmer->firstname) }}'.includes(search.toLowerCase())">
                            <input type="checkbox" name="swimmers[]" value="{{ $swimmer->id }}"
                                   {{ in_array($swimmer->id, old('swimmers', [])) ? 'checked' : '' }}
                                   class="w-4 h-4 text-primary rounded border-gray-300">
                            <span class="text-gray-700">{{ $swimmer->lastname }}, {{ $swimmer->firstname }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-6 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
                Gruppe anlegen
            </button>
            <a href="{{ route('admin.training-groups.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                Abbrechen
            </a>
        </div>
    </form>

</div>
@endsection

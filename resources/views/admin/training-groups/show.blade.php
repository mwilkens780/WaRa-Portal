@extends('layouts.app')
@section('title', $trainingGroup->name)
@section('page-title', $trainingGroup->name)

@section('content')
@php $colors = $trainingGroup->colorDots; @endphp
<div class="mt-2 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-4 h-4 rounded-full {{ $colors['dot'] }}"></span>
            <span class="{{ $colors['badge'] }} text-xs font-medium px-2.5 py-1 rounded-full">
                {{ ucfirst($trainingGroup->color) }}
            </span>
            @if(!$trainingGroup->active)
                <span class="bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-1 rounded-full">Inaktiv</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.training-groups.edit', $trainingGroup) }}"
               class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Bearbeiten
            </a>
            <a href="{{ route('admin.training-groups.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                Zurück
            </a>
        </div>
    </div>

    @if($trainingGroup->description)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-600">{{ $trainingGroup->description }}</p>
        </div>
    @endif

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    @if($trainingGroup->trainers->isEmpty())
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <div>
                <p class="font-semibold text-amber-800">Kein Trainer zugewiesen</p>
                <p class="text-amber-700 mt-0.5">
                    Dieser Gruppe ist kein Trainer zugeordnet.
                    <a href="{{ route('admin.training-groups.edit', $trainingGroup) }}" class="underline font-medium">Trainer jetzt zuweisen →</a>
                </p>
            </div>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">

        {{-- Trainer --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Trainer</h2>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $trainingGroup->trainers->count() }}</span>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($trainingGroup->trainers as $trainer)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-sm flex-shrink-0">
                            {{ strtoupper(substr($trainer->firstname, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $trainer->firstname }} {{ $trainer->lastname }}</p>
                            <p class="text-xs text-gray-500">{{ \App\Models\User::ROLE_LABELS[$trainer->role] ?? $trainer->role }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 px-5 py-4 text-center">Keine Trainer zugewiesen.</p>
                @endforelse
            </div>
        </div>

        {{-- Aktive Schwimmer --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h2 class="font-semibold text-gray-800">Schwimmer</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Nur aktive Mitglieder</p>
                </div>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $activeSwimmers->count() }}</span>
            </div>
            <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
                @forelse($activeSwimmers as $swimmer)
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-semibold text-sm flex-shrink-0">
                            {{ strtoupper(substr($swimmer->firstname, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $swimmer->firstname }} {{ $swimmer->lastname }}</p>
                            @if($swimmer->birth_date)
                                <p class="text-xs text-gray-500">Jg. {{ $swimmer->birth_date->format('Y') }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.training-groups.remove-swimmer', [$trainingGroup, $swimmer]) }}"
                              onsubmit="return confirm('{{ $swimmer->firstname }} {{ $swimmer->lastname }} aus der Gruppe entfernen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-600 transition-colors p-1" title="Aus Gruppe entfernen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 px-5 py-4 text-center">Keine aktiven Schwimmer zugewiesen.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Gruppenziele ────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100" x-data="{ showAddGoal: false }">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-800">Gruppenziele</h2>
                <p class="text-xs text-gray-400 mt-0.5">Qualifikationskriterien und Trainingsziele für diese Gruppe</p>
            </div>
            <button @click="showAddGoal = !showAddGoal" type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Ziel hinzufügen
            </button>
        </div>

        {{-- Neues Ziel hinzufügen --}}
        <div x-show="showAddGoal" x-transition class="p-5 border-b border-gray-100 bg-gray-50">
            <form method="POST" action="{{ route('admin.training-groups.goals.store', $trainingGroup) }}" class="space-y-3">
                @csrf
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Ziel <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required maxlength="255"
                               placeholder="z.B. 1x NDM Norm schwimmen"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Art</label>
                        <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="quantitative">Messbar (Quantitativ)</option>
                            <option value="qualitative">Qualitativ</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Zielwert <span class="text-gray-400">(optional, z.B. "80%", "1x")</span></label>
                        <input type="text" name="target_value" maxlength="255"
                               placeholder="z.B. 80% oder 1x NDM Norm"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Beschreibung <span class="text-gray-400">(optional)</span></label>
                        <textarea name="description" rows="2" maxlength="1000"
                                  placeholder="Genauere Erläuterung des Ziels..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">Ziel speichern</button>
                    <button type="button" @click="showAddGoal = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">Abbrechen</button>
                </div>
            </form>
        </div>

        {{-- Zielliste --}}
        @if($goals->isEmpty())
            <p class="text-sm text-gray-400 px-5 py-6 text-center">Noch keine Ziele definiert.</p>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($goals as $goal)
            <div x-data="{ editing: false, showEvals: false }" class="p-5">
                {{-- Ziel-Header --}}
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-0.5">
                            @if($goal->type === 'quantitative')
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Messbar</span>
                            @else
                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Qualitativ</span>
                            @endif
                            <span class="text-sm font-semibold text-gray-800">{{ $goal->title }}</span>
                            @if($goal->target_value)
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Ziel: {{ $goal->target_value }}</span>
                            @endif
                        </div>
                        @if($goal->description)
                            <p class="text-xs text-gray-500 mt-1">{{ $goal->description }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($activeSwimmers->isNotEmpty())
                        <button @click="showEvals = !showEvals" type="button"
                                class="text-xs px-2.5 py-1 border rounded-lg transition-colors"
                                :class="showEvals ? 'border-indigo-300 text-indigo-600 bg-indigo-50' : 'border-gray-200 text-gray-500 hover:bg-gray-50'">
                            Bewertungen
                        </button>
                        @endif
                        <button @click="editing = !editing" type="button"
                                class="text-xs text-gray-400 hover:text-gray-600 transition-colors" title="Bearbeiten">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <form method="POST" action="{{ route('admin.training-groups.goals.destroy', [$trainingGroup, $goal]) }}"
                              onsubmit="return confirm('Ziel löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600" title="Löschen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Inline-Bearbeitungsformular --}}
                <div x-show="editing" x-transition class="mt-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <form method="POST" action="{{ route('admin.training-groups.goals.update', [$trainingGroup, $goal]) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Titel</label>
                                <input type="text" name="title" value="{{ $goal->title }}" required maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Art</label>
                                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                    <option value="quantitative" {{ $goal->type === 'quantitative' ? 'selected' : '' }}>Messbar</option>
                                    <option value="qualitative" {{ $goal->type === 'qualitative' ? 'selected' : '' }}>Qualitativ</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Zielwert</label>
                                <input type="text" name="target_value" value="{{ $goal->target_value }}" maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Beschreibung</label>
                                <textarea name="description" rows="2" maxlength="1000"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ $goal->description }}</textarea>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">Speichern</button>
                            <button type="button" @click="editing = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">Abbrechen</button>
                        </div>
                    </form>
                </div>

                {{-- Bewertungsübersicht --}}
                @if($activeSwimmers->isNotEmpty())
                <div x-show="showEvals" x-transition class="mt-3">
                    <p class="text-xs text-gray-500 mb-2 font-medium">Zielgespräch – Bewertungen der Schwimmer:</p>
                    <div class="space-y-2">
                        @foreach($activeSwimmers as $swimmer)
                        @php
                            $selfEval    = $goal->selfEvaluationFor($swimmer->id);
                            $trainerEval = $goal->trainerEvaluationFor($swimmer->id);
                        @endphp
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <div class="flex flex-wrap items-center gap-3 mb-2">
                                <span class="text-sm font-medium text-gray-800">{{ $swimmer->firstname }} {{ $swimmer->lastname }}</span>
                                {{-- Eigenbewertung --}}
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-gray-400">Eigenbewertung:</span>
                                    @if($selfEval)
                                        <span class="text-xs font-semibold {{ $selfEval->rating_color }}">
                                            {{ str_repeat('★', $selfEval->rating) }}{{ str_repeat('☆', 5 - $selfEval->rating) }}
                                            {{ $selfEval->rating_label }}
                                        </span>
                                        @if($selfEval->current_value)
                                            <span class="text-xs text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $selfEval->current_value }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-300">Noch nicht bewertet</span>
                                    @endif
                                </div>
                                {{-- Trainerbewertung --}}
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-gray-400">Trainer:</span>
                                    @if($trainerEval)
                                        <span class="text-xs font-semibold {{ $trainerEval->rating_color }}">
                                            {{ str_repeat('★', $trainerEval->rating) }}{{ str_repeat('☆', 5 - $trainerEval->rating) }}
                                            {{ $trainerEval->rating_label }}
                                        </span>
                                        @if($trainerEval->current_value)
                                            <span class="text-xs text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $trainerEval->current_value }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-300">Noch nicht bewertet</span>
                                    @endif
                                </div>
                            </div>
                            {{-- Notizen --}}
                            @if($selfEval?->notes || $trainerEval?->notes)
                            <div class="flex flex-wrap gap-3 mb-2">
                                @if($selfEval?->notes)
                                    <p class="text-xs text-gray-500 italic">"{{ $selfEval->notes }}"</p>
                                @endif
                                @if($trainerEval?->notes)
                                    <p class="text-xs text-gray-600 border-l-2 border-primary/30 pl-2">{{ $trainerEval->notes }}</p>
                                @endif
                            </div>
                            @endif
                            {{-- Trainerbewertung erfassen --}}
                            <details class="mt-1">
                                <summary class="text-xs text-primary cursor-pointer hover:underline select-none">Trainerbewertung {{ $trainerEval ? 'aktualisieren' : 'erfassen' }}</summary>
                                <form method="POST" action="{{ route('admin.training-groups.goals.trainer-eval', [$trainingGroup, $goal, $swimmer]) }}"
                                      class="mt-2 flex flex-wrap gap-2 items-end">
                                    @csrf
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Bewertung</label>
                                        <select name="rating" class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                                            <option value="">– keine –</option>
                                            @foreach(\App\Models\TrainingGroupGoal::$ratingLabels as $val => $label)
                                                <option value="{{ $val }}" {{ $trainerEval?->rating == $val ? 'selected' : '' }}>{{ $val }}★ {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if($goal->type === 'quantitative')
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Aktueller Stand</label>
                                        <input type="text" name="current_value" maxlength="100"
                                               value="{{ $trainerEval?->current_value }}"
                                               placeholder="z.B. 72%"
                                               class="px-2 py-1.5 border border-gray-300 rounded text-xs w-24 focus:ring-1 focus:ring-blue-400 outline-none">
                                    </div>
                                    @endif
                                    <div class="flex-1 min-w-[160px]">
                                        <label class="block text-xs text-gray-500 mb-1">Notiz</label>
                                        <input type="text" name="notes" maxlength="1000"
                                               value="{{ $trainerEval?->notes }}"
                                               placeholder="Kommentar..."
                                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                                    </div>
                                    <button type="submit" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                                        Speichern
                                    </button>
                                </form>
                            </details>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- CSV-Import ──────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-data="{ open: false }">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-800 text-sm">Schwimmer per CSV importieren</h2>
                <p class="text-xs text-gray-500 mt-0.5">Format: <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">Name;Jg.;DSV-Id;Aktiv;</span></p>
            </div>
            <button @click="open = !open" type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                CSV hochladen
            </button>
        </div>

        <div x-show="open" x-transition class="mt-4 border-t border-gray-100 pt-4">
            <p class="text-xs text-gray-500 mb-3">
                Ein erneutes Einlesen aktualisiert bestehende Einträge, fügt neue hinzu und entfernt Schwimmer,
                die nicht mehr in der CSV enthalten sind. Ein Schwimmer kann nur einer Gruppe zugeordnet sein.
            </p>
            <form method="POST" action="{{ route('admin.training-groups.csv-upload', $trainingGroup) }}"
                  enctype="multipart/form-data" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">CSV-Datei</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required
                           class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-primary text-white font-semibold rounded-lg text-sm hover:bg-primary-dark transition-colors">
                    Einlesen → Vorschau
                </button>
            </form>
            @error('csv_file')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Bevorstehende Einheiten --}}
    @if($upcomingSessions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Bevorstehende Einheiten</h2>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($upcomingSessions as $session)
                <a href="{{ route('trainer.sessions.show', $session) }}"
                   class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[50px]">
                        <p class="text-xs font-semibold text-primary">{{ $session->date->format('d.M') }}</p>
                        <p class="text-xs text-primary/70">{{ $session->date->isoFormat('ddd') }}</p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-gray-800 truncate">{{ $session->title }}</p>
                        <p class="text-xs text-gray-500">{{ $session->type_label }} · {{ substr($session->start_time, 0, 5) }} Uhr</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Zugeordnete Einheiten --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Zugeordnete Einheiten</h2>
            <a href="{{ route('admin.training-groups.edit', $trainingGroup) }}" class="text-xs text-primary hover:underline">Verwalten</a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($recentSessions as $session)
                <a href="{{ route('trainer.sessions.show', $session) }}"
                   class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div class="text-center bg-gray-50 rounded-lg p-2 min-w-[50px]">
                        <p class="text-xs font-semibold text-gray-700">{{ $session->date->format('d.M') }}</p>
                        <p class="text-xs text-gray-400">{{ $session->date->format('Y') }}</p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-gray-800 truncate">{{ $session->title }}</p>
                        <p class="text-xs text-gray-500">{{ $session->type_label }}</p>
                    </div>
                </a>
            @empty
                <p class="text-sm text-gray-400 px-5 py-6 text-center">Noch keine Trainingseinheiten zugeordnet.</p>
            @endforelse
        </div>
    </div>

    {{-- Danger zone (admin only) --}}
    @if(auth()->user()->isAdmin())
    <div class="bg-white rounded-xl shadow-sm border border-red-100 p-6">
        <h2 class="font-semibold text-red-700 text-sm mb-3">Gruppe löschen</h2>
        <p class="text-sm text-gray-500 mb-4">Die Gruppe wird unwiderruflich gelöscht. Zugeordnete Trainingseinheiten bleiben erhalten, verlieren aber die Gruppenzuordnung.</p>
        <form method="POST" action="{{ route('admin.training-groups.destroy', $trainingGroup) }}"
              onsubmit="return confirm('Trainingsgruppe \"{{ $trainingGroup->name }}\" wirklich löschen?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                Gruppe löschen
            </button>
        </form>
    </div>
    @endif

</div>
@endsection

@extends('layouts.app')
@section('title', 'Trainingsplan – ' . $session->title)
@section('page-title', 'Trainingsplan')

@section('content')
<div class="mt-2 space-y-4"
     x-data="planBuilder({{ json_encode($initialBlocks) }}, {{ $targetSeconds }})"
     x-init="init()">

    {{-- Session-Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <div class="bg-primary/10 rounded-lg p-2 text-center min-w-[52px]">
                <p class="text-sm font-bold text-primary leading-none">{{ $session->date->format('d') }}</p>
                <p class="text-xs text-primary/70">{{ $session->date->isoFormat('MMM') }}</p>
            </div>
            <div>
                <p class="font-semibold text-gray-800">{{ $session->title }}</p>
                <p class="text-xs text-gray-500">
                    {{ $session->start_time }}@if($session->end_time) – {{ $session->end_time }} Uhr @endif
                    · {{ $session->location }}
                    · <span class="inline-block {{ $session->type_color }} px-1.5 py-0.5 rounded-full text-xs font-medium">{{ $session->type_label }}</span>
                </p>
            </div>
        </div>
        <a href="{{ route('trainer.sessions.show', $session) }}"
           class="text-sm text-gray-500 hover:text-primary flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Zur Einheit
        </a>
    </div>

    {{-- Hauptgrid: Builder links, Zusammenfassung rechts --}}
    <div class="grid xl:grid-cols-3 gap-4 items-start">

        {{-- ── LINKE SPALTE: Formular & Blöcke ── --}}
        <div class="xl:col-span-2 space-y-4">

            {{-- Flash --}}
            @if(session('success'))
                <div class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            <form x-ref="form" method="POST" action="{{ route('trainer.sessions.plan.save', $session) }}"
                  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="blocks_json" x-ref="blocksJson">

                {{-- Beschreibung --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Beschreibung / Trainingsziel</label>
                    <textarea name="description" rows="3" x-model="description"
                              placeholder="Ziel der Einheit, Schwerpunkte, Hinweise für Trainer..."
                              class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none resize-y">{{ old('description', $plan?->description) }}</textarea>
                </div>

                {{-- Blöcke --}}
                <div class="space-y-3">
                    <template x-for="(block, index) in blocks" :key="block._key">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                            {{-- Block-Header --}}
                            <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 border-b border-gray-100">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide w-16 flex-shrink-0"
                                      x-text="'Block ' + (index + 1)"></span>
                                <input type="text" x-model="block.label"
                                       placeholder="Bezeichnung (z.B. Aufwärmen, Hauptset, Abwärmen)"
                                       class="flex-1 text-sm font-medium border-0 bg-transparent focus:ring-0 outline-none text-gray-700 placeholder-gray-300 min-w-0">
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <button type="button" @click="moveBlock(index, -1)"
                                            :disabled="index === 0"
                                            class="p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-600 disabled:opacity-30 transition-colors"
                                            title="Nach oben">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" @click="moveBlock(index, 1)"
                                            :disabled="index === blocks.length - 1"
                                            class="p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-600 disabled:opacity-30 transition-colors"
                                            title="Nach unten">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <button type="button" @click="removeBlock(index)"
                                            class="p-1.5 rounded hover:bg-red-100 text-gray-300 hover:text-red-500 transition-colors ml-1"
                                            title="Block löschen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Block-Body --}}
                            <div class="p-4 space-y-4">

                                {{-- Zeile 1: Wiederholungen × Distanz --}}
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-gray-500 font-medium">Wdh.</label>
                                        <input type="number" x-model="block.repetitions" min="1" max="999"
                                               placeholder="4"
                                               class="w-20 px-3 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary outline-none font-semibold">
                                    </div>
                                    <span class="text-gray-400 font-bold text-lg">×</span>
                                    <div class="flex items-center gap-2">
                                        <input type="number" x-model="block.distance" min="1" max="9999"
                                               placeholder="100"
                                               class="w-24 px-3 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary outline-none font-semibold">
                                        <span class="text-sm text-gray-500 font-medium">m</span>
                                    </div>
                                    {{-- Block-Meter mini summary --}}
                                    <span x-show="block.repetitions && block.distance"
                                          class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-full"
                                          x-text="'= ' + ((parseInt(block.repetitions)||0) * (parseInt(block.distance)||0)) + 'm'"></span>
                                </div>

                                {{-- Zeile 2: Stilarten --}}
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-2">Stilart(en)</p>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="disc in disciplineOptions" :key="disc.key">
                                            <button type="button"
                                                    @click="toggleItem(block.disciplines, disc.key)"
                                                    :class="block.disciplines.includes(disc.key)
                                                        ? 'bg-primary text-white border-primary shadow-sm'
                                                        : 'bg-white text-gray-600 border-gray-200 hover:border-primary/50 hover:text-primary'"
                                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium transition-all">
                                                <span class="font-bold" x-text="disc.short"></span>
                                                <span class="text-xs opacity-80" x-text="disc.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Zeile 3: Materialien --}}
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-2">Materialien</p>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="mat in materialOptions" :key="mat">
                                            <button type="button"
                                                    @click="toggleItem(block.materials, mat)"
                                                    :class="block.materials.includes(mat)
                                                        ? 'bg-teal-500 text-white border-teal-500 shadow-sm'
                                                        : 'bg-white text-gray-600 border-gray-200 hover:border-teal-300 hover:text-teal-700'"
                                                    class="px-3 py-1.5 rounded-lg border text-sm font-medium transition-all"
                                                    x-text="mat">
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Zeile 4: Zusätze --}}
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-2">Zusätze</p>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="add in additionOptions" :key="add">
                                            <button type="button"
                                                    @click="toggleItem(block.additions, add)"
                                                    :class="block.additions.includes(add)
                                                        ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                                                        : 'bg-white text-gray-600 border-gray-200 hover:border-amber-300 hover:text-amber-700'"
                                                    class="px-3 py-1.5 rounded-lg border text-sm font-medium transition-all"
                                                    x-text="add">
                                            </button>
                                        </template>
                                        {{-- Freie Zusatz-Eingabe --}}
                                        <div class="flex items-center gap-1">
                                            <input type="text" x-ref="addInput" @keydown.enter.prevent="addCustomAddition(block)"
                                                   placeholder="Eigener Zusatz + Enter"
                                                   class="px-3 py-1.5 border border-dashed border-gray-200 rounded-lg text-sm text-gray-600 focus:ring-2 focus:ring-amber-300 outline-none w-44">
                                        </div>
                                        {{-- Benutzerdefinierte Zusätze als Tags --}}
                                        <template x-for="add in block.additions.filter(a => !additionOptions.includes(a))" :key="add">
                                            <span class="flex items-center gap-1 bg-amber-500 text-white text-sm font-medium px-3 py-1.5 rounded-lg">
                                                <span x-text="add"></span>
                                                <button type="button" @click="toggleItem(block.additions, add)" class="ml-1 hover:opacity-70">×</button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Zeile 5: Kommentar --}}
                                <div>
                                    <label class="text-xs font-medium text-gray-500 mb-1.5 block">Kommentar / Hinweise</label>
                                    <textarea x-model="block.comment" rows="2"
                                              placeholder='z.B. "Wende bis Wende", "3er/5er Atmung", "Sonderregel für Sportler X", "Achten auf Körperlage"...'
                                              class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none resize-y"></textarea>
                                </div>

                                {{-- Zeile 6: Zeiten --}}
                                <div class="grid sm:grid-cols-3 gap-4 bg-blue-50/50 rounded-lg p-3 border border-blue-100">
                                    <div>
                                        <p class="text-xs font-medium text-blue-700 mb-1.5">Startzeit / Intervall</p>
                                        <div class="flex items-center gap-1">
                                            <input type="number" x-model="block.start_interval_min"
                                                   min="0" max="99" placeholder="0"
                                                   class="w-16 px-2 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                                            <span class="text-gray-400 font-semibold">:</span>
                                            <input type="number" x-model="block.start_interval_sec"
                                                   min="0" max="59" placeholder="00"
                                                   class="w-16 px-2 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                                            <span class="text-xs text-gray-400 ml-1">min:sek</span>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-blue-700 mb-1.5">Regeneration / Pause</p>
                                        <div class="flex items-center gap-1">
                                            <input type="number" x-model="block.recovery_min"
                                                   min="0" max="99" placeholder="0"
                                                   class="w-16 px-2 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                                            <span class="text-gray-400 font-semibold">:</span>
                                            <input type="number" x-model="block.recovery_sec"
                                                   min="0" max="59" placeholder="00"
                                                   class="w-16 px-2 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                                            <span class="text-xs text-gray-400 ml-1">min:sek</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div>
                                            <p class="text-xs font-medium text-blue-700 mb-1">Block-Dauer</p>
                                            <p class="text-lg font-bold text-blue-700"
                                               x-text="formatTime(blockSeconds(block))"></p>
                                            <p class="text-xs text-blue-500"
                                               x-show="block.repetitions && blockIntervalSeconds(block) > 0"
                                               x-text="(parseInt(block.repetitions)||0) + ' × ' + formatTime(blockIntervalSeconds(block))
                                                    + (blockRecoverySeconds(block) > 0 ? ' + ' + formatTime(blockRecoverySeconds(block)) + ' Pause' : '')">
                                            </p>
                                        </div>
                                    </div>
                                </div>

                            </div>{{-- /block-body --}}
                        </div>{{-- /block-card --}}
                    </template>
                </div>{{-- /blocks --}}

                {{-- Block hinzufügen --}}
                <div class="mt-3">
                    <button type="button" @click="addBlock()"
                            class="w-full py-3 border-2 border-dashed border-gray-200 rounded-xl text-sm font-medium text-gray-500 hover:border-primary hover:text-primary hover:bg-primary/5 transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Block hinzufügen
                    </button>
                </div>

                {{-- Anhang --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mt-4">
                    <p class="text-sm font-semibold text-gray-700 mb-3">Anhang (PDF oder Bild)</p>
                    @if($plan?->attachment_path)
                        <div class="flex items-center gap-3 p-3 bg-blue-50 rounded-lg border border-blue-100 mb-3">
                            <svg class="w-7 h-7 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-blue-800">Anhang vorhanden</p>
                                <a href="{{ route('sessions.plan.download', [$session, 'team']) }}"
                                   class="text-xs text-blue-600 hover:underline">Herunterladen</a>
                            </div>
                            <form method="POST" action="{{ route('trainer.sessions.plan.attachment.delete', $session) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition-colors">
                                    Löschen
                                </button>
                            </form>
                        </div>
                    @endif
                    <div class="flex gap-2 items-center">
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"
                               class="flex-1 text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    </div>
                    @error('attachment')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Mobile: Speichern-Button --}}
                <div class="xl:hidden mt-4">
                    <button type="button" @click="submitForm()"
                            class="w-full bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-3 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Trainingsplan speichern
                    </button>
                </div>

            </form>
        </div>{{-- /linke Spalte --}}

        {{-- ── RECHTE SPALTE: Zusammenfassung (sticky) ── --}}
        <div class="space-y-4 xl:sticky xl:top-4">

            {{-- Zeitplanung --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-semibold text-gray-700 mb-4">Zeitplanung</p>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Verplant</span>
                        <span class="font-mono font-bold text-gray-800 text-lg" x-text="formatTime(totalSeconds)"></span>
                    </div>
                    @if($targetSeconds > 0)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Ziel (Einheit)</span>
                        <span class="font-mono text-gray-500 text-sm">{{ sprintf('%d:%02d', intdiv($targetSeconds, 60), $targetSeconds % 60) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold" :class="remainingSeconds >= 0 ? 'text-green-700' : 'text-red-600'">
                            Verbleibend
                        </span>
                        <span class="font-mono font-bold text-lg"
                              :class="remainingSeconds >= 0 ? 'text-green-700' : 'text-red-600'"
                              x-text="(remainingSeconds < 0 ? '−' : '') + formatTime(Math.abs(remainingSeconds))"></span>
                    </div>
                    {{-- Progress bar --}}
                    <div class="bg-gray-100 rounded-full h-2 mt-1">
                        <div class="h-2 rounded-full transition-all duration-300"
                             :class="progressPct <= 100 ? 'bg-primary' : 'bg-red-500'"
                             :style="'width: ' + Math.min(progressPct, 100) + '%'"></div>
                    </div>
                    <p class="text-xs text-gray-400 text-right" x-text="progressPct + '% der Einheit verplant'"></p>
                    @else
                    <p class="text-xs text-gray-400">Keine Endzeit für die Einheit gesetzt.</p>
                    @endif
                </div>

                {{-- Gesamt-Meter --}}
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 mb-1">Gesamtdistanz</p>
                    <p class="text-2xl font-bold text-primary" x-text="totalDistance + 'm'"></p>
                </div>
            </div>

            {{-- Materialien (Streckenzusammenf.) --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-semibold text-gray-700 mb-3">Streckenübersicht</p>
                <template x-if="Object.keys(materials).length === 0">
                    <p class="text-xs text-gray-400">Noch keine Blöcke mit Distanz.</p>
                </template>
                <div class="space-y-2">
                    <template x-for="disc in disciplineOptions" :key="disc.key">
                        <template x-if="materials[disc.key] > 0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-bold flex items-center justify-center"
                                          x-text="disc.short"></span>
                                    <span class="text-sm text-gray-600" x-text="disc.label"></span>
                                </div>
                                <span class="font-mono font-semibold text-gray-700 text-sm" x-text="materials[disc.key] + 'm'"></span>
                            </div>
                        </template>
                    </template>
                    <template x-if="materials['gemischt'] > 0">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Gemischt</span>
                            <span class="font-mono font-semibold text-gray-700 text-sm" x-text="materials['gemischt'] + 'm'"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Desktop: Speichern-Button --}}
            <div class="hidden xl:block">
                <button type="button" @click="submitForm()"
                        class="w-full bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-3 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Trainingsplan speichern
                </button>
                @if($plan)
                    <p class="text-xs text-center text-gray-400 mt-2">
                        Zuletzt gespeichert: {{ $plan->updated_at->format('d.m.Y H:i') }} Uhr
                    </p>
                @endif
            </div>
        </div>{{-- /rechte Spalte --}}
    </div>{{-- /grid --}}
</div>

@push('scripts')
<script>
function planBuilder(initialBlocks, targetSeconds) {
    return {
        description: '',
        blocks: [],
        targetSeconds: targetSeconds,
        _nextKey: 1,

        disciplineOptions: [
            { key: 'freistil',      label: 'Freistil',       short: 'F' },
            { key: 'ruecken',       label: 'Rücken',         short: 'R' },
            { key: 'brust',         label: 'Brust',          short: 'B' },
            { key: 'schmetterling', label: 'Schmetterling',  short: 'S' },
            { key: 'lagen',         label: 'Lagen',          short: 'L' },
        ],

        materialOptions: ['Pullbuoy', 'Brett', 'Pullkick', 'Widerstandshose', 'Fingerpaddles', 'Kurzflossen', 'Gummiband', 'Frontschnorchel'],
        additionOptions: ['Beine', 'Arme', 'gesamt', 'Steigerung', 'DL', 'TP', 'SP'],

        init() {
            if (initialBlocks && initialBlocks.length > 0) {
                this.blocks = initialBlocks.map(b => ({
                    ...b,
                    disciplines: b.disciplines || [],
                    additions:   b.additions   || [],
                    materials:   b.materials   || [],
                    _key: this._nextKey++,
                }));
            } else {
                this.addBlock();
            }
        },

        addBlock() {
            this.blocks.push({
                _key:                this._nextKey++,
                label:               '',
                repetitions:         '',
                distance:            '',
                disciplines:         [],
                additions:           [],
                materials:           [],
                comment:             '',
                start_interval_min:  0,
                start_interval_sec:  0,
                recovery_min:        0,
                recovery_sec:        0,
            });
        },

        removeBlock(index) {
            this.blocks.splice(index, 1);
        },

        moveBlock(index, dir) {
            const to = index + dir;
            if (to < 0 || to >= this.blocks.length) return;
            const moved = this.blocks.splice(index, 1)[0];
            this.blocks.splice(to, 0, moved);
        },

        toggleItem(arr, item) {
            const i = arr.indexOf(item);
            if (i >= 0) arr.splice(i, 1);
            else        arr.push(item);
        },

        addCustomAddition(block) {
            const input = this.$el.querySelector('input[placeholder*="Eigener Zusatz"]');
            if (!input) return;
            const val = input.value.trim();
            if (val && !block.additions.includes(val)) {
                block.additions.push(val);
                input.value = '';
            }
        },

        blockIntervalSeconds(block) {
            return (parseInt(block.start_interval_min) || 0) * 60
                 + (parseInt(block.start_interval_sec) || 0);
        },

        blockRecoverySeconds(block) {
            return (parseInt(block.recovery_min) || 0) * 60
                 + (parseInt(block.recovery_sec) || 0);
        },

        blockSeconds(block) {
            const reps     = parseInt(block.repetitions) || 0;
            const interval = this.blockIntervalSeconds(block);
            const recovery = this.blockRecoverySeconds(block);
            return reps * interval + recovery;
        },

        get totalSeconds() {
            return this.blocks.reduce((sum, b) => sum + this.blockSeconds(b), 0);
        },

        get remainingSeconds() {
            return this.targetSeconds - this.totalSeconds;
        },

        get progressPct() {
            if (!this.targetSeconds) return 0;
            return Math.round(this.totalSeconds / this.targetSeconds * 100);
        },

        get materials() {
            const mat = {};
            for (const block of this.blocks) {
                const reps = parseInt(block.repetitions) || 0;
                const dist = parseInt(block.distance)    || 0;
                if (!reps || !dist) continue;
                const discs = block.disciplines || [];
                if (discs.length === 0) continue;
                if (discs.length === 1) {
                    mat[discs[0]] = (mat[discs[0]] || 0) + reps * dist;
                } else {
                    mat['gemischt'] = (mat['gemischt'] || 0) + reps * dist;
                }
            }
            return mat;
        },

        get totalDistance() {
            return this.blocks.reduce((sum, b) => {
                return sum + (parseInt(b.repetitions) || 0) * (parseInt(b.distance) || 0);
            }, 0);
        },

        formatTime(seconds) {
            if (!seconds || isNaN(seconds)) return '0:00';
            seconds = Math.abs(Math.round(seconds));
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            if (h > 0) return `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            return `${m}:${String(s).padStart(2,'0')}`;
        },

        submitForm() {
            const payload = this.blocks.map(b => ({
                label:               b.label               || null,
                repetitions:         b.repetitions         !== '' ? b.repetitions         : null,
                distance:            b.distance            !== '' ? b.distance            : null,
                disciplines:         b.disciplines,
                additions:           b.additions,
                materials:           b.materials,
                comment:             b.comment             || null,
                start_interval_min:  b.start_interval_min  || 0,
                start_interval_sec:  b.start_interval_sec  || 0,
                recovery_min:        b.recovery_min        || 0,
                recovery_sec:        b.recovery_sec        || 0,
            }));
            this.$refs.blocksJson.value = JSON.stringify(payload);
            this.$refs.form.submit();
        },
    };
}
</script>
@endpush
@endsection

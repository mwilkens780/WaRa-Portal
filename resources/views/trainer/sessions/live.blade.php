@extends('layouts.app')
@section('title', 'Live-Zeitnahme')
@section('page-title', 'Live-Zeitnahme')

@section('content')
@if($blocks->isEmpty())
    <div class="max-w-xl mt-2 space-y-4">
        <a href="{{ route('trainer.sessions.show', $session) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Zurück zur Einheit
        </a>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-8 text-center">
            <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm font-semibold text-gray-700">Keine Serie mit Zeitnahme</p>
            <p class="text-xs text-gray-400 mt-1.5 max-w-sm mx-auto">
                Setze im Trainingsplan bei den Serien, bei denen Zeiten genommen werden sollen, den Haken „Zeitnahme".
            </p>
            <a href="{{ route('trainer.sessions.plan.builder', $session) }}"
               class="inline-block mt-4 bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                Trainingsplan bearbeiten
            </a>
        </div>
    </div>
@else
<script>
window._ltBlocks    = @json($liveBlocks);
window._ltAthletes  = @json($liveAthletes);
window._ltTimesMap  = @json($timesMap);
window._ltSessionId = {{ $session->id }};
</script>
<div x-data="liveTiming()" class="mt-2 pb-24">

    {{-- Kopf: Serie wählen --}}
    <div class="flex items-center justify-between gap-3 mb-3">
        <a href="{{ route('trainer.sessions.show', $session) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Einheit
        </a>
        <div class="flex items-center gap-2 text-xs">
            <span x-show="saveState === 'saved'" class="flex items-center gap-1 text-green-600 font-medium">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                gespeichert
            </span>
            <span x-show="saveState === 'saving'" x-cloak class="text-gray-400">speichert…</span>
            <span x-show="saveState === 'error'" x-cloak class="flex items-center gap-1 text-amber-600 font-medium">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span x-text="pending.length + ' offen'"></span>
            </span>
        </div>
    </div>

    <div class="flex gap-1.5 overflow-x-auto pb-2 mb-3 -mx-1 px-1">
        <template x-for="b in blocks" :key="b.id">
            <button type="button" @click="selectBlock(b.id)"
                    class="flex-shrink-0 px-3 py-2 rounded-lg text-xs font-semibold border transition-colors"
                    :class="b.id === activeBlockId
                        ? 'bg-primary text-white border-primary shadow-sm'
                        : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
                <span x-text="b.label || (b.display + '×' + (b.distance || '?') + 'm')"></span>
                <span class="opacity-70 ml-1" x-text="'(' + filledCount(b.id) + '/' + (b.reps * activeAthletes.length) + ')'"></span>
            </button>
        </template>
    </div>

    {{-- Modus --}}
    <div class="grid grid-cols-2 gap-1.5 mb-3 bg-gray-100 p-1 rounded-lg">
        <button type="button" @click="mode = 'watch'"
                class="py-2 rounded-md text-sm font-semibold transition-colors"
                :class="mode === 'watch' ? 'bg-white text-primary shadow-sm' : 'text-gray-500'">
            Stoppuhr
        </button>
        <button type="button" @click="mode = 'table'"
                class="py-2 rounded-md text-sm font-semibold transition-colors"
                :class="mode === 'table' ? 'bg-white text-primary shadow-sm' : 'text-gray-500'">
            Tabelle
        </button>
    </div>

    {{-- ===================== STOPPUHR ===================== --}}
    <div x-show="mode === 'watch'" class="space-y-3">

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <div class="text-center">
                <p class="font-mono font-bold tabular-nums leading-none text-gray-900"
                   style="font-size:clamp(2.6rem,15vw,4rem)"
                   x-text="display"></p>
                <p class="text-xs text-gray-400 mt-1.5" x-text="activeBlock ? (activeBlock.label || '') + ' · ' + activeBlock.display + ' × ' + (activeBlock.distance || '?') + ' m' : ''"></p>
            </div>

            <div class="grid grid-cols-3 gap-2 mt-4">
                <button type="button" @click="toggleWatch()"
                        class="col-span-2 py-4 rounded-xl text-white font-bold text-lg shadow-sm transition-colors active:scale-[.98]"
                        :class="running ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                        x-text="running ? 'Stopp' : (elapsedCs > 0 ? 'Weiter' : 'Start')"></button>
                <button type="button" @click="resetWatch()"
                        class="py-4 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm transition-colors">
                    Reset
                </button>
            </div>

            <div class="flex items-center gap-2 mt-2">
                <button type="button" @click="toggleVoice()" x-show="voiceSupported"
                        class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-semibold text-sm border transition-colors"
                        :class="listening
                            ? 'bg-red-50 border-red-300 text-red-700 animate-pulse'
                            : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-14 0m7 7v3m0-3a4 4 0 004-4V6a4 4 0 10-8 0v5a4 4 0 004 4z"/></svg>
                    <span x-text="listening ? 'Hört zu – tippen zum Beenden' : 'Sprachsteuerung'"></span>
                </button>
                <button type="button" @click="undo()" :disabled="!lastEntry"
                        class="px-4 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold disabled:opacity-40 hover:border-gray-300 transition-colors">
                    Rückgängig
                </button>
            </div>

            <p x-show="voiceSupported && listening" x-cloak class="text-xs text-center text-gray-400 mt-2">
                Sag Name und Zeit, z.&nbsp;B. „Anna 32 45" oder „Lukas eine Minute 12 30".
            </p>
            <p x-show="!voiceSupported" x-cloak class="text-xs text-center text-gray-400 mt-2">
                Dieser Browser unterstützt keine Spracherkennung – nutze die Tipp-Eingabe unten.
            </p>

            <div x-show="toast" x-cloak x-transition
                 class="mt-2 text-center text-sm font-medium rounded-lg px-3 py-2"
                 :class="toastOk ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                 x-text="toast"></div>
        </div>

        {{-- Wellen-Einstellungen (einklappbar) --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <button type="button" @click="waveOpen = !waveOpen"
                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Wellen-Zeitnahme
                    <span x-show="hasWaves" x-cloak
                          class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium"
                          x-text="waveLanes + ' Bahnen · ' + (waveGapCs / 100).toFixed(1).replace('.', ',') + ' s'"></span>
                </span>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="waveOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="waveOpen" x-cloak class="px-4 pb-4 border-t border-gray-100 space-y-4 pt-3">
                {{-- Bahnen pro Welle --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Bahnen pro Welle</p>
                    <div class="flex gap-2">
                        <template x-for="n in [1,2,3,4]" :key="n">
                            <button type="button" @click="setWaveLanes(n)"
                                    class="flex-1 py-2 rounded-lg text-sm font-semibold border transition-colors"
                                    :class="waveLanes === n
                                        ? 'bg-primary text-white border-primary'
                                        : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-gray-300'"
                                    x-text="n === 1 ? 'Aus' : n"></button>
                        </template>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">„Aus" = alle einzeln, keine Wellen-Korrektur</p>
                </div>

                {{-- Wellenabstand --}}
                <div x-show="waveLanes > 1" x-cloak>
                    <p class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Wellenabstand</p>
                    <div class="flex gap-2">
                        <template x-for="g in [{cs:300,label:'3 Sek'},{cs:500,label:'5 Sek'}]" :key="g.cs">
                            <button type="button" @click="setWaveGap(g.cs)"
                                    class="flex-1 py-2 rounded-lg text-sm font-semibold border transition-colors"
                                    :class="waveGapCs === g.cs
                                        ? 'bg-primary text-white border-primary'
                                        : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-gray-300'"
                                    x-text="g.label"></button>
                        </template>
                    </div>
                </div>

                {{-- Reihenfolge-Button --}}
                <div class="flex items-center gap-2">
                    <button type="button" @click="openOrderModal()"
                            class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:border-gray-300 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        Reihenfolge bearbeiten
                    </button>
                    <span x-show="waveSaveState === 'saving'" x-cloak class="text-xs text-gray-400">speichert…</span>
                    <span x-show="waveSaveState === 'saved'" x-cloak class="text-xs text-green-600">gespeichert</span>
                    <span x-show="waveSaveState === 'error'" x-cloak class="text-xs text-red-600">Fehler</span>
                </div>
            </div>
        </div>

        {{-- Sportler antippen --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3">
            <div class="flex items-center justify-between mb-2 px-1">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">
                    Antippen trägt <span class="text-primary" x-text="running || elapsedCs > 0 ? display : 'die Zeit'"></span> ein
                </p>
                <button type="button" @click="showAll = !showAll"
                        class="text-xs text-gray-400 hover:text-primary transition-colors"
                        x-text="showAll ? 'nur Anwesende' : 'alle anzeigen'"></button>
            </div>

            {{-- Normaler Modus (keine Wellen) --}}
            <div x-show="!hasWaves">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <template x-for="a in activeAthletes" :key="a.id">
                        <button type="button" @click="tapAthlete(a)"
                                class="text-left px-3 py-3 rounded-xl border transition-colors active:scale-[.98]"
                                :class="nextRep(a.id) === null
                                    ? 'bg-gray-50 border-gray-200 text-gray-400'
                                    : 'bg-white border-gray-200 hover:border-primary text-gray-800'">
                            <span class="block text-sm font-semibold truncate" x-text="a.short"></span>
                            <span class="block text-xs mt-0.5"
                                  :class="nextRep(a.id) === null ? 'text-green-600 font-medium' : 'text-gray-400'"
                                  x-text="nextRep(a.id) === null
                                    ? 'komplett'
                                    : (countFor(a.id) + '/' + (activeBlock ? activeBlock.reps : 0) + ' · nächste: ' + nextRep(a.id) + '.')"></span>
                        </button>
                    </template>
                </div>
                <p x-show="activeAthletes.length === 0" x-cloak class="text-sm text-gray-400 text-center py-4">
                    Keine Sportler – über „alle anzeigen" einblenden.
                </p>
            </div>

            {{-- Wellen-Modus --}}
            <div x-show="hasWaves" x-cloak class="space-y-3">
                <template x-for="g in waveGroups" :key="g.wave">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-xs font-bold text-blue-600" x-text="'Welle ' + (g.wave + 1)"></span>
                            <span x-show="g.offsetCs > 0" x-cloak
                                  class="text-xs text-gray-400"
                                  x-text="'(−' + fmt(g.offsetCs) + ' s Offset)'"></span>
                            <div class="flex-1 h-px bg-blue-100"></div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <template x-for="a in g.athletes" :key="a.id">
                                <button type="button" @click="tapAthlete(a)"
                                        class="text-left px-3 py-3 rounded-xl border transition-colors active:scale-[.98]"
                                        :class="nextRep(a.id) === null
                                            ? 'bg-gray-50 border-gray-200 text-gray-400'
                                            : 'bg-white border-gray-200 hover:border-primary text-gray-800'">
                                    <span class="block text-sm font-semibold truncate" x-text="a.short"></span>
                                    <span class="block text-xs mt-0.5"
                                          :class="nextRep(a.id) === null ? 'text-green-600 font-medium' : 'text-gray-400'"
                                          x-text="nextRep(a.id) === null
                                            ? 'komplett'
                                            : (countFor(a.id) + '/' + (activeBlock ? activeBlock.reps : 0) + ' · nächste: ' + nextRep(a.id) + '.')"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <p x-show="waveGroups.length === 0" x-cloak class="text-sm text-gray-400 text-center py-4">
                    Keine Sportler.
                </p>
            </div>
        </div>
    </div>

    {{-- ===================== TABELLE ===================== --}}
    <div x-show="mode === 'table'" x-cloak class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <p class="text-xs text-gray-400">Zelle antippen zum Ändern · Name antippen für die ganze Zeile</p>
            <button type="button" @click="showAll = !showAll"
                    class="text-xs text-gray-400 hover:text-primary transition-colors"
                    x-text="showAll ? 'nur Anwesende' : 'alle anzeigen'"></button>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="text-xs w-full min-w-max">
                    <thead>
                        <tr class="bg-blue-50 border-b border-blue-100">
                            <th class="px-3 py-2 text-left text-gray-600 font-semibold sticky left-0 bg-blue-50 min-w-[104px] z-10">Sportler</th>
                            <template x-for="i in (activeBlock ? activeBlock.reps : 0)" :key="i">
                                <th class="px-1 py-2 text-center text-gray-500 font-medium min-w-[76px]" x-text="i + '.'"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="a in activeAthletes" :key="a.id">
                            <tr>
                                <td class="px-3 py-1.5 sticky left-0 bg-white z-10 min-w-[104px]">
                                    <button type="button" @click="openRow(a)"
                                            class="font-medium text-gray-700 hover:text-primary truncate max-w-[96px] block text-left transition-colors"
                                            x-text="a.short"></button>
                                    <span x-show="hasWaves" x-cloak
                                          class="text-[10px] text-blue-400"
                                          x-text="'W' + (waveOf(a.id) + 1)"></span>
                                </td>
                                <template x-for="i in (activeBlock ? activeBlock.reps : 0)" :key="i">
                                    <td class="px-1 py-1">
                                        <input type="text" inputmode="decimal"
                                               class="w-full text-center px-1 py-2 border rounded font-mono text-xs outline-none focus:ring-2 focus:ring-blue-300 transition-colors"
                                               :class="getCs(a.id, i) !== null ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50'"
                                               placeholder="–"
                                               :value="fmt(getCs(a.id, i))"
                                               @focus="$event.target.select()"
                                               @change="setCell(a.id, i, $event.target.value); $event.target.value = fmt(getCs(a.id, i))"
                                               @keydown.enter.prevent="$event.target.blur()">
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-gray-400 px-1">Format <span class="font-mono">m:ss,zz</span> · leer = nicht mitgeschwommen</p>
    </div>

    {{-- ===================== ZEILEN-EDITOR ===================== --}}
    <div x-show="rowAthlete" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center"
         @keydown.escape.window="rowAthlete = null">
        <div class="absolute inset-0 bg-black/50" @click="rowAthlete = null"></div>
        <div class="relative bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-base font-semibold text-gray-800 truncate" x-text="rowAthlete ? rowAthlete.name : ''"></p>
                    <p class="text-xs text-gray-400" x-text="activeBlock ? (activeBlock.label || activeBlock.display + '×' + (activeBlock.distance || '?') + 'm') : ''"></p>
                </div>
                <button type="button" @click="rowAthlete = null" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="overflow-y-auto px-5 py-4 space-y-2">
                <template x-for="i in (activeBlock ? activeBlock.reps : 0)" :key="i">
                    <div class="flex items-center gap-3">
                        <span class="w-8 text-sm text-gray-400 font-medium flex-shrink-0" x-text="i + '.'"></span>
                        <input type="text" inputmode="decimal"
                               :data-rowrep="i"
                               class="flex-1 px-3 py-2.5 border border-gray-200 rounded-lg font-mono text-base text-center outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                               placeholder="–"
                               :value="fmt(getCs(rowAthlete.id, i))"
                               @focus="$event.target.select()"
                               @change="setCell(rowAthlete.id, i, $event.target.value); $event.target.value = fmt(getCs(rowAthlete.id, i))"
                               @keydown.enter.prevent="focusRowRep(i + 1)">
                        <button type="button" @click="setCell(rowAthlete.id, i, ''); $event.target.closest('div').querySelector('input').value = ''"
                                class="p-2 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">
                <button type="button" @click="rowAthlete = null"
                        class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">
                    Fertig
                </button>
            </div>
        </div>
    </div>

    {{-- ===================== REIHENFOLGE-MODAL ===================== --}}
    <div x-show="orderModalOpen" x-cloak class="fixed inset-0 z-[110] flex items-end sm:items-center justify-center"
         @keydown.escape.window="orderModalOpen = false">
        <div class="absolute inset-0 bg-black/50" @click="orderModalOpen = false"></div>
        <div class="relative bg-white w-full sm:max-w-sm rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <p class="font-semibold text-gray-800 text-base">Reihenfolge</p>
                    <p class="text-xs text-gray-400 mt-0.5"
                       x-text="waveLanes > 1 ? 'Ziehen zum Umsortieren · ' + waveLanes + ' pro Welle' : 'Ziehen zum Umsortieren'"></p>
                </div>
                <button type="button" @click="orderModalOpen = false" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="overflow-y-auto px-4 py-3">
                <div id="wave-order-list" class="space-y-1.5">
                    {{-- JS-rendered --}}
                </div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">
                <button type="button" @click="orderModalOpen = false"
                        class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">
                    Fertig
                </button>
            </div>
        </div>
    </div>

</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
function liveTiming() {
    const blocks    = window._ltBlocks    || [];
    const athletes  = window._ltAthletes  || [];
    const timesMap  = window._ltTimesMap;
    const sessionId = window._ltSessionId;

    // Per-block wave config keyed by block ID
    const initWaveCfg = blocks.reduce((m, b) => {
        m[b.id] = {
            lanesPerWave: b.lanesPerWave || 1,
            gapCs:        b.waveGapCs    || 0,
            order:        Array.isArray(b.athleteOrder) ? b.athleteOrder : [],
        };
        return m;
    }, {});

    return {
        blocks:      blocks,
        athletes:    athletes,
        times:       Array.isArray(timesMap) ? {} : (timesMap || {}),
        sessionId:   sessionId,

        activeBlockId: blocks.length ? blocks[0].id : null,
        mode:          'watch',
        showAll:       false,
        rowAthlete:    null,

        running:   false,
        startedAt: 0,
        baseCs:    0,
        elapsedCs: 0,
        display:   '0,00',
        ticker:    null,

        listening:      false,
        voiceSupported: false,
        recognition:    null,

        lastEntry:  null,
        toast:      '',
        toastOk:    true,
        toastTimer: null,

        pending:   [],
        saveState: 'idle',
        flushTimer: null,

        // Wave
        waveConfig:    initWaveCfg,
        waveOpen:      false,
        orderModalOpen: false,
        waveSaveState: 'idle',
        _sortable:     null,

        init() {
            this.voiceSupported = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
            this.render();
            window.addEventListener('beforeunload', (e) => {
                if (this.pending.length) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        // ---------- Basis-Getter ----------
        get activeBlock() {
            return this.blocks.find(b => b.id === this.activeBlockId) || null;
        },

        get activeAthletes() {
            const base = this.showAll ? this.athletes : this.athletes.filter(a => a.present);
            const order = (this.waveConfig[this.activeBlockId] || {}).order || [];
            if (!order.length) return base;
            return [...base].sort((a, b) => {
                const ia = order.indexOf(a.id);
                const ib = order.indexOf(b.id);
                return (ia === -1 ? 99999 : ia) - (ib === -1 ? 99999 : ib);
            });
        },

        selectBlock(id) {
            this.activeBlockId = id;
            this.rowAthlete = null;
            if (this.orderModalOpen) {
                this.$nextTick(() => this.renderOrderList());
            }
        },

        // ---------- Wellen-Getter ----------
        get waveLanes() {
            return (this.waveConfig[this.activeBlockId] || {}).lanesPerWave || 1;
        },
        get waveGapCs() {
            return (this.waveConfig[this.activeBlockId] || {}).gapCs || 0;
        },
        get hasWaves() {
            return this.waveLanes > 1 && this.waveGapCs > 0;
        },
        get waveGroups() {
            if (!this.hasWaves) return [];
            const athletes  = this.activeAthletes;
            const lanes     = this.waveLanes;
            const gap       = this.waveGapCs;
            const groups    = [];
            for (let i = 0; i < athletes.length; i += lanes) {
                const wi = Math.floor(i / lanes);
                groups.push({ wave: wi, offsetCs: wi * gap, athletes: athletes.slice(i, i + lanes) });
            }
            return groups;
        },

        waveOf(userId) {
            const cfg = this.waveConfig[this.activeBlockId] || {};
            const order = cfg.order || [];
            const lanes = cfg.lanesPerWave || 1;
            const idx = order.indexOf(userId);
            if (idx === -1 || lanes < 2) return 0;
            return Math.floor(idx / lanes);
        },

        waveOffsetCs(userId) {
            if (!this.hasWaves) return 0;
            return this.waveOf(userId) * this.waveGapCs;
        },

        // ---------- Wellen-Konfiguration ----------
        setWaveLanes(n) {
            const cfg = this.waveConfig[this.activeBlockId] || {};
            this.waveConfig = {
                ...this.waveConfig,
                [this.activeBlockId]: { ...cfg, lanesPerWave: n },
            };
            if (n <= 1) {
                // turn off waves: clear gap too
                this.waveConfig = {
                    ...this.waveConfig,
                    [this.activeBlockId]: { ...this.waveConfig[this.activeBlockId], gapCs: 0 },
                };
            } else if (!cfg.gapCs) {
                // default gap when enabling waves
                this.waveConfig = {
                    ...this.waveConfig,
                    [this.activeBlockId]: { ...this.waveConfig[this.activeBlockId], gapCs: 500 },
                };
            }
            this.saveWaveConfig();
        },

        setWaveGap(cs) {
            const cfg = this.waveConfig[this.activeBlockId] || {};
            this.waveConfig = {
                ...this.waveConfig,
                [this.activeBlockId]: { ...cfg, gapCs: cs },
            };
            this.saveWaveConfig();
        },

        saveWaveConfig() {
            const blockId = this.activeBlockId;
            const cfg     = this.waveConfig[blockId] || {};
            this.waveSaveState = 'saving';
            fetch('{{ route('trainer.sessions.live.wave', $session) }}', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    block_id:       blockId,
                    lanes_per_wave: cfg.lanesPerWave > 1 ? cfg.lanesPerWave : null,
                    wave_gap_cs:    cfg.gapCs || null,
                    athlete_order:  cfg.order || [],
                }),
            })
            .then(r => { if (!r.ok) throw new Error(); })
            .then(() => {
                this.waveSaveState = 'saved';
                setTimeout(() => { this.waveSaveState = 'idle'; }, 2000);
            })
            .catch(() => { this.waveSaveState = 'error'; });
        },

        // ---------- Reihenfolge-Modal ----------
        openOrderModal() {
            this.orderModalOpen = true;
            this.$nextTick(() => {
                this.renderOrderList();
                this.initSortable();
            });
        },

        renderOrderList() {
            const container = document.getElementById('wave-order-list');
            if (!container) return;
            const cfg   = this.waveConfig[this.activeBlockId] || {};
            const order = cfg.order || [];
            const lanes = cfg.lanesPerWave || 1;
            const gap   = cfg.gapCs || 0;
            const base  = this.showAll ? this.athletes : this.athletes.filter(a => a.present);
            const sorted = [...base].sort((a, b) => {
                const ia = order.indexOf(a.id);
                const ib = order.indexOf(b.id);
                return (ia === -1 ? 99999 : ia) - (ib === -1 ? 99999 : ib);
            });

            container.innerHTML = '';
            sorted.forEach((athlete, idx) => {
                const wave = Math.floor(idx / lanes);
                const item = document.createElement('div');
                item.dataset.uid = athlete.id;
                item.className = 'flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-3 py-2.5 cursor-grab active:cursor-grabbing';
                const waveLabel = lanes > 1 && gap > 0
                    ? '<span class="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-600 font-semibold flex-shrink-0 wave-badge">W' + (wave + 1) + '</span>'
                    : '';
                item.innerHTML = `
                    <svg class="w-4 h-4 text-gray-300 flex-shrink-0 drag-handle" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 6h2v2H8zm0 4h2v2H8zm0 4h2v2H8zm6-8h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2z"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700 truncate">${athlete.name}</span>
                    ${waveLabel}
                `;
                container.appendChild(item);
            });
        },

        updateWaveBadges() {
            const container = document.getElementById('wave-order-list');
            if (!container) return;
            const cfg   = this.waveConfig[this.activeBlockId] || {};
            const lanes = cfg.lanesPerWave || 1;
            const gap   = cfg.gapCs || 0;
            const items = [...container.querySelectorAll('[data-uid]')];
            items.forEach((el, idx) => {
                const badge = el.querySelector('.wave-badge');
                if (!badge) return;
                const wave = Math.floor(idx / lanes);
                badge.textContent = 'W' + (wave + 1);
            });
        },

        initSortable() {
            if (typeof Sortable === 'undefined') return;
            const container = document.getElementById('wave-order-list');
            if (!container) return;
            if (this._sortable) { this._sortable.destroy(); this._sortable = null; }
            this._sortable = Sortable.create(container, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'bg-blue-50',
                onSort: () => {
                    this.updateWaveBadges();
                },
                onEnd: () => {
                    const ids = [...container.querySelectorAll('[data-uid]')].map(el => parseInt(el.dataset.uid));
                    this.applyNewOrder(ids);
                },
            });
        },

        applyNewOrder(newIds) {
            const blockId = this.activeBlockId;
            const cfg     = this.waveConfig[blockId] || {};

            // Already-saved times stay as-is; only future taps use the new offset.
            this.waveConfig = {
                ...this.waveConfig,
                [blockId]: { ...cfg, order: newIds },
            };
            this.saveWaveConfig();
        },

        // ---------- Datenzugriff ----------
        getCs(userId, rep) {
            const b = this.times[this.activeBlockId];
            if (!b) return null;
            const u = b[userId];
            if (!u) return null;
            const v = u[rep];
            return (v === undefined || v === null) ? null : v;
        },

        putCs(blockId, userId, rep, cs) {
            if (!this.times[blockId]) this.times[blockId] = {};
            if (!this.times[blockId][userId]) this.times[blockId][userId] = {};
            if (cs === null) delete this.times[blockId][userId][rep];
            else this.times[blockId][userId][rep] = cs;
            this.times = Object.assign({}, this.times);
        },

        countFor(userId) {
            const u = (this.times[this.activeBlockId] || {})[userId] || {};
            return Object.keys(u).length;
        },

        filledCount(blockId) {
            const b = this.times[blockId] || {};
            const ids = this.activeAthletes.map(a => a.id);
            let n = 0;
            for (const uid of ids) n += Object.keys(b[uid] || {}).length;
            return n;
        },

        nextRep(userId) {
            const total = this.activeBlock ? this.activeBlock.reps : 0;
            for (let i = 1; i <= total; i++) {
                if (this.getCs(userId, i) === null) return i;
            }
            return null;
        },

        // ---------- Stoppuhr ----------
        toggleWatch() {
            if (this.running) {
                this.baseCs = this.elapsedCs;
                this.running = false;
                clearInterval(this.ticker);
            } else {
                this.startedAt = Date.now();
                this.running = true;
                this.ticker = setInterval(() => this.render(), 41);
            }
            this.render();
        },

        resetWatch() {
            this.running = false;
            clearInterval(this.ticker);
            this.baseCs = 0;
            this.elapsedCs = 0;
            this.render();
        },

        render() {
            if (this.running) {
                this.elapsedCs = this.baseCs + Math.floor((Date.now() - this.startedAt) / 10);
            } else {
                this.elapsedCs = this.baseCs;
            }
            this.display = this.fmt(this.elapsedCs) || '0,00';
        },

        tapAthlete(a) {
            const rep = this.nextRep(a.id);
            if (rep === null) { this.say(a.short + ' ist komplett', false); return; }
            if (this.elapsedCs <= 0) { this.say('Stoppuhr läuft noch nicht', false); return; }
            const offset = this.waveOffsetCs(a.id);
            const netCs  = Math.max(0, this.elapsedCs - offset);
            this.record(a, rep, netCs);
        },

        record(a, rep, cs) {
            this.putCs(this.activeBlockId, a.id, rep, cs);
            this.lastEntry = { blockId: this.activeBlockId, athlete: a, rep: rep };
            this.queue(this.activeBlockId, a.id, rep, cs);
            const offsetCs = this.waveOffsetCs(a.id);
            const note = offsetCs > 0 ? ' (−' + this.fmt(offsetCs) + ' s)' : '';
            this.say(a.short + ' · ' + rep + '. → ' + this.fmt(cs) + note, true);
        },

        undo() {
            if (!this.lastEntry) return;
            const e = this.lastEntry;
            this.putCs(e.blockId, e.athlete.id, e.rep, null);
            this.queue(e.blockId, e.athlete.id, e.rep, null);
            this.say(e.athlete.short + ' · ' + e.rep + '. gelöscht', true);
            this.lastEntry = null;
        },

        // ---------- Tabelle / Zeile ----------
        setCell(userId, rep, raw) {
            const cs = this.parseCs(raw);
            this.putCs(this.activeBlockId, userId, rep, cs);
            this.queue(this.activeBlockId, userId, rep, cs);
        },

        openRow(a) {
            this.rowAthlete = a;
            this.$nextTick(() => this.focusRowRep(1));
        },

        focusRowRep(i) {
            const el = document.querySelector('[data-rowrep="' + i + '"]');
            if (el) { el.focus(); el.select(); }
        },

        // ---------- Speichern ----------
        queue(blockId, userId, rep, cs) {
            this.pending = this.pending.filter(
                p => !(p.block_id === blockId && p.user_id === userId && p.repetition === rep)
            );
            this.pending.push({ block_id: blockId, user_id: userId, repetition: rep, time_cs: cs });
            clearTimeout(this.flushTimer);
            this.flushTimer = setTimeout(() => this.flush(), 700);
        },

        flush() {
            if (!this.pending.length || this.saveState === 'saving') return;
            const batch = this.pending.slice();
            this.saveState = 'saving';

            fetch('{{ route('trainer.sessions.live.save', $session) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ entries: batch }),
            })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(() => {
                this.pending = this.pending.filter(p => !batch.some(
                    b => b.block_id === p.block_id && b.user_id === p.user_id
                      && b.repetition === p.repetition && b.time_cs === p.time_cs
                ));
                this.saveState = this.pending.length ? 'saving' : 'saved';
                if (this.pending.length) this.flush();
            })
            .catch(() => {
                this.saveState = 'error';
                setTimeout(() => { if (this.pending.length) this.flush(); }, 4000);
            });
        },

        // ---------- Sprache ----------
        toggleVoice() {
            if (this.listening) { this.stopVoice(); return; }

            const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Rec) return;

            const r = new Rec();
            r.lang = 'de-DE';
            r.continuous = true;
            r.interimResults = false;
            r.maxAlternatives = 3;

            r.onresult = (ev) => {
                for (let i = ev.resultIndex; i < ev.results.length; i++) {
                    if (!ev.results[i].isFinal) continue;
                    let done = false;
                    for (let j = 0; j < ev.results[i].length && !done; j++) {
                        done = this.handleSpeech(ev.results[i][j].transcript);
                    }
                    if (!done) this.say('nicht verstanden', false);
                }
            };
            r.onerror = (e) => {
                if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                    this.say('Mikrofon nicht freigegeben', false);
                    this.stopVoice();
                }
            };
            r.onend = () => {
                if (this.listening) { try { r.start(); } catch (e) {} }
            };

            this.recognition = r;
            this.listening = true;
            try { r.start(); } catch (e) { this.listening = false; }
        },

        stopVoice() {
            this.listening = false;
            if (this.recognition) { try { this.recognition.stop(); } catch (e) {} }
            this.recognition = null;
        },

        handleSpeech(transcript) {
            const raw = (transcript || '').toLowerCase().trim();
            if (!raw) return false;

            const athlete = this.matchAthlete(raw);
            if (!athlete) return false;

            const cs = this.parseSpokenTime(raw);
            if (cs === null) return false;

            const rep = this.nextRep(athlete.id);
            if (rep === null) { this.say(athlete.short + ' ist komplett', false); return true; }

            this.record(athlete, rep, cs);
            return true;
        },

        norm(s) {
            return (s || '').toLowerCase()
                .replace(/ä/g, 'a').replace(/ö/g, 'o').replace(/ü/g, 'u').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9 ]/g, ' ')
                .replace(/\s+/g, ' ').trim();
        },

        normAlt(s) {
            return (s || '').toLowerCase()
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9 ]/g, ' ')
                .replace(/\s+/g, ' ').trim();
        },

        matchAthlete(text) {
            const words    = this.norm(text).split(' ').filter(w => w && !/^\d+$/.test(w));
            const wordsAlt = this.normAlt(text).split(' ').filter(w => w && !/^\d+$/.test(w));
            const all      = [...new Set(words.concat(wordsAlt))];
            if (!all.length) return null;

            let best = null, bestScore = 0;
            for (const a of this.activeAthletes) {
                const forms = [
                    { v: this.norm(a.first),    base: 100 },
                    { v: this.normAlt(a.first), base: 100 },
                    { v: this.norm(a.last),     base: 90  },
                    { v: this.normAlt(a.last),  base: 90  },
                ].filter(f => f.v);

                for (const w of all) {
                    for (const f of forms) {
                        let score = 0;
                        if (w === f.v) score = f.base;
                        else if ((f.v.startsWith(w) || w.startsWith(f.v)) && w.length >= 3) score = f.base - 30;
                        if (score > 0) score += Math.min(w.length, 12);
                        if (score > bestScore) { bestScore = score; best = a; }
                    }
                }
            }
            return bestScore >= 60 ? best : null;
        },

        parseSpokenTime(text) {
            let t = ' ' + text.toLowerCase() + ' ';
            t = t.replace(/\bkomma\b/g, ',')
                 .replace(/\bpunkt\b/g, ',')
                 .replace(/\beine\b|\beiner\b/g, '1')
                 .replace(/\bzwei\b/g, '2').replace(/\bdrei\b/g, '3').replace(/\bvier\b/g, '4')
                 .replace(/\bminuten\b|\bminute\b|\bmin\b/g, ':')
                 .replace(/\bsekunden\b|\bsekunde\b|\bsek\b/g, ' ')
                 .replace(/\bhundertstel\b|\bzehntel\b/g, ' ')
                 .replace(/\s*([:,])\s*/g, '$1');

            let m;
            if ((m = t.match(/(\d+):(\d{1,2})[,\s]\s*(\d{1,2})/))) {
                return (+m[1]) * 6000 + (+m[2]) * 100 + +((m[3] + '0').slice(0, 2));
            }
            if ((m = t.match(/(\d+):(\d{1,2})/))) {
                return (+m[1]) * 6000 + (+m[2]) * 100;
            }
            if ((m = t.match(/(\d{1,3})[,](\d{1,2})/))) {
                return (+m[1]) * 100 + +((m[2] + '0').slice(0, 2));
            }

            const groups = (t.match(/\d+/g) || []);
            if (!groups.length) return null;

            if (groups.length >= 3) {
                return (+groups[0]) * 6000 + (+groups[1]) * 100 + +((groups[2] + '0').slice(0, 2));
            }
            if (groups.length === 2) {
                return (+groups[0]) * 100 + +((groups[1] + '0').slice(0, 2));
            }

            const g = groups[0];
            if (g.length >= 5) return (+g.slice(0, -4)) * 6000 + (+g.slice(-4, -2)) * 100 + +g.slice(-2);
            if (g.length === 4) return (+g.slice(0, 2)) * 100 + +g.slice(2);
            if (g.length === 3) return (+g.slice(0, 2)) * 100 + (+g.slice(2)) * 10;
            return (+g) * 100;
        },

        // ---------- Helfer ----------
        parseCs(raw) {
            if (raw === null || raw === undefined) return null;
            let v = String(raw).trim().replace('.', ',');
            if (v === '' || v === '–' || v === '-') return null;
            let m;
            if ((m = v.match(/^(\d+):(\d{1,2})[,](\d{1,2})$/))) {
                return (+m[1]) * 6000 + (+m[2]) * 100 + +((m[3] + '0').slice(0, 2));
            }
            if ((m = v.match(/^(\d+):(\d{1,2})$/))) return (+m[1]) * 6000 + (+m[2]) * 100;
            if ((m = v.match(/^(\d{1,3})[,](\d{1,2})$/))) return (+m[1]) * 100 + +((m[2] + '0').slice(0, 2));
            if ((m = v.match(/^(\d{1,3})$/))) return (+m[1]) * 100;
            return null;
        },

        fmt(cs) {
            if (cs === null || cs === undefined) return '';
            const min  = Math.floor(cs / 6000);
            const sec  = Math.floor((cs % 6000) / 100);
            const hund = cs % 100;
            const h = String(hund).padStart(2, '0');
            if (min > 0) return min + ':' + String(sec).padStart(2, '0') + ',' + h;
            return sec + ',' + h;
        },

        say(msg, ok) {
            this.toast = msg;
            this.toastOk = ok;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toast = ''; }, 2600);
        },
    };
}
</script>
@endpush

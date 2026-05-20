@extends('layouts.app')
@section('title', 'Neuer Wettkampf')
@section('page-title', 'Neuer Wettkampf')

@section('content')
<div class="mt-2 max-w-3xl"
     x-data="{
         tab: '{{ $lenexData || session('lenex_loaded') ? 'manual' : 'manual' }}',
         events: {{ $lenexData ? json_encode($lenexData['events']) : '[]' }},
         removedEvents: [],
         toggleEvent(idx) {
             if (this.removedEvents.includes(idx)) {
                 this.removedEvents = this.removedEvents.filter(i => i !== idx);
             } else {
                 this.removedEvents.push(idx);
             }
         },
         isRemoved(idx) { return this.removedEvents.includes(idx); },
         activeEvents() { return this.events.filter((_, i) => !this.removedEvents.includes(i)); },
         eventsJson() { return JSON.stringify(this.activeEvents()); }
     }">

    {{-- Tab-Auswahl --}}
    <div class="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit mb-5">
        <button type="button" @click="tab = 'manual'"
                :class="tab === 'manual' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-2 rounded-lg text-sm font-medium transition-all">
            Manuell anlegen
        </button>
        <button type="button" @click="tab = 'lenex'"
                :class="tab === 'lenex' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-2 rounded-lg text-sm font-medium transition-all">
            Aus Lenex-Datei
            <span class="ml-1.5 text-xs bg-primary/10 text-primary px-1.5 py-0.5 rounded-full font-semibold">DSV7</span>
        </button>
    </div>

    {{-- ── Tab: Aus Lenex-Datei ──────────────────────────────────────────────── --}}
    <div x-show="tab === 'lenex'" x-cloak class="space-y-5">
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-700">
            Lade eine Lenex-Ausschreibungs- oder Meldedatei (.lef, .xml) hoch.
            Name, Ort, Datum, Veranstalter und alle Startdisziplinen werden automatisch übernommen –
            du kannst sie danach noch bearbeiten.
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <form method="POST" action="{{ route('admin.competitions.lenex') }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Lenex-Datei <span class="text-red-500">*</span>
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary transition-colors">
                        <svg class="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="text-sm text-gray-400 mb-3">.dsv7, .lef, .xml oder .txt – max. 20 MB</p>
                        <input type="file" name="lenex_file" accept=".xml,.lef,.txt,.dsv7"
                               class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
                    </div>
                    @error('lenex_file')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                    Datei einlesen →
                </button>
            </form>
        </div>
    </div>

    {{-- ── Tab: Manuell / Nach Lenex-Import ────────────────────────────────── --}}
    <div x-show="tab === 'manual'" class="space-y-5">

        @if(session('lenex_loaded') && $lenexData)
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-start gap-3">
                <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-green-800">Lenex-Datei erfolgreich eingelesen</p>
                    <p class="text-sm text-green-700 mt-0.5">
                        {{ count($lenexData['events']) }} Startdisziplinen gefunden.
                        Bitte überprüfe die vorausgefüllten Felder und passe sie bei Bedarf an.
                    </p>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <form method="POST" action="{{ route('admin.competitions.store') }}" class="space-y-5">
                @csrf

                <div class="grid md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required
                               value="{{ old('name', $lenexData['name'] ?? '') }}"
                               placeholder="z.B. Hamburger Meisterschaften 2024"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('name') ? 'border-red-400' : '' }}">
                        @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Austragungsort <span class="text-red-500">*</span></label>
                        <input type="text" name="location" required
                               value="{{ old('location', $lenexData['city'] ?? '') }}"
                               placeholder="z.B. Hamburg"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @error('location')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Veranstalter</label>
                        <input type="text" name="organizer"
                               value="{{ old('organizer', $lenexData['organizer'] ?? '') }}"
                               placeholder="z.B. Hamburger Schwimm-Verband"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Startdatum <span class="text-red-500">*</span></label>
                        <input type="date" name="date" required
                               value="{{ old('date', $lenexData['startdate'] ?? '') }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @error('date')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Enddatum</label>
                        <input type="date" name="date_end"
                               value="{{ old('date_end', ($lenexData['enddate'] ?? '') !== ($lenexData['startdate'] ?? '') ? ($lenexData['enddate'] ?? '') : '') }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Wettkampftyp <span class="text-red-500">*</span></label>
                        <select name="type" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            @foreach(\App\Models\Competition::TYPE_LABELS as $v => $l)
                                <option value="{{ $v }}" {{ old('type', 'regional') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bahnlänge</label>
                        <select name="course"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">– nicht angegeben –</option>
                            <option value="SCM" {{ old('course', $lenexData['course'] ?? '') === 'SCM' ? 'selected' : '' }}>25 m (Kurzbahn)</option>
                            <option value="LCM" {{ old('course', $lenexData['course'] ?? '') === 'LCM' ? 'selected' : '' }}>50 m (Langbahn)</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                        <textarea name="description" rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                                  placeholder="Weitere Informationen...">{{ old('description') }}</textarea>
                    </div>
                </div>

                {{-- ── Startdisziplinen (nur bei Lenex-Import) ────────────────────── --}}
                <template x-if="events.length > 0">
                    <div class="border-t border-gray-100 pt-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">
                                Startdisziplinen aus Lenex-Datei
                            </h3>
                            <span class="text-xs text-gray-400" x-text="activeEvents().length + ' von ' + events.length + ' ausgewählt'"></span>
                        </div>

                        {{-- Session-Gruppenheader --}}
                        <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <template x-for="(ev, idx) in events" :key="idx">
                                <div @click="toggleEvent(idx)"
                                     :class="isRemoved(idx) ? 'opacity-40 bg-gray-50' : 'bg-blue-50/50 hover:bg-blue-50'"
                                     class="flex items-center gap-3 px-4 py-2.5 rounded-lg border cursor-pointer transition-all select-none"
                                     :class="isRemoved(idx) ? 'border-gray-200' : 'border-blue-100'">
                                    <div :class="isRemoved(idx) ? 'border-gray-300 bg-white' : 'border-primary bg-primary'"
                                         class="w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition-colors">
                                        <svg x-show="!isRemoved(idx)" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-gray-700">
                                            <span x-text="'Nr. ' + ev.event_number + ':  ' + ev.distance + ' m ' +
                                                {'freistil':'Freistil','ruecken':'Rücken','brust':'Brust','schmetterling':'Schmetterling','lagen':'Lagen'}[ev.discipline]">
                                            </span>
                                            <span x-show="ev.age_group" x-text="' · ' + ev.age_group" class="text-gray-400"></span>
                                            <span x-show="ev.gender !== 'X'" x-text="ev.gender === 'M' ? ' · Männer' : ' · Frauen'" class="text-gray-400"></span>
                                        </span>
                                    </div>
                                    <span class="text-xs text-gray-400 flex-shrink-0"
                                          x-text="ev.session_name || ('Abschnitt ' + ev.session_number)"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Hidden JSON field --}}
                        <input type="hidden" name="events_json" :value="eventsJson()">
                    </div>
                </template>

                <div class="flex gap-3 pt-2 border-t border-gray-100">
                    <button type="submit"
                            class="bg-accent hover:bg-accent-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                        Wettkampf anlegen
                    </button>
                    <a href="{{ route('admin.competitions.index') }}"
                       class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Abbrechen
                    </a>
                    @if($lenexData)
                        <button type="button" @click="events = []; $el.closest('form').querySelector('[name=events_json]') && ($el.closest('form').querySelector('[name=events_json]').value = '[]')"
                                class="ml-auto text-xs text-gray-400 hover:text-gray-600 transition-colors">
                            Disziplinen verwerfen
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@if(session('lenex_loaded') && $lenexData)
{{-- Auto-switch to manual tab after Lenex load --}}
<script>
    document.addEventListener('alpine:init', () => {});
</script>
@endif

@endsection

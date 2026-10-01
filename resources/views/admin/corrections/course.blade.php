@extends('layouts.app')
@section('title', 'Korrektur: Bahnlängen')
@section('page-title', 'Korrektur: Bahnlängen')

@section('content')
<div class="mt-2 space-y-5"
     x-data="courseCorrection(
         @js($competitions->mapWithKeys(fn($c) => [$c->id => $suggestions[$c->id]['course'] ?? ''])),
         @js($competitions->mapWithKeys(fn($c) => [$c->id => $c->results_count > 0]))
     )">

    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zu den Einstellungen
    </a>


    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-sm text-gray-600 space-y-2">
        <p>
            Wettkämpfe ohne Bahnlänge zählen weder für Rekorde noch für Bestenlisten. WebClub liefert die Angabe
            häufig nicht mit. Hier lässt sie sich für viele Wettkämpfe auf einmal nachtragen.
        </p>
        <p>
            Wähle je Zeile Kurzbahn oder Langbahn – <strong>leer gelassene Zeilen bleiben unverändert</strong>.
            Wo die Bahn eindeutig ist, ist sie vorausgewählt und als Vorschlag gekennzeichnet; das kannst du ändern.
            Beim Ausführen werden danach Rekorde und Bestenlisten einmal komplett neu berechnet,
            genau wie bei einer Änderung im Wettkampfformular.
        </p>
    </div>

    @if($competitions->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-600">
            Alle Wettkämpfe haben eine Bahnlänge. Nichts zu korrigieren.
        </div>
    @else

    <form method="POST" action="{{ route('admin.corrections.course.update') }}"
          @submit.prevent="if (await $confirm({ title: `${selectedCount()} Wettkämpfe setzen?`, text: 'Danach werden Rekorde und Bestenlisten neu berechnet.', confirmLabel: 'Setzen' })) $el.submit()">
        @csrf @method('PUT')

        {{-- Steuerung --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3 mb-4 flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" x-model="onlyWithResults" class="rounded text-primary">
                Nur Wettkämpfe mit Ergebnissen
            </label>
            <div class="flex flex-wrap gap-2 ml-auto text-xs">
                <span class="self-center text-gray-600">Alle leeren Zeilen:</span>
                <button type="button" @click="fillEmpty('Kurzbahn')"
                        class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">auf Kurzbahn</button>
                <button type="button" @click="fillEmpty('Langbahn')"
                        class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">auf Langbahn</button>
                <button type="button" @click="reset()"
                        class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">Auswahl zurücksetzen</button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
<x-ui.table :card="false" caption="Bahnlängen korrigieren" class="table-fixed min-w-[760px]"
            :columns="[['label' => 'Datum', 'class' => 'w-28'], 'Wettkampf', ['label' => 'Ort', 'class' => 'w-44'], ['label' => 'Ergebnisse', 'align' => 'right', 'class' => 'w-24'], ['label' => 'Vorschlag', 'class' => 'w-48'], ['label' => 'Bahnlänge', 'class' => 'w-40']]">
                    @foreach($competitions as $c)
                        @php $sugg = $suggestions[$c->id] ?? null; @endphp
                        <tr class="hover:bg-gray-50"
                            x-show="!onlyWithResults || {{ $c->results_count > 0 ? 'true' : 'false' }}"
                            :class="choice[{{ $c->id }}] ? 'bg-blue-50/40' : ''">
                            <x-ui.td muted class="whitespace-nowrap">{{ $c->date?->format('d.m.Y') }}</x-ui.td>
                            <x-ui.td class="truncate" title="{{ $c->name }}">
                                <a href="{{ route('admin.competitions.show', $c) }}" target="_blank"
                                   class="text-gray-800 hover:text-primary hover:underline">{{ $c->name }}</a>
                            </x-ui.td>
                            <x-ui.td muted class="text-xs truncate" title="{{ $c->location }}">{{ $c->location ?? '–' }}</x-ui.td>
                            <x-ui.td class="px-4 py-2.5 text-right tabular-nums {{ $c->results_count ? 'text-gray-700' : 'text-gray-600' }}">{{ $c->results_count }}</x-ui.td>
                            <x-ui.td class="text-xs">
                                @if($sugg)
                                    <span class="text-blue-700 font-medium">{{ $sugg['course'] }}</span>
                                    <span class="block text-gray-600">{{ $sugg['reason'] }}</span>
                                @else
                                    <span class="text-gray-600">–</span>
                                @endif
                            </x-ui.td>
                            <x-ui.td>
                                <select name="course[{{ $c->id }}]" x-model="choice[{{ $c->id }}]" aria-label="Bahnlänge: {{ $c->name }}"
                                        class="w-full px-2 py-1.5 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                        :class="choice[{{ $c->id }}] ? 'border-blue-300 bg-white' : 'border-gray-200 text-gray-600'">
                                    <option value="">– unverändert –</option>
                                    <option value="Kurzbahn">Kurzbahn (25 m)</option>
                                    <option value="Langbahn">Langbahn (50 m)</option>
                                </select>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
</div>

        <div class="flex items-center gap-4 mt-4">
            <button type="submit" :disabled="selectedCount() === 0"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                Bahnlängen setzen und neu berechnen
            </button>
            <span class="text-sm text-gray-500">
                <strong x-text="selectedCount()"></strong> von {{ $competitions->count() }} ausgewählt
            </span>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function courseCorrection(initial, hasResults) {
        return {
            // Startwerte = Vorschlaege; leere Zeilen bleiben beim Speichern unveraendert
            initial: { ...initial },
            choice: { ...initial },
            onlyWithResults: false,
            selectedCount() {
                return Object.values(this.choice).filter(v => v).length;
            },
            // Nur leere und sichtbare Zeilen fuellen - bewusst gesetzte oder
            // vorgeschlagene bleiben, ausgefilterte werden nicht angefasst
            fillEmpty(course) {
                for (const id in this.choice) {
                    if (this.choice[id]) continue;
                    if (this.onlyWithResults && !hasResults[id]) continue;
                    this.choice[id] = course;
                }
            },
            reset() {
                this.choice = { ...this.initial };
            },
        };
    }
</script>
@endpush

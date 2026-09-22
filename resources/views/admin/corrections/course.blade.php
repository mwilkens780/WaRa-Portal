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

    @if(session('error'))
        <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif

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
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Alle Wettkämpfe haben eine Bahnlänge. Nichts zu korrigieren.
        </div>
    @else

    <form method="POST" action="{{ route('admin.corrections.course.update') }}"
          @submit="if (!confirm(`${selectedCount()} Wettkämpfe setzen und danach Rekorde und Bestenlisten neu berechnen?`)) $event.preventDefault()">
        @csrf @method('PUT')

        {{-- Steuerung --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3 mb-4 flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" x-model="onlyWithResults" class="rounded text-primary">
                Nur Wettkämpfe mit Ergebnissen
            </label>
            <div class="flex flex-wrap gap-2 ml-auto text-xs">
                <span class="self-center text-gray-400">Alle leeren Zeilen:</span>
                <button type="button" @click="fillEmpty('Kurzbahn')"
                        class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">auf Kurzbahn</button>
                <button type="button" @click="fillEmpty('Langbahn')"
                        class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">auf Langbahn</button>
                <button type="button" @click="reset()"
                        class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">Auswahl zurücksetzen</button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
            <table class="w-full text-sm table-fixed min-w-[760px]">
                <colgroup>
                    <col class="w-28"><col><col class="w-44"><col class="w-24"><col class="w-48"><col class="w-40">
                </colgroup>
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left font-medium">Datum</th>
                        <th class="px-4 py-3 text-left font-medium">Wettkampf</th>
                        <th class="px-4 py-3 text-left font-medium">Ort</th>
                        <th class="px-4 py-3 text-right font-medium">Ergebnisse</th>
                        <th class="px-4 py-3 text-left font-medium">Vorschlag</th>
                        <th class="px-4 py-3 text-left font-medium">Bahnlänge</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($competitions as $c)
                        @php $sugg = $suggestions[$c->id] ?? null; @endphp
                        <tr class="hover:bg-gray-50"
                            x-show="!onlyWithResults || {{ $c->results_count > 0 ? 'true' : 'false' }}"
                            :class="choice[{{ $c->id }}] ? 'bg-blue-50/40' : ''">
                            <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">{{ $c->date?->format('d.m.Y') }}</td>
                            <td class="px-4 py-2.5 truncate" title="{{ $c->name }}">
                                <a href="{{ route('admin.competitions.show', $c) }}" target="_blank"
                                   class="text-gray-800 hover:text-primary hover:underline">{{ $c->name }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs truncate" title="{{ $c->location }}">{{ $c->location ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums {{ $c->results_count ? 'text-gray-700' : 'text-gray-300' }}">{{ $c->results_count }}</td>
                            <td class="px-4 py-2.5 text-xs">
                                @if($sugg)
                                    <span class="text-blue-700 font-medium">{{ $sugg['course'] }}</span>
                                    <span class="block text-gray-400">{{ $sugg['reason'] }}</span>
                                @else
                                    <span class="text-gray-300">–</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <select name="course[{{ $c->id }}]" x-model="choice[{{ $c->id }}]"
                                        class="w-full px-2 py-1.5 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                        :class="choice[{{ $c->id }}] ? 'border-blue-300 bg-white' : 'border-gray-200 text-gray-400'">
                                    <option value="">– unverändert –</option>
                                    <option value="Kurzbahn">Kurzbahn (25 m)</option>
                                    <option value="Langbahn">Langbahn (50 m)</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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

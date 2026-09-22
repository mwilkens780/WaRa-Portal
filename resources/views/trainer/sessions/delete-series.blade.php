@extends('layouts.app')
@section('title', 'Serie löschen')
@section('page-title', 'Serie löschen')

@php
    $weekdays = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    $first = $sessions->first();
    $last  = $sessions->last();
    $historyTotal = array_sum($history);
@endphp

@section('content')
<div class="mt-2 max-w-3xl space-y-5"
     x-data="seriesDelete(@js($dates), '{{ $from->format('Y-m-d') }}')">

    <a href="{{ route('trainer.sessions.series.edit', $group) }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zur Serie
    </a>

    {{-- Serie --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="font-semibold text-gray-800">{{ $rep->title }}</h2>
        <p class="text-sm text-gray-500 mt-1">
            {{ $weekdays[$first->date->isoWeekday()] ?? '' }}s,
            {{ substr($first->start_time, 0, 5) }}–{{ substr($first->end_time ?? '', 0, 5) }} Uhr
            · {{ $first->date->format('d.m.Y') }} bis {{ $last->date->format('d.m.Y') }}
            · {{ $sessions->count() }} Einheiten
        </p>
        @if($rep->trainingGroups->isNotEmpty() || $rep->coTrainers->isNotEmpty())
            <p class="text-xs text-gray-400 mt-1">
                {{ $rep->trainingGroups->pluck('name')->join(', ') }}
                @if($rep->coTrainers->isNotEmpty()) · {{ $rep->coTrainers->map(fn($t) => $t->firstname . ' ' . $t->lastname)->join(', ') }} @endif
            </p>
        @endif
    </div>

    @if($errors->any())
        <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('trainer.sessions.series.destroy', $group) }}"
          @submit="if (!confirm(confirmText())) $event.preventDefault()"
          class="space-y-4">
        @csrf @method('DELETE')

        {{-- Variante 1: ab Datum --}}
        <label class="block bg-white rounded-xl shadow-sm border p-5 cursor-pointer transition-colors"
               :class="scope === 'future' ? 'border-primary ring-1 ring-primary/30' : 'border-gray-100'">
            <div class="flex items-start gap-3">
                <input type="radio" name="scope" value="future" x-model="scope" class="mt-1 text-primary">
                <div class="flex-1">
                    <p class="font-medium text-gray-800">Serie beenden – Einheiten ab einem Datum löschen</p>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Frühere Einheiten bleiben mit Anwesenheiten, Zeiten und Einschätzungen erhalten.
                        Der übliche Fall, wenn eine Serie endet oder umgestellt wird.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3" x-show="scope === 'future'">
                        <label class="text-sm text-gray-600">Ab</label>
                        <input type="date" name="from" x-model="from"
                               min="{{ $first->date->format('Y-m-d') }}" max="{{ $last->date->format('Y-m-d') }}"
                               class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                        <span class="text-sm text-gray-600">
                            → <strong x-text="futureCount()"></strong> Einheiten werden gelöscht,
                            <strong x-text="{{ $sessions->count() }} - futureCount()"></strong> bleiben.
                        </span>
                    </div>
                    <p x-show="scope === 'future' && futureWithData() > 0" x-cloak
                       class="mt-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        Achtung: <strong x-text="futureWithData()"></strong> der zu löschenden Einheiten haben bereits
                        erfasste Daten (Anwesenheit, Zeiten oder Einschätzungen). Diese gehen mit verloren.
                    </p>
                </div>
            </div>
        </label>

        {{-- Variante 2: ganze Serie --}}
        <label class="block bg-white rounded-xl shadow-sm border p-5 cursor-pointer transition-colors"
               :class="scope === 'all' ? 'border-red-400 ring-1 ring-red-200' : 'border-gray-100'">
            <div class="flex items-start gap-3">
                <input type="radio" name="scope" value="all" x-model="scope" class="mt-1 text-red-600">
                <div class="flex-1">
                    <p class="font-medium text-gray-800">Gesamte Serie löschen – einschließlich Vergangenheit</p>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Alle {{ $sessions->count() }} Einheiten verschwinden. Nur sinnvoll für versehentlich angelegte Serien.
                    </p>
                    @if($historyTotal > 0)
                        <div x-show="scope === 'all'" x-cloak class="mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            <p class="font-medium mb-1">Damit werden auch gelöscht:</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                @if($history['attendances'])<li>{{ $history['attendances'] }} Anwesenheitseinträge</li>@endif
                                @if($history['diaries'])<li>{{ $history['diaries'] }} Einschätzungen</li>@endif
                                @if($history['plans'])<li>{{ $history['plans'] }} Trainingspläne</li>@endif
                                @if($history['registrations'])<li>{{ $history['registrations'] }} Anmeldungen</li>@endif
                                @if($history['times'])<li>{{ $history['times'] }} erfasste Zeiten verlieren den Bezug zur Einheit (die Zeiten selbst bleiben)</li>@endif
                            </ul>
                        </div>
                    @else
                        <p x-show="scope === 'all'" x-cloak class="mt-2 text-sm text-gray-500">In dieser Serie sind noch keine Daten erfasst.</p>
                    @endif
                </div>
            </div>
        </label>

        <p class="text-xs text-gray-400">
            Die wöchentliche Hallenbelegung der Gruppe (z.&nbsp;B. aus dem Belegungsplan-Import) bleibt bestehen;
            nur Bahnbuchungen, die direkt an gelöschten Einheiten hängen, werden entfernt.
        </p>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors"
                    :class="scope === 'all' ? 'bg-red-600 hover:bg-red-700' : 'bg-primary hover:bg-primary-dark'"
                    x-text="scope === 'all' ? 'Gesamte Serie löschen' : 'Einheiten ab Datum löschen'"></button>
            <a href="{{ route('trainer.sessions.series.edit', $group) }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function seriesDelete(dates, from) {
        return {
            scope: 'future',
            from,
            futureCount() {
                return dates.filter(d => d.date >= this.from).length;
            },
            futureWithData() {
                return dates.filter(d => d.date >= this.from && d.hasData).length;
            },
            confirmText() {
                return this.scope === 'all'
                    ? `Wirklich die gesamte Serie mit allen ${dates.length} Einheiten und allen erfassten Daten löschen?`
                    : `${this.futureCount()} Einheiten ab ${this.from.split('-').reverse().join('.')} löschen?`;
            },
        };
    }
</script>
@endpush

@extends('layouts.app')
@section('title', 'Motto der Woche – ' . $trainingGroup->name)
@section('page-title', 'Motto der Woche')

@php
    $colors = $trainingGroup->colorDots;
    $monday = now()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
    $isAdmin = auth()->user()->isAdmin();
@endphp

@section('content')
<div class="mt-2 space-y-6">

    {{-- Kopf --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="w-4 h-4 rounded-full {{ $colors['dot'] }}"></span>
            <span class="font-semibold text-gray-800">{{ $trainingGroup->name }}</span>
            <span class="{{ $colors['badge'] }} text-xs font-medium px-2.5 py-1 rounded-full">Motto der Woche</span>
            @if($trainingGroup->mottoPartnerGroup)
                <span class="text-xs text-gray-500">gemeinsam mit <strong>{{ $trainingGroup->mottoPartnerGroup->name }}</strong></span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($season && $isAdmin)
            <form method="POST" action="{{ route('admin.training-groups.motto-generate', $trainingGroup) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-2 bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-600 transition-colors"
                        onclick="return confirm('Wochen für die aktuelle Saison generieren? Bestehende Zuweisungen bleiben erhalten.')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Wochen generieren
                </button>
            </form>
            <form method="POST" action="{{ route('admin.training-groups.motto-reset', $trainingGroup) }}">
                @csrf
                <input type="hidden" name="force" value="0">
                <button type="submit"
                        class="flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                        onclick="return confirm('Alle Wochen ohne eingetragenes Motto zurücksetzen und in der eingestellten Reihenfolge neu verteilen?\n\nWochen mit bereits eingetragenem Motto-Text bleiben erhalten.')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Neu verteilen
                </button>
            </form>
            @endif
            <a href="{{ route('admin.training-groups.show', $trainingGroup) }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                Zurück
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(!$season)
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <p class="text-amber-800">Keine aktive Saison gefunden. Bitte zuerst eine Saison anlegen, um Wochen generieren zu können.</p>
        </div>
    @endif

    @if($ledBy)
        <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-blue-900">
                Diese Gruppe läuft im Zyklus von <strong>{{ $ledBy->name }}</strong> mit – die Mitglieder kommen dort in der
                Wochenliste vor. Ein eigener Zyklus hier würde dieselben Personen ein zweites Mal einteilen.
                <a href="{{ route('admin.training-groups.motto-weeks', $ledBy) }}" class="underline font-medium">Zum Zyklus von {{ $ledBy->name }}</a>
            </p>
        </div>
    @endif

    {{-- Definition --}}
    @if($isAdmin)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Definition</h2>
            <p class="text-xs text-gray-400 mt-0.5">Wer läuft im Zyklus mit? Änderungen wirken beim nächsten Verteilen.</p>
        </div>
        <form method="POST" action="{{ route('admin.training-groups.motto-settings', $trainingGroup) }}" class="p-5 space-y-4">
            @csrf @method('PUT')

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Gemeinsam mit Gruppe</label>
                    <select name="motto_partner_group_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">– keine, nur diese Gruppe –</option>
                        @foreach($partnerOptions as $option)
                            <option value="{{ $option->id }}" {{ $trainingGroup->motto_partner_group_id == $option->id ? 'selected' : '' }}>
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Für Gruppen, die zusammen trainieren: die Mitglieder beider Gruppen teilen sich einen Zyklus.
                        Die Partnergruppe führt dann keinen eigenen.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Wer übernimmt eine Woche?</label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 py-2 cursor-pointer">
                        <input type="checkbox" name="motto_include_trainers" value="1" class="rounded text-primary"
                               {{ $trainingGroup->motto_include_trainers ? 'checked' : '' }}>
                        Trainer machen mit und übernehmen je eine Woche
                    </label>
                    <p class="text-xs text-gray-400 mt-1">
                        Ohne Haken sind nur die Sportlerinnen und Sportler an der Reihe.
                    </p>
                </div>
            </div>

            <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                Definition speichern
            </button>
        </form>
    </div>
    @endif

    {{-- Reihenfolge --}}
    @if($isAdmin && $allMembers->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
         x-data="mottoOrder(@js($allMembers->map(fn($m) => [
             'id'      => $m->id,
             'name'    => trim($m->lastname . ', ' . $m->firstname),
             'trainer' => in_array($m->role, ['trainer', 'admin'], true),
         ])->values()))">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="font-semibold text-gray-800">Reihenfolge</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    In dieser Reihenfolge werden die Wochen verteilt. <span x-text="people.length"></span> Beteiligte.
                </p>
            </div>
            <button type="button" @click="sortAlphabetically()"
                    class="text-xs text-gray-500 hover:text-primary">alphabetisch sortieren</button>
        </div>

        <form method="POST" action="{{ route('admin.training-groups.motto-order', $trainingGroup) }}" class="p-5 space-y-4">
            @csrf @method('PUT')

            <ol class="divide-y divide-gray-50 border border-gray-100 rounded-lg overflow-hidden">
                <template x-for="(person, i) in people" :key="person.id">
                    <li class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50">
                        <input type="hidden" name="order[]" :value="person.id">
                        <span class="w-6 text-xs text-gray-400 tabular-nums" x-text="(i + 1) + '.'"></span>
                        <span class="text-sm text-gray-800" x-text="person.name"></span>
                        <span x-show="person.trainer"
                              class="text-[10px] font-medium bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">Trainer</span>
                        <span class="ml-auto flex items-center gap-1">
                            <button type="button" @click="move(i, -1)" :disabled="i === 0"
                                    class="px-2 py-1 border border-gray-200 rounded text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                    title="nach oben">↑</button>
                            <button type="button" @click="move(i, 1)" :disabled="i === people.length - 1"
                                    class="px-2 py-1 border border-gray-200 rounded text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                    title="nach unten">↓</button>
                        </span>
                    </li>
                </template>
            </ol>

            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="apply" value="1" class="rounded text-primary">
                Kommende Wochen gleich neu zuordnen (Wochen mit eingetragenem Motto bleiben unverändert)
            </label>

            <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                Reihenfolge speichern
            </button>
        </form>
    </div>
    @endif

    {{-- Kennzahlen --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap gap-6 text-sm">
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Saison</p>
                <p class="font-semibold text-gray-800 mt-0.5">{{ $season ? $season->name : '–' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Wochen gesamt</p>
                <p class="font-semibold text-gray-800 mt-0.5">{{ $weeks->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Mottos eingetragen</p>
                <p class="font-semibold text-green-700 mt-0.5">{{ $weeks->whereNotNull('motto')->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Noch ausstehend</p>
                <p class="font-semibold text-amber-600 mt-0.5">{{ $weeks->filter(fn($w) => !$w->motto && $w->week_start->gte($monday))->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Beteiligte</p>
                <p class="font-semibold text-gray-800 mt-0.5">{{ $allMembers->count() }}</p>
            </div>
        </div>
    </div>

    {{-- Wochenplan --}}
    @if($weeks->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm text-gray-400 font-medium">Noch keine Wochen generiert.</p>
            @if($season)
                <p class="text-xs text-gray-400 mt-1">Klicke auf „Wochen generieren", um die Saison-Wochen anzulegen.</p>
            @endif
        </div>
    @else
    <form method="POST" action="{{ route('admin.training-groups.motto-weeks-bulk', $trainingGroup) }}">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Wochenplan</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    Personen direkt in die passende Woche setzen und Mottos anpassen. Ferienwochen sind übersprungen.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm table-fixed min-w-[820px]">
                    <colgroup>
                        <col class="w-32"><col class="w-64"><col><col class="w-28">
                    </colgroup>
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="text-left px-5 py-3">Woche</th>
                            <th class="text-left px-5 py-3">Zuständig</th>
                            <th class="text-left px-5 py-3">Motto</th>
                            <th class="text-left px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($weeks as $week)
                        @php
                            $isCurrentWeek = $week->week_start->eq($monday);
                            $isPast        = $week->week_start->lt($monday);

                            if ($isCurrentWeek)      { $statusLabel = 'Aktiv';       $statusCls = 'bg-blue-100 text-blue-700'; }
                            elseif ($week->motto)    { $statusLabel = 'Eingetragen'; $statusCls = 'bg-green-100 text-green-700'; }
                            elseif ($isPast)         { $statusLabel = 'Verpasst';    $statusCls = 'bg-red-100 text-red-600'; }
                            else                     { $statusLabel = 'Ausstehend';  $statusCls = 'bg-amber-100 text-amber-700'; }

                            // Wer zugewiesen ist, aber nicht mehr mitmacht (Gruppe gewechselt,
                            // Trainer abgewaehlt), muss trotzdem sichtbar bleiben.
                            $assignedMissing = $week->user && !$allMembers->contains('id', $week->user_id);
                        @endphp
                        <tr class="{{ $isCurrentWeek ? 'bg-blue-50/40' : 'hover:bg-gray-50' }} transition-colors">
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <p class="font-medium text-gray-800">{{ $week->week_start->format('d.m.Y') }}</p>
                                <p class="text-xs text-gray-400">KW {{ $week->week_start->weekOfYear }}</p>
                            </td>
                            <td class="px-5 py-2">
                                <select name="assign[{{ $week->id }}]"
                                        class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                                    <option value="">– niemand –</option>
                                    @foreach($allMembers as $member)
                                        <option value="{{ $member->id }}" {{ $week->user_id == $member->id ? 'selected' : '' }}>
                                            {{ $member->lastname }}, {{ $member->firstname }}
                                        </option>
                                    @endforeach
                                    @if($assignedMissing)
                                        <option value="{{ $week->user_id }}" selected>
                                            {{ $week->user->lastname }}, {{ $week->user->firstname }} (nicht mehr im Zyklus)
                                        </option>
                                    @endif
                                </select>
                            </td>
                            <td class="px-5 py-2">
                                <input type="text" name="motto[{{ $week->id }}]" maxlength="500"
                                       value="{{ $week->motto }}"
                                       placeholder="{{ $week->generated_motto ? 'KI-Vorschlag: ' . \Illuminate\Support\Str::limit($week->generated_motto, 60) : 'noch kein Motto' }}"
                                       class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                            </td>
                            <td class="px-5 py-2.5">
                                <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $statusCls }}">{{ $statusLabel }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-5 border-t border-gray-100">
                <button type="submit"
                        class="px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                    Wochenplan speichern
                </button>
            </div>
        </div>
    </form>
    @endif

</div>
@endsection

@push('scripts')
<script>
    function mottoOrder(initial) {
        return {
            people: initial,
            move(index, direction) {
                const target = index + direction;
                if (target < 0 || target >= this.people.length) return;
                const copy = [...this.people];
                [copy[index], copy[target]] = [copy[target], copy[index]];
                this.people = copy;
            },
            sortAlphabetically() {
                this.people = [...this.people].sort((a, b) => a.name.localeCompare(b.name, 'de'));
            },
        };
    }
</script>
@endpush

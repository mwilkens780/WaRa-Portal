@extends('layouts.app')
@section('title', $series->title . ' – Serie')
@section('page-title', $series->title)

@section('content')
@php
    $days = \App\Models\HallBooking::DAY_NAMES;
    $zeit = substr($series->start_time, 0, 5) . ($series->end_time ? '–' . substr($series->end_time, 0, 5) : '');
    $next = $future->first(fn($s) => !$s->isCancelled());
    $cancelledCount = $future->filter->isCancelled()->count();
    $groupsJson = $allGroups->map(fn($g) => ['id' => $g->id, 'trainers' => $g->trainers->pluck('id')])->values();
    $selectedGroups = array_map('intval', old('groups', $series->trainingGroups->pluck('id')->all()));
    $selectedTrainers = array_map('intval', old('trainers', $series->trainers->pluck('id')->all()));
@endphp

<div class="mt-2 space-y-5" x-data="{ tab: @js(in_array($tab, ['ueberblick', 'termine', 'teilnehmer', 'saison'], true) ? $tab : 'ueberblick'), cancel: { url: '', label: '' } }">

    <x-ui.page-header :back="route('trainer.sessions.index')" back-label="Trainingseinheiten"
        :subtitle="'Serie · jeden ' . ($days[$series->day_of_week] ?? '') . ' ' . $zeit . ' · ' . ($series->season?->name ?? 'ohne Saison')">
        <x-slot:actions>
            @if($next)
                <x-ui.button variant="secondary" href="{{ route('trainer.sessions.show', $next) }}">Nächster Termin: {{ $next->date->format('d.m.') }}</x-ui.button>
            @endif
        </x-slot:actions>
        {{-- Seltene und zerstoerende Aktionen im Menue - direkt von der Serie aus, ohne Umweg ueber die Liste --}}
        <x-slot:menu>
            <x-ui.menu label="Weitere Aktionen zur Serie">
                <x-ui.menu-item @click="tab = 'saison'">Nächste Saison planen</x-ui.menu-item>
                <x-ui.menu-item tone="danger" href="{{ route('trainer.sessions.series.delete', $series->id) }}">Serie beenden oder löschen …</x-ui.menu-item>
            </x-ui.menu>
        </x-slot:menu>
    </x-ui.page-header>

    {{-- Kurzüberblick --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <x-ui.card>
            <p class="text-xs text-gray-600">Termine</p>
            <p class="text-lg font-semibold text-gray-900 tabular-nums">{{ $sessions->count() }}</p>
            <p class="text-xs text-gray-600">{{ $future->count() }} kommend</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs text-gray-600">Zeitraum</p>
            <p class="text-sm font-semibold text-gray-900 tabular-nums">{{ $series->valid_from?->format('d.m.Y') }} – {{ $series->valid_until?->format('d.m.Y') ?? 'offen' }}</p>
            <p class="text-xs text-gray-600">{{ $series->skip_holidays ? 'ohne SH-Ferien' : 'auch in den Ferien' }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs text-gray-600">Teilnehmer erwartet</p>
            <p class="text-lg font-semibold text-gray-900 tabular-nums">{{ $expectedCount }}</p>
            <p class="text-xs text-gray-600">{{ $series->max_participants ? 'max. ' . $series->max_participants : 'ohne Limit' }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs text-gray-600">Bahnen</p>
            <p class="text-sm font-semibold text-gray-900">{{ $allResources->whereIn('id', $bookedResourceIds)->pluck('name')->implode(', ') ?: 'keine' }}</p>
            @if($cancelledCount)<p class="text-xs text-amber-800">{{ $cancelledCount }} Termin(e) fallen aus</p>@endif
        </x-ui.card>
    </div>

    <x-ui.tabs model="tab" :tabs="[
        'ueberblick' => 'Überblick',
        'termine'    => ['label' => 'Termine', 'count' => $sessions->count()],
        'teilnehmer' => ['label' => 'Teilnehmer', 'count' => $expectedCount],
        'saison'     => 'Saison',
    ]" />

    {{-- ── Überblick: Stammdaten ─────────────────────────────────────────── --}}
    <div x-show="tab === 'ueberblick'" role="tabpanel" id="panel-ueberblick" aria-labelledby="tab-ueberblick">
        <x-ui.card title="Seriendefinition">
            <form method="POST" action="{{ route('trainer.sessions.series.update', $series->id) }}" class="space-y-5"
                  x-data="seriesForm(@js($groupsJson), @js($selectedGroups), @js($selectedTrainers))">
                @csrf @method('PUT')

                <x-ui.field label="Gilt ab" name="valid_from" type="date" :value="today()->format('Y-m-d')" min="{{ today()->format('Y-m-d') }}" required
                            hint="Termine ab diesem Datum übernehmen die Änderung. Vergangene bleiben, wie sie waren; Termine mit eigener Abweichung behalten diese." />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Titel" name="title" :value="$series->title" required />
                    <x-ui.field label="Art" name="type" as="select">
                        @foreach($types as $val => $label)
                            <option value="{{ $val }}" @selected(old('type', $series->type) === $val)>{{ $label }}</option>
                        @endforeach
                    </x-ui.field>
                    <x-ui.field label="Wochentag" name="day_of_week" as="select" hint="Ein anderer Tag verlegt jeden Termin ab dem Datum in derselben Woche.">
                        @foreach($days as $num => $name)
                            <option value="{{ $num }}" @selected((int) old('day_of_week', $series->day_of_week) === $num)>{{ $name }}</option>
                        @endforeach
                    </x-ui.field>
                    <x-ui.field label="Ort" name="location" :value="$series->location" required />
                    <x-ui.field label="Beginn" name="start_time" type="time" :value="substr($series->start_time, 0, 5)" required />
                    <x-ui.field label="Ende" name="end_time" type="time" :value="substr($series->end_time ?? '', 0, 5)" />
                </div>

                <x-ui.field label="Notizen" name="notes" as="textarea" rows="2" :value="$series->notes" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-2">Trainingsgruppen</legend>
                        <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-56 overflow-y-auto">
                            @foreach($allGroups as $g)
                                <label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="groups[]" value="{{ $g->id }}" x-model.number="groups" @change="groupToggled({{ $g->id }}, $event.target.checked)"
                                           class="w-4 h-4 rounded text-primary border-gray-300">
                                    <span class="text-sm text-gray-700">{{ $g->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-2">Trainer</legend>
                        <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-56 overflow-y-auto">
                            @foreach($allTrainers as $t)
                                <label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="trainers[]" value="{{ $t->id }}" x-model.number="trainers"
                                           class="w-4 h-4 rounded text-primary border-gray-300">
                                    <span class="text-sm text-gray-700">{{ $t->lastname }}, {{ $t->firstname }}</span>
                                    <span x-show="groupTrainers().includes({{ $t->id }})" class="ml-auto text-xs text-gray-600">Gruppentrainer</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Max. Teilnehmer" name="max_participants" type="number" min="1" max="999" :value="$series->max_participants" placeholder="Unbegrenzt"
                                hint="Aktuell {{ $expectedCount }} erwartet (Gruppen und Einzelne, ohne dauerhafte Absagen)" />
                    <x-ui.field label="Gastgruppe" name="guest_group_id" as="select" hint="Nur mit Teilnehmerlimit: Gäste werden bei freien Plätzen benachrichtigt.">
                        <option value="">– keine –</option>
                        @foreach($allGroups as $g)
                            <option value="{{ $g->id }}" @selected((int) old('guest_group_id', $series->guest_group_id) === $g->id)>{{ $g->name }}</option>
                        @endforeach
                    </x-ui.field>
                </div>

                <div class="space-y-2">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="hidden" name="registration_open" value="0">
                        <input type="checkbox" name="registration_open" value="1" @checked(old('registration_open', $series->registration_open)) class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary">
                        <span class="text-sm text-gray-700">Anmeldung öffnen <span class="block text-xs text-gray-600">Schwimmer können sich selbst anmelden.</span></span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="hidden" name="skip_holidays" value="0">
                        <input type="checkbox" name="skip_holidays" value="1" @checked(old('skip_holidays', $series->skip_holidays)) class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary">
                        <span class="text-sm text-gray-700">Schulferien Schleswig-Holstein auslassen <span class="block text-xs text-gray-600">Gilt für neu erzeugte Termine (nächste Saison).</span></span>
                    </label>
                </div>

                <fieldset class="border-t border-gray-100 pt-4">
                    <legend class="text-sm font-semibold text-gray-700">Bahnen im Hallenplan</legend>
                    <p class="text-xs text-gray-600 mt-1 mb-3">
                        Eine wöchentliche Belegung je Bahn für die ganze Serie. Einzelne Termine können zusätzlich eigene Ausnahme-Bahnen haben – die stehen im Termin, nicht im Hallenplan.
                    </p>
                    @error('hall_resource_ids')
                        <x-ui.alert tone="error" class="mb-3">
                            <div>
                                {{ $message }}
                                <label class="mt-2 flex items-center gap-2 font-medium">
                                    <input type="checkbox" name="force_lanes" value="1" class="rounded border-gray-300">
                                    Trotzdem speichern
                                </label>
                            </div>
                        </x-ui.alert>
                    @enderror
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach($allResources as $resource)
                            <label class="flex items-center gap-2.5 cursor-pointer px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50">
                                <input type="checkbox" name="hall_resource_ids[]" value="{{ $resource->id }}"
                                       @checked(in_array($resource->id, array_map('intval', old('hall_resource_ids', $bookedResourceIds))))
                                       class="w-4 h-4 rounded border-gray-300 text-primary">
                                @if($resource->color)<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $resource->color }}" aria-hidden="true"></span>@endif
                                <span class="flex-1 text-sm text-gray-700 truncate">{{ $resource->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <x-ui.button type="submit">Serie speichern</x-ui.button>
                    <span class="text-xs text-gray-600">{{ $future->count() }} kommende Termine</span>
                </div>
            </form>
        </x-ui.card>
    </div>

    {{-- ── Termine ────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'termine'" x-cloak role="tabpanel" id="panel-termine" aria-labelledby="tab-termine">
        <x-ui.table title="Alle Termine" :meta="$future->count() . ' kommend, ' . ($sessions->count() - $future->count()) . ' vergangen'"
                    caption="Termine der Serie {{ $series->title }}" stack
                    :columns="['Datum', 'Zeit', 'Status', ['label' => 'Teilnahme', 'align' => 'right'], ['label' => 'Aktionen', 'sr' => true]]">
                        @foreach($sessions as $s)
                            @php
                                $past = $s->date->lt(today());
                                $labels = ['title' => 'Titel', 'type' => 'Art', 'start_time' => 'Beginn', 'end_time' => 'Ende', 'location' => 'Ort', 'notes' => 'Notiz',
                                           'max_participants' => 'Limit', 'registration_open' => 'Anmeldung', 'guest_group_id' => 'Gastgruppe', 'date' => 'Tag', 'groups' => 'Gruppen', 'trainers' => 'Trainer'];
                                $abw = collect($s->overridden_fields ?? [])->map(fn($f) => $labels[$f] ?? $f);
                            @endphp
                            <tr>
                                <x-ui.td label="Datum" :strong="!$past" :muted="$past" class="whitespace-nowrap tabular-nums">{{ $s->date->isoFormat('dd, DD.MM.YYYY') }}</x-ui.td>
                                <x-ui.td label="Zeit" :muted="$past" class="whitespace-nowrap tabular-nums">{{ substr($s->start_time, 0, 5) }}@if($s->end_time)–{{ substr($s->end_time, 0, 5) }}@endif</x-ui.td>
                                <x-ui.td label="Status">
                                    <div class="flex flex-wrap gap-1">
                                        @if($s->isCancelled())
                                            <x-ui.badge tone="warning">fällt aus</x-ui.badge>
                                        @elseif($past)
                                            <x-ui.badge>vergangen</x-ui.badge>
                                        @endif
                                        @if($abw->isNotEmpty())
                                            <x-ui.badge tone="info" title="Weicht von der Serie ab">eigene: {{ $abw->implode(', ') }}</x-ui.badge>
                                        @endif
                                        @if($s->exception_lanes_count)
                                            <x-ui.badge tone="brand">+{{ $s->exception_lanes_count }} Ausnahme-Bahn</x-ui.badge>
                                        @endif
                                    </div>
                                    @if($s->cancel_reason)<p class="text-xs text-gray-600 mt-0.5">{{ $s->cancel_reason }}</p>@endif
                                </x-ui.td>
                                <x-ui.td label="Teilnahme" num :muted="$past">
                                    @if($past)
                                        {{ isset($attended[$s->id]) ? $attended[$s->id] . ' anwesend' : '–' }}
                                    @else
                                        {{ max(0, $expectedCount - ($preAbsent[$s->id] ?? 0)) }} erwartet
                                        @if($preAbsent[$s->id] ?? 0)<span class="text-xs text-gray-600">({{ $preAbsent[$s->id] }} Absagen)</span>@endif
                                    @endif
                                </x-ui.td>
                                <x-ui.td align="right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.button size="sm" variant="ghost" href="{{ route('trainer.sessions.show', $s) }}">Öffnen</x-ui.button>
                                        @if(!$past && !$s->isCancelled())
                                            <x-ui.button size="sm" variant="secondary"
                                                @click="cancel = { url: {{ \Illuminate\Support\Js::from(route('trainer.sessions.cancel', $s)) }}, label: {{ \Illuminate\Support\Js::from($s->date->isoFormat('dddd, D. MMMM')) }} }; $dispatch('open-dialog', 'cancel-session')">Fällt aus</x-ui.button>
                                        @elseif($s->isCancelled() && !$past)
                                            <form method="POST" action="{{ route('trainer.sessions.reactivate', $s) }}">
                                                @csrf @method('DELETE')
                                                <x-ui.button size="sm" variant="secondary" type="submit">Findet statt</x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                </x-ui.td>
                            </tr>
                        @endforeach
        </x-ui.table>
    </div>

    <x-ui.dialog name="cancel-session" title="Termin fällt aus">
        <form id="cancel-form" method="POST" :action="cancel.url" class="space-y-4">
            @csrf
            <p class="text-sm text-gray-700">Der Termin am <strong x-text="cancel.label"></strong> bleibt stehen und wird als „fällt aus“ markiert – Schwimmer und Eltern sehen das im Portal.</p>
            <x-ui.field label="Grund (optional)" name="cancel_reason" placeholder="z. B. Hallenschließung" />
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="notify" value="1" checked class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary">
                <span class="text-sm text-gray-700">Per E-Mail benachrichtigen <span class="block text-xs text-gray-600">An Schwimmer und Eltern – außer wer „Trainingsausfall“ im Profil abgewählt hat.</span></span>
            </label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" @click="close()">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="cancel-form">Fällt aus</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    {{-- ── Teilnehmer ─────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'teilnehmer'" x-cloak role="tabpanel" id="panel-teilnehmer" aria-labelledby="tab-teilnehmer" class="space-y-5">
        <x-ui.card title="Gruppen" :meta="$expectedCount . ' erwartet'">
            @forelse($series->trainingGroups as $g)
                <div class="flex items-center justify-between py-1.5 text-sm">
                    <a href="{{ route('admin.training-groups.show', $g) }}" class="text-primary underline underline-offset-2 hover:no-underline">{{ $g->name }}</a>
                    <span class="text-gray-600 tabular-nums">{{ $g->swimmers->count() }} Schwimmer</span>
                </div>
            @empty
                <p class="text-sm text-gray-600">Keine Gruppe – Gruppen im Überblick wählen.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card title="Einzeln zur ganzen Serie" :meta="$individual->count() . ' Schwimmer'">
            <form method="POST" action="{{ route('trainer.sessions.series.swimmer.add', $series->id) }}" class="flex flex-wrap items-end gap-2 mb-4">
                @csrf
                <x-ui.field label="Schwimmer hinzufügen" name="user_id" as="select" required class="min-w-[14rem]">
                    <option value="">Wählen…</option>
                    @foreach($allSwimmers as $sw)
                        <option value="{{ $sw->id }}">{{ $sw->lastname }}, {{ $sw->firstname }}</option>
                    @endforeach
                </x-ui.field>
                <x-ui.button type="submit" variant="secondary">Hinzufügen</x-ui.button>
            </form>
            @forelse($individual as $assign)
                <div class="flex items-center justify-between py-1.5 text-sm">
                    <span class="text-gray-800">{{ $assign->user?->lastname }}, {{ $assign->user?->firstname }}</span>
                    <form method="POST" action="{{ route('trainer.sessions.series.swimmer.remove', [$series->id, $assign->user_id]) }}">
                        @csrf @method('DELETE')
                        <x-ui.button size="sm" variant="ghost" type="submit" aria-label="{{ $assign->user?->firstname }} aus der Serie entfernen">Entfernen</x-ui.button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-600">Niemand einzeln zugewiesen.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card title="Dauerhafte Absagen" :meta="$exclusions->count()">
            @forelse($exclusions as $ex)
                <div class="flex items-center justify-between py-1.5 text-sm">
                    <span class="text-gray-800">{{ $ex->user?->lastname }}, {{ $ex->user?->firstname }}</span>
                    @if($ex->comment)<span class="text-xs text-gray-600 truncate max-w-xs">„{{ $ex->comment }}“</span>@endif
                </div>
            @empty
                <p class="text-sm text-gray-600">Niemand hat die Serie dauerhaft abgesagt.</p>
            @endforelse
        </x-ui.card>
    </div>

    {{-- ── Saison ─────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'saison'" x-cloak role="tabpanel" id="panel-saison" aria-labelledby="tab-saison" class="space-y-5">
        <x-ui.card title="Nächste Saison planen">
            <p class="text-sm text-gray-700 mb-4">
                Legt eine <strong>neue Serie</strong> an – vorbefüllt mit Zeit, Ort, Gruppen und Trainern dieser Serie. Danach dort anpassen,
                was sich ändert. Die Bahnen im Hallenplan gehen auf die neue Serie über; diese Serie bleibt mit ihren Terminen als Geschichte erhalten.
            </p>
            <form method="POST" action="{{ route('trainer.sessions.series.store-season', $series->id) }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field label="Erster Termin ab" name="start_date" type="date" :value="$suggestedStart->format('Y-m-d')" required
                                hint="Wird auf den nächsten {{ $days[$series->day_of_week] ?? '' }} gelegt." />
                    <x-ui.field label="Bis" name="end_date" type="date" :value="$suggestedEnd->format('Y-m-d')" required />
                    <x-ui.field label="Rhythmus" name="recurrence_type" as="select">
                        <option value="weekly" @selected($series->recurrence_type === 'weekly')>wöchentlich</option>
                        <option value="biweekly" @selected($series->recurrence_type === 'biweekly')>alle 2 Wochen</option>
                        <option value="monthly" @selected($series->recurrence_type === 'monthly')>monatlich</option>
                    </x-ui.field>
                </div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="skip_holidays" value="1" @checked($series->skip_holidays) class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary">
                    <span class="text-sm text-gray-700">Schulferien Schleswig-Holstein auslassen</span>
                </label>
                @if($nextSeason)<p class="text-xs text-gray-600">Vorschlag aus Saison {{ $nextSeason->name }}.</p>@endif
                <x-ui.button type="submit">Neue Serie anlegen</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Serie beenden oder löschen">
            <p class="text-sm text-gray-700 mb-4">
                <strong>Beenden ab Datum</strong> löscht die Termine ab dem Datum, frühere bleiben mit Anwesenheiten erhalten.
                <strong>Komplett löschen</strong> entfernt die Serie mit allen Terminen – auch vergangenen, mit Anwesenheiten und Plänen.
                Auf der nächsten Seite siehst du vorher, was betroffen ist.
            </p>
            <x-ui.button variant="danger" href="{{ route('trainer.sessions.series.delete', $series->id) }}">Beenden oder löschen …</x-ui.button>
        </x-ui.card>
    </div>
</div>

<script>
function seriesForm(allGroups, groups, trainers) {
    return {
        groups, trainers,
        groupTrainers() {
            return allGroups.filter(g => this.groups.includes(g.id)).flatMap(g => g.trainers);
        },
        // Gruppe dazu: ihre Trainer vorschlagen (anhaken)
        groupToggled(id, checked) {
            if (!checked) return;
            (allGroups.find(g => g.id === id)?.trainers ?? []).forEach(t => { if (!this.trainers.includes(t)) this.trainers.push(t); });
        },
    };
}
</script>
@endsection

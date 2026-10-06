{{--
    Felder eines Termins (Anlegen und Bearbeiten).
    Erwartet: $types (erlaubte Arten), $seasons, optional $calendarEvent
    Alpine im umgebenden Formular: type, audience (aus den Arten abgeleitet)
--}}
@php $e = $calendarEvent ?? null; @endphp

<x-ui.field label="Titel" name="title" :value="$e?->title" required maxlength="200" />

<x-ui.field label="Art" name="type" as="select" x-model="type" required :disabled="$e && $e->hasInvitations()">
    @foreach($types as $key => $info)
        <option value="{{ $key }}" @selected(old('type', $e?->type ?? $defaultType ?? null) === $key)>{{ $info['label'] }}</option>
    @endforeach
</x-ui.field>
@if($e && $e->hasInvitations())
    <input type="hidden" name="type" value="{{ $e->type }}">
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.field label="Datum von" name="start_date" type="date" :value="$e?->start_date?->format('Y-m-d') ?? ($defaultDate ?? null)" required />
    <x-ui.field label="Datum bis" name="end_date" type="date" :value="$e?->end_date?->format('Y-m-d')" />
    <x-ui.field label="Uhrzeit von" name="start_time" type="time" step="900" :value="$e?->start_time ? substr($e->start_time, 0, 5) : null" />
    <x-ui.field label="Uhrzeit bis" name="end_time" type="time" step="900" :value="$e?->end_time ? substr($e->end_time, 0, 5) : null" />
</div>

<x-ui.field label="Ort" name="location" :value="$e?->location" maxlength="200" placeholder="z. B. Vereinsheim oder Online-Link" />

<x-ui.field label="Saison" name="season_id" as="select">
    <option value="">– keine Saison –</option>
    @foreach($seasons as $s)
        <option value="{{ $s->id }}" @selected(old('season_id', $e?->season_id) == $s->id)>Saison {{ $s->name }}</option>
    @endforeach
</x-ui.field>

<x-ui.field label="Beschreibung" name="description" as="textarea" rows="3" :value="$e?->description" />

{{-- Nur Termine mit Einladung: Agenda und Anmeldung --}}
<div class="space-y-4" x-show="audience" x-cloak>
    <x-ui.rich-text-editor name="agenda" :value="old('agenda', $e?->agenda)" label="Agenda"
                           placeholder="Tagesordnungspunkte – werden mit der Einladung verschickt" />

    <div class="rounded-lg border border-gray-200 p-4 space-y-4" x-data="{ rsvp: {{ old('rsvp_enabled', $e?->rsvp_enabled ?? true) ? 'true' : 'false' }} }">
        <label class="flex items-start gap-3 text-sm text-gray-800">
            <input type="hidden" name="rsvp_enabled" value="0">
            <input type="checkbox" name="rsvp_enabled" value="1" x-model="rsvp"
                   class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary/30">
            <span><span class="font-medium">Anmeldung</span> – Eingeladene sagen zu, ab oder „vielleicht“</span>
        </label>
        <div class="grid gap-4 sm:grid-cols-2" x-show="rsvp" x-cloak>
            <x-ui.field label="Anmeldeschluss" name="rsvp_deadline" type="date" :value="$e?->rsvp_deadline?->format('Y-m-d')"
                        hint="Zwei Tage vorher geht eine Erinnerung an alle ohne Rückmeldung." />
            <x-ui.field label="Plätze" name="capacity" type="number" min="1" :value="$e?->capacity" hint="Leer lassen, wenn es keine Begrenzung gibt." />
        </div>
    </div>
</div>

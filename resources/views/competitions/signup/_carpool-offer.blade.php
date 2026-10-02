{{--
    Fahrgemeinschaft anbieten (nur Eltern) - Teil des Rueckmeldeformulars.
    Erwartet: $response, $idSuffix
--}}
@php $mitfahrer = $response?->carpoolPassengers()->with('user:id,firstname,lastname')->get() ?? collect(); @endphp
<fieldset class="border-t border-gray-100 pt-3 space-y-3">
    <legend class="text-xs font-semibold text-gray-600">Fahrgemeinschaft anbieten</legend>
    <div class="flex flex-wrap items-end gap-3">
        <x-ui.field label="Freie Plätze für andere" name="carpool_seats" type="number" min="{{ $mitfahrer->count() }}" max="20" placeholder="0"
                    :id="'carpool_' . $idSuffix" :value="$response?->carpool_seats" hint="Ohne Fahrer und eigenes Kind. 0 = kein Angebot." />
        <div class="flex-1 min-w-[12rem]">
            <x-ui.field label="Hinweis für Mitfahrer (optional)" name="carpool_note" :id="'carpool_note_' . $idSuffix" maxlength="255"
                        :value="$response?->carpool_note" placeholder="z. B. Abfahrt 7:30 am Stadtbad" />
        </div>
    </div>
    @if($mitfahrer->isNotEmpty())
        <p class="text-xs text-gray-700">
            Es fahren mit: <strong>{{ $mitfahrer->map(fn($m) => $m->user?->name)->filter()->implode(', ') }}</strong>
        </p>
    @endif
</fieldset>

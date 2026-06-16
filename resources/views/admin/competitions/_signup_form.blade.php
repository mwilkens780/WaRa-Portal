{{-- Wiederverwendetes Formular für Signup-Abfrage (create + edit) --}}
{{-- Variablen: $signupRequest (null beim Anlegen), $allGroups, $swimmers --}}

<div>
    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Berechtigte Trainingsgruppen</label>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
        @foreach($allGroups as $group)
            @php
                $checked = $signupRequest
                    ? in_array($group->id, $signupRequest->eligible_group_ids ?? [])
                    : false;
                $dots = $group->color_dots;
            @endphp
            <label class="flex items-center gap-2 p-2.5 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors
                {{ $checked ? 'border-primary bg-blue-50/40' : 'border-gray-200' }}">
                <input type="checkbox" name="eligible_group_ids[]" value="{{ $group->id }}"
                       {{ $checked ? 'checked' : '' }}
                       class="rounded border-gray-300 text-primary focus:ring-primary">
                <span class="w-2 h-2 rounded-full {{ $dots['dot'] }} shrink-0"></span>
                <span class="text-sm text-gray-700">{{ $group->name }}</span>
            </label>
        @endforeach
    </div>
    <p class="text-xs text-gray-400 mt-1">Alle aktiven Schwimmer dieser Gruppen werden zur Anmeldung eingeladen.</p>
</div>

<div>
    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Zusätzliche Einzelschwimmer</label>
    <select name="eligible_user_ids[]" multiple
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
            size="5">
        @foreach($swimmers as $s)
            <option value="{{ $s->id }}"
                {{ $signupRequest && in_array($s->id, $signupRequest->eligible_user_ids ?? []) ? 'selected' : '' }}>
                {{ $s->name }}
            </option>
        @endforeach
    </select>
    <p class="text-xs text-gray-400 mt-1">Mehrfachauswahl mit Strg/Cmd + Klick.</p>
</div>

<div>
    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nachricht an die Schwimmer</label>
    <textarea name="message" rows="5"
              placeholder="Informationen zur Anmeldung, Anforderungen, Besonderheiten..."
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-y">{{ $signupRequest?->message }}</textarea>
</div>

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Anmeldefrist</label>
        <input type="date" name="deadline"
               value="{{ $signupRequest?->deadline?->format('Y-m-d') }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Anhang (optional)</label>
        <input type="file" name="attachment"
               class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
        @if($signupRequest?->attachment_path)
            <p class="text-xs text-gray-400 mt-1">Aktuell: Anhang vorhanden (wird durch Upload ersetzt)</p>
        @endif
    </div>
</div>

{{-- Treffpunkt & Bus --}}
<div class="border-t border-gray-100 pt-4">
    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Treffpunkt & Bus</p>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Treffpunkt</label>
            <input type="text" name="meeting_point"
                   value="{{ $signupRequest?->meeting_point }}"
                   placeholder="z.B. Parkplatz Schwimmhalle Nord"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Treffpunkt-Uhrzeit</label>
            <input type="time" name="meeting_time"
                   value="{{ $signupRequest?->meeting_time ? \Illuminate\Support\Str::substr($signupRequest->meeting_time, 0, 5) : '' }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
    </div>

    <div x-data="{ busOn: {{ ($signupRequest?->bus_available ?? false) ? 'true' : 'false' }} }" class="mt-4 space-y-3">
        <label class="flex items-center gap-3 cursor-pointer select-none">
            <input type="hidden" name="bus_available" value="0">
            <input type="checkbox" name="bus_available" value="1"
                   x-model="busOn"
                   {{ ($signupRequest?->bus_available ?? false) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-primary focus:ring-primary">
            <span class="text-sm font-medium text-gray-700">Vereinsbus anbieten</span>
        </label>

        <div x-show="busOn" x-cloak>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Maximale Plätze im Bus</label>
            <input type="number" name="bus_seats"
                   value="{{ $signupRequest?->bus_seats ?? 8 }}"
                   min="1" max="100"
                   class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
    </div>
</div>

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

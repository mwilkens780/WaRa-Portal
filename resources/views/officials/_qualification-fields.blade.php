{{-- Felder einer Qualifikation. Erwartet: $q (oder null), $listId (datalist), $prefix (eindeutige IDs) --}}
<div class="grid gap-3 sm:grid-cols-2">
    <x-ui.field label="Qualifikation" name="title" :id="$prefix . '-title'" :value="$q?->title" required maxlength="120"
                list="{{ $listId }}" placeholder="z. B. Schiedsrichter*in" />
    <x-ui.field label="Lizenznummer" name="license_nr" :id="$prefix . '-nr'" :value="$q?->license_nr" maxlength="50" />
    <x-ui.field label="Erworben am" name="acquired_on" type="date" :id="$prefix . '-acq'" :value="$q?->acquired_on?->format('Y-m-d')" />
    <x-ui.field label="Gültig bis" name="valid_until" type="date" :id="$prefix . '-until'" :value="$q?->valid_until?->format('Y-m-d')" />
</div>
<label class="flex items-start gap-3 text-sm text-gray-800">
    <input type="checkbox" name="is_primary" value="1" @checked($q?->is_primary)
           class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary/30">
    <span>Hauptlizenz – wird im Mitgliederstamm geführt (Lizenzfelder des Benutzers, WebClub)</span>
</label>

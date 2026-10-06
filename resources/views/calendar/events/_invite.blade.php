{{--
    Auswahl der Eingeladenen, passend zur Art des Termins.
    Im Formular "Anlegen" wechselt die Auswahl mit der Art (Alpine-Variable audience),
    auf der Terminseite ("Weitere einladen") ist die Art fest: $fixedAudience.

    Erwartet: $groups, $swimmers, $portalUsers, $boardCount, optional $fixedAudience
--}}
@php $fixed = $fixedAudience ?? null; @endphp

<div class="space-y-5">
    {{-- Vorstandssitzung --}}
    <div @if($fixed) @if($fixed !== 'vorstand') hidden @endif @else x-show="audience === 'vorstand'" x-cloak @endif>
        <label class="flex items-start gap-3 text-sm text-gray-800">
            <input type="checkbox" name="all_board" value="1" @checked(old('all_board', !$fixed))
                   class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary/30">
            <span>Alle Vorstandsmitglieder einladen <span class="text-gray-600">({{ $boardCount }})</span></span>
        </label>
    </div>

    {{-- Elternabend und Team-Event: Gruppen --}}
    <fieldset @if($fixed) @if(!in_array($fixed, ['eltern', 'team'], true)) hidden @endif @else x-show="audience === 'eltern' || audience === 'team'" x-cloak @endif
              class="space-y-2">
        <legend class="block text-sm font-medium text-gray-800">Gruppen</legend>
        <p class="text-xs text-gray-600" @if(!$fixed) x-show="audience === 'eltern'" @elseif($fixed !== 'eltern') hidden @endif>
            Eingeladen werden die Eltern minderjähriger Mitglieder und die volljährigen Mitglieder selbst.
        </p>
        <p class="text-xs text-gray-600" @if(!$fixed) x-show="audience === 'team'" @elseif($fixed !== 'team') hidden @endif>
            Eingeladen werden die Mitglieder; bei Minderjährigen bekommen die Eltern die Einladung mit und können zusagen.
        </p>
        <div class="flex flex-wrap gap-2">
            @forelse($groups as $g)
                <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-800 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="group_ids[]" value="{{ $g->id }}" @checked(in_array($g->id, old('group_ids', [])))
                           class="rounded border-gray-300 text-primary focus:ring-primary/30">
                    {{ $g->name }}
                </label>
            @empty
                <p class="text-sm text-gray-600">Keine Gruppen verfügbar.</p>
            @endforelse
        </div>
    </fieldset>

    {{-- Team-Event: einzelne Schwimmer --}}
    <div @if($fixed) @if($fixed !== 'team') hidden @endif @else x-show="audience === 'team'" x-cloak @endif>
        @include('calendar.events._user-picker', ['name' => 'user_ids', 'label' => 'Einzelne Schwimmerinnen und Schwimmer', 'people' => $swimmers])
    </div>

    {{-- Gäste --}}
    <div class="grid gap-5 lg:grid-cols-2">
        @include('calendar.events._user-picker', ['name' => 'guest_user_ids', 'label' => 'Gäste aus dem Portal', 'people' => $portalUsers])
        <x-ui.field label="Gäste per E-Mail" name="guest_emails" as="textarea" rows="4"
                    hint="Eine Person je Zeile, z. B. „Erika Muster <erika@example.de>“. Gäste bekommen einen persönlichen Link zum Zu- oder Absagen."
                    placeholder="Name <adresse@beispiel.de>" />
    </div>
</div>

{{--
    Qualifikationen einer Person: Liste mit Bearbeiten/Entfernen und Formular zum Hinzufügen.
    Erwartet: $user (mit officialQualifications)
--}}
@php
    use App\Models\OfficialQualification;
    use App\View\Components\OfficialsPanel;
    $warn = OfficialsPanel::LICENSE_WARN_MONTHS;
    $listId = 'qual-titles-' . $user->id;
@endphp

<datalist id="{{ $listId }}">
    @foreach(OfficialQualification::SUGGESTIONS as $s)<option value="{{ $s }}">@endforeach
</datalist>

<div class="space-y-3">
    @forelse($user->officialQualifications as $q)
        <details class="rounded-lg border border-gray-200 bg-white">
            <summary class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 cursor-pointer">
                <span class="min-w-0">
                    <span class="font-medium text-gray-900">{{ $q->title }}</span>
                    @if($q->is_primary)<x-ui.badge tone="brand" class="ml-1">Hauptlizenz</x-ui.badge>@endif
                    <span class="block text-sm text-gray-600">
                        {{ $q->license_nr ? 'Lizenz ' . $q->license_nr : 'ohne Lizenznummer' }}
                        @if($q->acquired_on) · erworben {{ $q->acquired_on->format('d.m.Y') }} @endif
                    </span>
                </span>
                @if($q->valid_until)
                    <x-ui.badge :tone="$q->valid_until->isPast() ? 'danger' : ($q->expiresWithin($warn) ? 'warning' : 'neutral')">
                        {{ $q->valid_until->isPast() ? 'abgelaufen' : 'gültig bis' }} {{ $q->valid_until->format('d.m.Y') }}
                    </x-ui.badge>
                @else
                    <x-ui.badge>ohne Ablaufdatum</x-ui.badge>
                @endif
            </summary>
            <div class="border-t border-gray-100 p-4 space-y-3">
                <form method="POST" action="{{ route('officials.qualifications.update', $q) }}" class="space-y-3">
                    @csrf @method('PUT')
                    @include('officials._qualification-fields', ['q' => $q, 'listId' => $listId, 'prefix' => 'q' . $q->id])
                    <x-ui.button type="submit" size="sm">Speichern</x-ui.button>
                </form>
                <form method="POST" action="{{ route('officials.qualifications.destroy', $q) }}"
                      data-confirm="Qualifikation „{{ $q->title }}“ entfernen?" data-confirm-label="Entfernen" data-confirm-danger>
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" size="sm" variant="secondary">Entfernen</x-ui.button>
                </form>
            </div>
        </details>
    @empty
        <p class="text-sm text-gray-600">Noch keine Qualifikationen eingetragen.</p>
    @endforelse

    <details class="rounded-lg border border-dashed border-gray-300 bg-white" @if($user->officialQualifications->isEmpty()) open @endif>
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold text-primary">Qualifikation hinzufügen</summary>
        <form method="POST" action="{{ route('officials.qualifications.store', $user) }}" class="border-t border-gray-100 p-4 space-y-3">
            @csrf
            @include('officials._qualification-fields', ['q' => null, 'listId' => $listId, 'prefix' => 'new' . $user->id])
            <x-ui.button type="submit" size="sm">Hinzufügen</x-ui.button>
        </form>
    </details>
</div>

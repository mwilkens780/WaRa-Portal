{{--
    Hinweis im Seitenfluss (bleibt stehen - fuer Kurzes, das verschwinden darf: $toast).

    <x-ui.alert tone="error">Speichern fehlgeschlagen.</x-ui.alert>
    <x-ui.alert tone="warning" dismissible>…</x-ui.alert>

    tone: info | success | warning | error. Fehler/Warnungen werden als role=alert angekuendigt.
--}}
@props(['tone' => 'info', 'dismissible' => false])

@php
    $styles = [
        'info'    => ['bg-blue-50 border-blue-200 text-blue-900', 'info', 'status'],
        'success' => ['bg-green-50 border-green-200 text-green-900', 'check-circle', 'status'],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-900', 'alert', 'alert'],
        'error'   => ['bg-red-50 border-red-200 text-red-900', 'alert', 'alert'],
    ];
    [$cls, $icon, $role] = $styles[$tone] ?? $styles['info'];
@endphp

<div @if($dismissible) x-data="{ show: true }" x-show="show" @endif role="{{ $role }}"
     {{ $attributes->merge(['class' => "flex items-start gap-3 border rounded-lg px-4 py-3 text-sm {$cls}"]) }}>
    <x-ui.icon :name="$icon" class="w-5 h-5 mt-px" />
    <div class="flex-1 min-w-0">{{ $slot }}</div>
    @if($dismissible)
        <button type="button" @click="show = false" aria-label="Hinweis schließen"
                class="-mr-1 opacity-70 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current rounded">
            <x-ui.icon name="x" class="w-4 h-4" />
        </button>
    @endif
</div>

{{--
    Button bzw. Link im Button-Stil.

    <x-ui.button>Speichern</x-ui.button>
    <x-ui.button variant="secondary" href="{{ route('...') }}" icon="plus">Neu</x-ui.button>
    <x-ui.button variant="danger" type="submit">Löschen</x-ui.button>

    variant: primary (eine je Bereich) | secondary | ghost | danger (nur fuer Zerstoerendes)
    size:    md | sm
    Trefferflaeche mobil mind. 44 px. Weitere Attribute (x-on, :disabled, form ...)
    werden durchgereicht.
--}}
@props([
    'variant' => 'primary',
    'size'    => 'md',
    'href'    => null,
    'icon'    => null,
    'type'    => 'button',
])

@php
    $variants = [
        'primary'   => 'bg-primary text-white hover:bg-primary-dark',
        'secondary' => 'bg-white text-gray-800 border border-gray-300 hover:bg-gray-50',
        'ghost'     => 'text-gray-700 hover:bg-gray-100',
        'danger'    => 'bg-accent text-white hover:bg-accent-dark',
    ];
    $sizes = [
        'md' => 'min-h-[44px] sm:min-h-[40px] px-4 py-2 text-sm',
        'sm' => 'min-h-[44px] sm:min-h-[34px] px-3 py-1.5 text-sm',
    ];
    // max-w-full + Text in eigener Hülle (min-w-0): lange Beschriftungen brechen um,
    // statt über den Rand zu ragen – Safari bricht losen Text im Flex-Knopf sonst nicht um
    $classes = 'inline-flex max-w-full items-center justify-center gap-2 rounded-lg font-semibold text-center leading-snug transition-colors '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 '
        . 'disabled:opacity-60 disabled:cursor-not-allowed '
        . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-ui.icon :name="$icon" class="w-4 h-4 flex-shrink-0" />@endif
        <span class="min-w-0 break-words">{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-ui.icon :name="$icon" class="w-4 h-4 flex-shrink-0" />@endif
        <span class="min-w-0 break-words">{{ $slot }}</span>
    </button>
@endif

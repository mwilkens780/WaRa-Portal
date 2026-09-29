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
        'md' => 'min-h-[44px] sm:min-h-[40px] px-4 text-sm',
        'sm' => 'min-h-[44px] sm:min-h-[34px] px-3 text-sm',
    ];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 '
        . 'disabled:opacity-60 disabled:cursor-not-allowed '
        . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-ui.icon :name="$icon" class="w-4 h-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-ui.icon :name="$icon" class="w-4 h-4" />@endif
        {{ $slot }}
    </button>
@endif

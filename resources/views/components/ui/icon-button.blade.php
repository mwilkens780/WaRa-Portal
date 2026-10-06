{{--
    Button nur mit Symbol. label ist Pflicht: Screenreader lesen ihn vor,
    Maus-Nutzer sehen ihn als Tooltip.

    <x-ui.icon-button icon="trash" label="Block löschen" @click="removeBlock(index)" />

    Sichtbar klein, Trefferflaeche aber 44 px (mobil) bzw. 36 px (Desktop).
--}}
@props(['icon', 'label', 'href' => null, 'tone' => 'neutral', 'type' => 'button'])

@php
    $tones = [
        'neutral' => 'text-gray-500 hover:text-gray-800 hover:bg-gray-100',
        'danger'  => 'text-gray-500 hover:text-red-700 hover:bg-red-50',
        'light'   => 'text-white/80 hover:text-white hover:bg-white/10',
    ];
    $classes = 'inline-flex items-center justify-center w-11 h-11 sm:w-9 sm:h-9 rounded-lg transition-colors '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-40 '
        . ($tones[$tone] ?? $tones['neutral']);
@endphp

@if($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-ui.icon :name="$icon" class="w-5 h-5" />
    </a>
@else
    <button type="{{ $type }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-ui.icon :name="$icon" class="w-5 h-5" />
    </button>
@endif

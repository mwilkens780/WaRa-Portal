{{--
    Status- oder Kategorie-Kennzeichen.

    <x-ui.badge tone="success">Zugesagt</x-ui.badge>

    tone: neutral | brand | success | warning | danger | info
    Farben mit mind. 4,5:1 Kontrast (Text 700/800 auf 50/100).
--}}
@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-gray-100 text-gray-700',
        'brand'   => 'bg-blue-50 text-primary-800',
        'success' => 'bg-green-100 text-green-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'danger'  => 'bg-red-100 text-red-800',
        'info'    => 'bg-sky-100 text-sky-800',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ' . ($tones[$tone] ?? $tones['neutral'])]) }}>{{ $slot }}</span>

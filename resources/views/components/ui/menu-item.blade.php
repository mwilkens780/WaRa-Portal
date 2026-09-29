{{-- Eintrag in <x-ui.menu>. Als Link (href), Button oder Formular-Submit (type="submit"). --}}
@props(['href' => null, 'tone' => 'neutral', 'active' => false, 'type' => 'button'])

@php
    $classes = 'flex w-full items-center gap-2 px-4 min-h-[44px] sm:min-h-[36px] text-sm text-left focus:outline-none '
        . ($tone === 'danger' ? 'text-red-700 hover:bg-red-50 focus:bg-red-50' : 'text-gray-800 hover:bg-gray-50 focus:bg-gray-100')
        . ($active ? ' font-semibold text-primary' : '');
@endphp

@if($href)
    <a href="{{ $href }}" role="menuitem" @if($active) aria-current="true" @endif {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" role="menuitem" @if($active) aria-current="true" @endif {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif

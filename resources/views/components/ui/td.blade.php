{{--
    Tabellenzelle zu x-ui.table.

    <x-ui.td label="Zeit" num>0:24,53</x-ui.td>

    align:  left | right | center      num:   Zahl (rechtsbündig, gleiche Ziffernbreite)
    hide:   sm | md | lg (erst ab dieser Breite sichtbar)
    label:  Spaltenname für die Stapelansicht auf dem Handy (x-ui.table stack)
    strong: Hauptspalte (dunkler, halbfett)     muted: Nebeninfo (grau)
    dense:  geringerer Abstand (zu x-ui.table dense)
--}}
@props(['align' => null, 'num' => false, 'hide' => null, 'label' => null, 'strong' => false, 'muted' => false, 'dense' => false])

@php
    $alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align ?? ($num ? 'right' : 'left')];
    $hideClass  = ['sm' => 'hidden sm:table-cell', 'md' => 'hidden md:table-cell', 'lg' => 'hidden lg:table-cell'][$hide] ?? '';
    $tone       = $strong ? 'font-medium text-gray-900' : ($muted ? 'text-gray-600' : 'text-gray-700');
@endphp

<td @if($label !== null) data-label="{{ $label }}" @endif
    {{ $attributes->merge(['class' => trim(($dense ? 'px-3 py-1.5' : 'px-4 py-2.5') . " $alignClass $hideClass $tone" . ($num ? ' tabular-nums whitespace-nowrap' : ''))]) }}>{{ $slot }}</td>

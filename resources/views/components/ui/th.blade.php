{{--
    Kopfzelle für den head-Slot von x-ui.table (wenn columns nicht reicht, z. B. „Alle auswählen“).

    <x-slot:head>
        <x-ui.th class="w-10"><input type="checkbox" aria-label="Alle auswählen" …></x-ui.th>
        <x-ui.th>Name</x-ui.th>
        <x-ui.th align="right">Zeit</x-ui.th>
    </x-slot:head>

    align: left | right | center    hide: sm | md | lg    sr: nur für Screenreader    dense: engerer Abstand
--}}
@props(['align' => 'left', 'hide' => null, 'sr' => false, 'dense' => false])

@php
    $alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
    $hideClass  = ['sm' => 'hidden sm:table-cell', 'md' => 'hidden md:table-cell', 'lg' => 'hidden lg:table-cell'][$hide] ?? '';
@endphp

<th scope="col" {{ $attributes->merge(['class' => trim(($dense ? 'px-3 py-1.5' : 'px-4 py-2.5') . " $alignClass $hideClass text-xs font-semibold uppercase tracking-wide text-gray-600 whitespace-nowrap")]) }}>
    @if($sr)<span class="sr-only">{{ $slot }}</span>@else{{ $slot }}@endif
</th>

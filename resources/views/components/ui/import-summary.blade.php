{{--
    Zusammenfassung einer Import-Vorschau: was neu ist, was sich aendert,
    was uebersprungen wird - in allen Importen dieselben Begriffe und Farben.

    <x-ui.import-summary :items="[
        ['label' => 'neu',           'count' => 12, 'tone' => 'success'],
        ['label' => 'geändert',      'count' => 3,  'tone' => 'brand'],
        ['label' => 'übersprungen',  'count' => 5,  'tone' => 'neutral', 'hint' => 'schon vorhanden'],
        ['label' => 'Fehler',        'count' => 1,  'tone' => 'danger'],
    ]" />

    Eintraege mit count 0 werden gedimmt, nicht versteckt - "0 Fehler" ist
    eine gute Nachricht.
--}}
@props(['items' => []])

@php
    $tones = [
        'success' => 'border-green-200 bg-green-50 text-green-800',
        'brand'   => 'border-blue-200 bg-blue-50 text-blue-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger'  => 'border-red-200 bg-red-50 text-red-800',
        'neutral' => 'border-gray-200 bg-gray-50 text-gray-800',
    ];
@endphp

<dl {{ $attributes->merge(['class' => 'grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3']) }}>
    @foreach($items as $item)
        @php $zero = (int) $item['count'] === 0; @endphp
        <div class="rounded-xl border px-3 py-2.5 {{ $zero ? 'border-gray-200 bg-white text-gray-600' : ($tones[$item['tone'] ?? 'neutral'] ?? $tones['neutral']) }}">
            <dt class="text-xs font-medium">{{ $item['label'] }}</dt>
            <dd class="text-2xl font-bold tabular-nums leading-tight">{{ $item['count'] }}</dd>
            @if(!empty($item['hint']))
                <dd class="text-[11px] leading-tight mt-0.5 opacity-90">{{ $item['hint'] }}</dd>
            @endif
        </div>
    @endforeach
</dl>

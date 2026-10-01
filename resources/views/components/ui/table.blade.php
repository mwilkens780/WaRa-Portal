{{--
    Tabelle nach docs/design-system.md (Abschnitt 7): Karte, Scrollbereich,
    unsichtbare Beschriftung, Kopfzellen mit scope, Leerzustand, auf Wunsch
    Stapelansicht fürs Handy.

    <x-ui.table caption="Vereinsrekorde Freistil" title="Freistil" meta="12 Strecken"
                :columns="['Strecke', ['label' => 'Zeit', 'align' => 'right'], ['label' => 'Ort', 'hide' => 'md'], ['label' => 'Aktionen', 'sr' => true]]"
                :empty="$rows->isEmpty()" empty-title="Noch keine Rekorde" stack>
        @foreach($rows as $r)
            <tr class="hover:bg-gray-50">
                <x-ui.td label="Strecke" strong>{{ $r->distance }} m</x-ui.td>
                <x-ui.td label="Zeit" num>{{ $r->time }}</x-ui.td>
                <x-ui.td label="Ort" hide="md">{{ $r->location }}</x-ui.td>
                <x-ui.td align="right">…</x-ui.td>
            </tr>
        @endforeach
    </x-ui.table>

    columns: Text oder ['label', 'align' => left|right|center, 'hide' => sm|md|lg, 'sr' => nur für Screenreader, 'class']
    stack:   unter 768 px wird jede Zeile eine Karte (Zellen brauchen label="…")
    card:    false = ohne Kartenrahmen (z. B. innerhalb einer bestehenden Karte)
    dense:   engere Kopfzeilen (Zellen dann mit x-ui.td dense)
    Slots:   head (eigene Kopfzellen statt columns, z. B. mit „Alle auswählen“), foot (tfoot), actions (Kartenkopf)
--}}
@props([
    'caption',
    'columns'    => [],
    'title'      => null,
    'meta'       => null,
    'card'       => true,
    'stack'      => false,
    'dense'      => false,
    'empty'      => false,
    'emptyTitle' => 'Keine Einträge',
    'emptyText'  => null,
    'emptyIcon'  => 'inbox',
])

@php
    // Inneres einmal bauen - mit oder ohne Kartenrahmen (ein Komponenten-Tag laesst sich nicht per @if halb oeffnen)
    $inner = view('components.ui.partials.table-inner', [
        'caption' => $caption, 'columns' => $columns, 'stack' => $stack, 'dense' => $dense,
        'empty' => $empty, 'emptyTitle' => $emptyTitle, 'emptyText' => $emptyText, 'emptyIcon' => $emptyIcon,
        'rows' => $slot, 'head' => $head ?? null, 'foot' => $foot ?? null, 'tableAttributes' => $attributes,
    ])->render();
@endphp

@if($card)
    <x-ui.card :title="$title" :meta="$meta" :padded="false">
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
        {!! $inner !!}
    </x-ui.card>
@else
    {!! $inner !!}
@endif

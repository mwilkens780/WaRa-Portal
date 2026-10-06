@extends('layouts.app')
@section('title', 'Einladungen')
@section('page-title', 'Einladungen')

@php $statusTone = ['offen' => 'neutral', 'zugesagt' => 'success', 'vielleicht' => 'warning', 'abgesagt' => 'danger']; @endphp

@section('content')
<div class="mt-2 max-w-4xl space-y-6">
    @if($officialInvites->isNotEmpty())
        <x-ui.table caption="Kampfrichter-Anfragen" title="Kampfrichter-Anfragen"
                    :columns="['Wettkampf', ['label' => 'Datum', 'hide' => 'sm'], 'Rückmeldung', ['label' => 'Aktionen', 'sr' => true]]" stack>
            @foreach($officialInvites as $oi)
                @php $c = $oi->request->competition; $open = $oi->request->isOpen(); @endphp
                <tr class="hover:bg-gray-50">
                    <x-ui.td label="Wettkampf" strong>{{ $c->name }}<span class="block text-xs font-normal text-gray-600">{{ $c->location }}</span></x-ui.td>
                    <x-ui.td label="Datum" hide="sm" class="text-sm text-gray-700">{{ $c->date->format('d.m.Y') }}@if($c->date_end && !$c->date_end->isSameDay($c->date))–{{ $c->date_end->format('d.m.Y') }}@endif</x-ui.td>
                    <x-ui.td label="Rückmeldung">
                        @if($oi->responded_at)
                            <x-ui.badge :tone="$oi->availableAnyDay() ? 'success' : 'danger'">{{ $oi->availableAnyDay() ? 'verfügbar' : 'nicht verfügbar' }}</x-ui.badge>
                        @else
                            <x-ui.badge>offen</x-ui.badge>
                        @endif
                    </x-ui.td>
                    <x-ui.td align="right">
                        <x-ui.button size="sm" :variant="!$oi->responded_at && $open ? 'primary' : 'secondary'" :href="route('officials.respond', $oi->request)">
                            {{ !$oi->responded_at && $open ? 'Antworten' : 'Ansehen' }}
                        </x-ui.button>
                    </x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @foreach(['Anstehend' => $upcoming, 'Vergangen (30 Tage)' => $past] as $heading => $list)
        @continue($heading !== 'Anstehend' && $list->isEmpty())
        <x-ui.table :caption="'Einladungen ' . $heading" :title="$heading"
                    :columns="['Termin', ['label' => 'Wann', 'hide' => 'sm'], 'Rückmeldung', ['label' => 'Aktionen', 'sr' => true]]"
                    :empty="$list->isEmpty()" empty-title="Keine anstehenden Einladungen" stack>
            @foreach($list as $inv)
                <tr class="hover:bg-gray-50">
                    <x-ui.td label="Termin" strong>
                        {{ $inv->event->title }}
                        <span class="block text-xs font-normal text-gray-600">
                            {{ $inv->event->type_label }}@if($inv->user_id !== $user->id) · für {{ $inv->user->firstname }}@endif
                        </span>
                    </x-ui.td>
                    <x-ui.td label="Wann" hide="sm" class="text-sm text-gray-700">{{ $inv->event->when_label }}</x-ui.td>
                    <x-ui.td label="Rückmeldung">
                        <x-ui.badge :tone="$statusTone[$inv->status] ?? 'neutral'">{{ $inv->status_label }}</x-ui.badge>
                    </x-ui.td>
                    <x-ui.td align="right">
                        <x-ui.button size="sm" :variant="$inv->status === 'offen' && $inv->event->rsvpOpen() ? 'primary' : 'secondary'"
                                     :href="route('calendar.events.show', $inv->event)">
                            {{ $inv->status === 'offen' && $inv->event->rsvpOpen() ? 'Antworten' : 'Ansehen' }}
                        </x-ui.button>
                    </x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @endforeach
</div>
@endsection

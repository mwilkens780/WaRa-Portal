@extends('layouts.app')
@section('title', 'Einladungen')
@section('page-title', 'Einladungen')

@php $statusTone = ['offen' => 'neutral', 'zugesagt' => 'success', 'vielleicht' => 'warning', 'abgesagt' => 'danger']; @endphp

@section('content')
<div class="mt-2 max-w-4xl space-y-6">
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

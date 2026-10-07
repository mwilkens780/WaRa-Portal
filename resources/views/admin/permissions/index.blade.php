@extends('layouts.app')
@section('title', 'Berechtigungs-Matrix')
@section('page-title', 'Berechtigungs-Matrix')

@section('content')
@php
    $sections = \App\Models\MenuPermission::SECTIONS;
    $roleLabels = \App\Models\User::ROLE_LABELS;
    $editableRoles = array_filter(array_keys($roleLabels), fn($r) => $r !== 'admin');
@endphp
<div class="mt-2">
    <div class="mb-5 space-y-2 text-sm text-gray-500 max-w-3xl">
        <p>
            Ein Haken steuert beides: Der Menüpunkt erscheint in der Seitenleiste
            <strong>und</strong> der Bereich ist aufrufbar. Ohne Haken führt auch der
            direkte Aufruf der Adresse zu „Zugriff verweigert“.
        </p>
        <p>
            Es zählen die Portal-Rolle <strong>und</strong> alle Vereinsrollen eines Mitglieds:
            Ein Trainer, der auch im Vorstand ist, bekommt die Rechte beider Spalten.
            „Reichweite“ erweitert einen Bereich von den eigenen Gruppen auf alle.
        </p>
        <p>
            Der <strong>Administrator</strong> hat immer Zugriff auf alle Bereiche.
            Reine Systemwerkzeuge – Berechtigungs-Matrix, Protokoll, Crawler &amp; Import-Log,
            DSGVO-Anfragen, Einstellungen und die vollständige Benutzerverwaltung – stehen
            deshalb nicht in dieser Tabelle. Sie sind fest auf die Administrator-Rolle
            beschränkt und lassen sich nicht freischalten.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.permissions.update') }}">
        @csrf @method('PUT')

        @foreach($sections as $sectionKey => $sectionLabel)
        @php $sectionItems = array_filter($items, fn($i) => $i['section'] === $sectionKey); @endphp
        @if(count($sectionItems))
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">{{ $sectionLabel }}</h3>
            </div>
            <x-ui.table :card="false" caption="Menürechte je Rolle">
<x-slot:head>
                            <x-ui.th class="w-48">Bereich</x-ui.th>
                            @foreach($editableRoles as $role)
                                <x-ui.th align="center">{{ $roleLabels[$role] }}</x-ui.th>
                            @endforeach
                        </x-slot:head>
                        @foreach($sectionItems as $key => $item)
                        <tr class="hover:bg-gray-50/50">
                            <x-ui.td class="text-gray-800 font-medium">{{ $item['label'] }}</x-ui.td>
                            @foreach($editableRoles as $role)
                            <x-ui.td align="center">
                                <input type="checkbox"
                                       aria-label="{{ $roleLabels[$role] }}: {{ $item['label'] }}"
                                       name="permissions[{{ $role }}][{{ $key }}]"
                                       value="1"
                                       {{ ($matrix[$role][$key] ?? false) ? 'checked' : '' }}
                                       class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-blue-500 cursor-pointer">
                            </x-ui.td>
                            @endforeach
                        </tr>
                        @endforeach
                    </x-ui.table>
        </div>
        @endif
        @endforeach

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Berechtigungen speichern
            </button>
        </div>
    </form>
</div>
@endsection

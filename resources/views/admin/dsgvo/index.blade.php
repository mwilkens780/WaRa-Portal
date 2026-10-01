@extends('layouts.app')
@section('title', 'DSGVO-Anfragen')
@section('page-title', 'DSGVO-Anfragen')

@section('content')
<div class="mt-2 space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-gray-500">
                Gemäß Art. 12 Abs. 3 DSGVO müssen Anfragen <strong>innerhalb von 30 Tagen</strong> beantwortet werden.
            </p>
        </div>
        <a href="{{ route('admin.dsgvo.create') }}"
           class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Anfrage erfassen
        </a>
    </div>

    @if($openCount > 0)
    <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        {{ $openCount }} {{ $openCount === 1 ? 'offene Anfrage' : 'offene Anfragen' }} — Fristenkontrolle beachten!
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <x-ui.table :card="false" caption="DSGVO-Übersicht" stack
            :columns="['Anfragender', 'Art', ['label' => 'Eingegangen', 'hide' => 'md'], ['label' => 'Frist', 'hide' => 'lg'], 'Status', ['label' => 'Aktionen', 'sr' => true]]">
                @forelse($requests as $req)
                <tr class="hover:bg-gray-50 transition-colors {{ $req->isOverdue() ? 'bg-red-50/30' : '' }}">
                    <x-ui.td label="Anfragender">
                        <p class="font-medium text-gray-800">{{ $req->requester_name }}</p>
                        @if($req->user)
                            <p class="text-xs text-gray-600">Portal-Nutzer: {{ $req->user->name }}</p>
                        @endif
                        @if($req->requester_email)
                            <p class="text-xs text-gray-600">{{ $req->requester_email }}</p>
                        @endif
                    </x-ui.td>
                    <x-ui.td label="Art" class="text-gray-700">{{ $req->typeLabel() }}</x-ui.td>
                    <x-ui.td label="Eingegangen" hide="md" muted>{{ $req->created_at->format('d.m.Y') }}</x-ui.td>
                    <x-ui.td label="Frist" hide="lg">
                        <span class="{{ $req->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                            {{ $req->deadline()->format('d.m.Y') }}
                            @if($req->isOverdue()) ⚠️ @endif
                        </span>
                    </x-ui.td>
                    <x-ui.td label="Status">
                        <span class="text-xs font-medium px-2 py-1 rounded-full {{ $req->statusColor() }}">
                            {{ $req->statusLabel() }}
                        </span>
                    </x-ui.td>
                    <x-ui.td align="right">
                        <a href="{{ route('admin.dsgvo.show', $req) }}"
                           class="text-primary hover:text-primary-dark text-xs font-medium">Öffnen →</a>
                    </x-ui.td>
                </tr>
                @empty
                <tr>
                    <x-ui.td align="center" muted class="text-sm" colspan="6">
                        Keine DSGVO-Anfragen vorhanden.
                    </x-ui.td>
                </tr>
                @endforelse
            </x-ui.table>
    </div>

    {{ $requests->links() }}

    {{-- Hinweise --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 text-sm text-blue-800 space-y-2">
        <p class="font-semibold">Rechtliche Hinweise zur Bearbeitung</p>
        <ul class="list-disc list-inside space-y-1 text-blue-700">
            <li><strong>Identitätsprüfung:</strong> Vor der Auskunft ist die Identität der anfragenden Person zu prüfen (Art. 12 Abs. 6 DSGVO).</li>
            <li><strong>Frist:</strong> 30 Tage ab Eingang, bei Fristverlängerung (max. +60 Tage) ist die anfragende Person vorab zu informieren (Art. 12 Abs. 3 DSGVO).</li>
            <li><strong>Auskunft:</strong> Die Datenauskunft muss vollständig und kostenlos erfolgen (Art. 15 DSGVO).</li>
            <li><strong>Löschung:</strong> Vor der Löschung prüfen, ob Aufbewahrungspflichten entgegenstehen (§ 257 HGB, § 147 AO).</li>
            <li><strong>Dokumentation:</strong> Alle Anfragen und deren Bearbeitung sind zu dokumentieren (Art. 5 Abs. 2 DSGVO – Rechenschaftspflicht).</li>
        </ul>
    </div>
</div>
@endsection

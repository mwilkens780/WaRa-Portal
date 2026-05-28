@extends('layouts.app')

@section('title', 'WebClub Import – Vorschau')
@section('page-title', 'WebClub Import – Vorschau')

@section('content')
<div class="space-y-5">

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-green-700">{{ $stats['new'] }}</p>
            <p class="text-xs text-green-600 mt-0.5">Neu (aus CSV)</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-blue-700">{{ $stats['update'] }}</p>
            <p class="text-xs text-blue-600 mt-0.5">Update (aus CSV)</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-gray-500">{{ $stats['skip'] }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Ohne Rolle (zuweisbar)</p>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-700">
        In der Spalte <strong>Rolle</strong> kannst du die Primär-Rolle vor dem Import ändern.
        Wähle <em>– überspringen –</em>, um einen Datensatz nicht zu importieren.
        Zeilen ohne erkannte WebClub-Rolle sind amber markiert – weise ihnen eine Rolle zu, um sie zu importieren.
    </div>

    <form method="POST" action="{{ route('admin.webclub-import.execute') }}">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">{{ count($rows) }} Datensätze</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold w-28">Aktion</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Name</th>
                            <th class="px-4 py-2.5 text-left font-semibold w-44">Rolle</th>
                            <th class="px-4 py-2.5 text-left font-semibold hidden md:table-cell">DSV-ID</th>
                            <th class="px-4 py-2.5 text-left font-semibold hidden lg:table-cell">E-Mail</th>
                            <th class="px-4 py-2.5 text-left font-semibold hidden xl:table-cell">Hinweis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $index => $row)
                            @php
                                $isUnassigned = ($row['action'] === 'skip' && isset($row['reason']));
                                $trClass = $isUnassigned
                                    ? 'bg-amber-50 hover:bg-amber-100 transition-colors'
                                    : 'hover:bg-gray-50 transition-colors';
                            @endphp
                            <tr class="{{ $trClass }}">

                                {{-- Aktion badge (reflects original parse, not final) --}}
                                <td class="px-4 py-2.5">
                                    @if($row['action'] === 'new')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Neu</span>
                                    @elseif($row['action'] === 'update')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">Update</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700">Ohne Rolle</span>
                                    @endif
                                </td>

                                {{-- Name --}}
                                <td class="px-4 py-2.5 font-medium text-gray-900">{{ $row['name'] }}</td>

                                {{-- Unified role select: empty = skip, value = import --}}
                                <td class="px-4 py-2.5">
                                    <select name="roles[{{ $index }}]"
                                            class="w-full px-2 py-1 border rounded text-xs focus:ring-2 focus:ring-blue-500 outline-none
                                                   {{ $isUnassigned ? 'border-amber-400 bg-amber-50' : 'border-gray-300' }}">
                                        <option value="">– überspringen –</option>
                                        @foreach(\App\Models\User::ROLE_LABELS as $value => $label)
                                            <option value="{{ $value }}"
                                                    {{ ($row['role'] ?? '') === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- DSV-ID --}}
                                <td class="px-4 py-2.5 text-gray-500 font-mono text-xs hidden md:table-cell">
                                    {{ $row['dsv_id'] ?: '–' }}
                                </td>

                                {{-- E-Mail --}}
                                <td class="px-4 py-2.5 text-xs hidden lg:table-cell">
                                    @if(isset($row['email']) && $row['email'])
                                        @if(str_contains($row['email'], '@mitglied.wasserratten.intern'))
                                            <span class="text-amber-600" title="Platzhalter-E-Mail">{{ $row['email'] }}</span>
                                        @else
                                            <span class="text-gray-500">{{ $row['email'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>

                                {{-- Hinweis --}}
                                <td class="px-4 py-2.5 text-xs text-gray-400 hidden xl:table-cell">
                                    @if($row['action'] === 'update')
                                        Bestehender User ID {{ $row['user_id'] }}
                                    @elseif($isUnassigned)
                                        {{ $row['reason'] ?? '' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Import durchführen
            </button>

            <a href="{{ route('admin.webclub-import.index') }}"
               class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                Abbrechen
            </a>
        </div>

    </form>
</div>
@endsection

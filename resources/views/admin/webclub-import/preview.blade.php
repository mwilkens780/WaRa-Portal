@extends('layouts.app')

@section('title', 'Mitglieder aus WebClub – Vorschau')
@section('page-title', 'Mitglieder aus WebClub – Vorschau')

@section('content')
<div class="mt-2 space-y-5">
    <x-ui.import-steps :current="2" />

    <x-ui.import-summary :items="[
        ['label' => 'neu', 'count' => $stats['new'], 'tone' => 'success'],
        ['label' => 'aktualisiert', 'count' => $stats['update'], 'tone' => 'brand', 'hint' => 'bestehende Konten'],
        ['label' => 'ohne Rolle', 'count' => $stats['skip'], 'tone' => 'warning', 'hint' => 'Rolle zuweisen, sonst übersprungen'],
    ]" />

    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-700">
        In der Spalte <strong>Rolle</strong> kannst du die Primär-Rolle vor dem Import ändern.
        Wähle <em>– überspringen –</em>, um einen Datensatz nicht zu importieren.
        Zeilen ohne erkannte WebClub-Rolle sind amber markiert – weise ihnen eine Rolle zu, um sie zu importieren.
    </div>

    <form method="POST" action="{{ route('admin.webclub-import.execute') }}">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">{{ count($rows) }} Datensätze</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-600 tracking-wide">
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
                                $isUnassigned  = ($row['action'] === 'skip' && isset($row['reason']));
                                $hasUserMatch  = $isUnassigned && ($row['user_id'] ?? null);
                                $trClass = $hasUserMatch
                                    ? 'bg-orange-50 hover:bg-orange-100 transition-colors'
                                    : ($isUnassigned
                                        ? 'bg-amber-50 hover:bg-amber-100 transition-colors'
                                        : 'hover:bg-gray-50 transition-colors');
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
                                    <select name="roles[{{ $index }}]" aria-label="Rolle für {{ $row['name'] }}"
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
                                <td class="px-4 py-2.5 text-xs hidden xl:table-cell">
                                    @if($row['action'] === 'update')
                                        <span class="text-blue-600">Update User #{{ $row['user_id'] }}</span>
                                        @if($row['existing_name'] ?? null)
                                            <span class="text-gray-500">({{ $row['existing_name'] }})</span>
                                        @endif
                                        @if(($row['matched_by'] ?? null) === 'dsv_id')
                                            <span class="text-gray-400">· via DSV-ID</span>
                                        @elseif(($row['matched_by'] ?? null) === 'name_birthdate')
                                            <span class="text-gray-400">· via Name+Geb.</span>
                                        @endif
                                    @elseif($isUnassigned && ($row['user_id'] ?? null))
                                        {{-- Skip-Zeile, aber Zuordnung zu bestehendem User gefunden --}}
                                        <span class="font-semibold text-orange-600">
                                            ⚠ Zuordnung zu User #{{ $row['user_id'] }}
                                            @if($row['existing_name'] ?? null)({{ $row['existing_name'] }})@endif
                                        </span>
                                        @if(($row['matched_by'] ?? null) === 'dsv_id')
                                            <span class="text-orange-500">via DSV-ID</span>
                                        @elseif(($row['matched_by'] ?? null) === 'name_birthdate')
                                            <span class="text-orange-500">via Name+Geb.</span>
                                        @endif
                                        <br><span class="text-gray-400">→ Rollenzuweisung aktualisiert bestehenden User, legt keinen neuen an</span>
                                    @elseif($isUnassigned)
                                        <span class="text-gray-400">{{ $row['reason'] ?? '' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <x-ui.import-bar :cancel="route('admin.webclub-import.index')" count="select[name^='roles'] option:checked:not([value=''])"
                         singular="Mitglied" plural="Mitglieder" />

    </form>
</div>
@endsection

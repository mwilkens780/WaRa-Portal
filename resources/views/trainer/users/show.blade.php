@extends('layouts.app')
@section('title', $user->name)
@section('page-title', $user->name)

@php
    $portal = $user->portalStatus();

    $stammdaten = [
        'Geburtsdatum'    => $user->birth_date
                                ? $user->birth_date->format('d.m.Y') . ($user->age !== null ? " ({$user->age} J.)" : '')
                                : null,
        'Geschlecht'      => match($user->gender) { 'M' => 'männlich', 'F' => 'weiblich', default => null },
        'Rolle'           => $user->role_label,
        'Mitgliedsnummer' => $user->membership_number,
        'DSV-ID'          => $user->dsv_id,
        'Mitglied seit'   => $user->member_since?->format('d.m.Y'),
        'Ausgetreten am'  => $user->resigned_at?->format('d.m.Y'),
    ];

    $kontakt = [
        'E-Mail (Login)' => $user->email,
        'E-Mail privat'  => $user->email2,
        'Telefon'        => $user->phone,
        'Mobil'          => $user->mobile,
    ];

    $adresse = [
        'Straße' => $user->street,
        'PLZ'    => $user->postal_code,
        'Ort'    => $user->city,
        'Land'   => $user->country,
    ];
@endphp

@section('content')
<div class="max-w-3xl mt-2 space-y-5">

    {{-- Kopf: wer, und in welchem Zustand --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold flex-shrink-0">
                {{ mb_substr($user->firstname ?: $user->name, 0, 1) }}
            </div>
            <div>
                <p class="font-semibold text-gray-800">{{ $user->name }}</p>
                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ $user->role_label }}</span>
                    @if($user->active)
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Aktives Mitglied</span>
                    @else
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700">Ehemaliges Mitglied</span>
                    @endif
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $portal['tone'] }}">{{ $portal['label'] }}</span>
                    @if($user->isPortalActivated())
                        <span class="text-[11px] text-gray-400">aktiviert am {{ $user->portal_activated_at->deBerlin('d.m.Y') }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($darfEditieren)
                <a href="{{ route('users-lite.edit', $user) }}"
                   class="text-sm font-medium text-primary hover:text-primary-dark">Bearbeiten</a>
            @endif
            <a href="{{ route('users-lite.index') }}"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Zurück</a>
        </div>
    </div>

    {{-- Trainingsgruppen, und das Zuordnen einer eigenen Gruppe --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Trainingsgruppen</h2>
        <div class="flex flex-wrap gap-2">
            @forelse($user->trainingGroups as $gruppe)
                <span class="text-xs font-medium text-gray-700 bg-gray-100 px-2.5 py-1 rounded-full">{{ $gruppe->name }}</span>
            @empty
                <p class="text-sm text-gray-400">Noch keiner Gruppe zugeordnet.</p>
            @endforelse
        </div>

        @if($offeneGruppen->isNotEmpty())
            <form method="POST" action="{{ route('users-lite.assign-group', $user) }}"
                  class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                @csrf
                <label class="text-sm text-gray-600">Zu meiner Gruppe hinzufügen:</label>
                <select aria-label="Zu meiner Gruppe hinzufügen" name="group_id" required
                        class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Gruppe wählen…</option>
                    @foreach($offeneGruppen as $gruppe)
                        <option value="{{ $gruppe->id }}">{{ $gruppe->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                    Zuordnen
                </button>
                @error('group_id')<p class="text-red-600 text-xs w-full">{{ $message }}</p>@enderror
            </form>
            <p class="text-xs text-gray-400 mt-2">
                Aus einer Gruppe entfernt wird über die Trainingsgruppe selbst.
            </p>
        @endif
    </div>

    {{-- Stammdaten, Kontakt, Adresse: nur zum Lesen --}}
    @foreach(['Stammdaten' => $stammdaten, 'Kontakt' => $kontakt, 'Adresse' => $adresse] as $titel => $felder)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">{{ $titel }}</h2>
            <dl class="grid md:grid-cols-2 gap-x-6 gap-y-3">
                @foreach($felder as $label => $wert)
                    <div>
                        <dt class="text-xs text-gray-500">{{ $label }}</dt>
                        <dd class="text-sm text-gray-800">
                            @if($wert && in_array($label, ['E-Mail (Login)', 'E-Mail privat'], true))
                                <a href="mailto:{{ $wert }}" class="text-primary underline underline-offset-2 hover:no-underline">{{ $wert }}</a>
                            @elseif($wert && ($label === 'Telefon' || $label === 'Mobil'))
                                <a href="tel:{{ $wert }}" class="text-primary underline underline-offset-2 hover:no-underline">{{ $wert }}</a>
                            @else
                                {{ $wert ?: '–' }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach

    {{-- Eltern und Kinder: im Trainingsalltag die wichtigsten Kontakte --}}
    @foreach(['Eltern' => $user->parents, 'Kinder' => $user->children] as $titel => $personen)
        @if($personen->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">{{ $titel }}</h2>
                <ul class="divide-y divide-gray-50">
                    @foreach($personen as $person)
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-gray-800">{{ $person->name }}</span>
                            <span class="text-gray-500 text-xs">
                                @if($person->email)<a href="mailto:{{ $person->email }}" class="text-primary underline underline-offset-2 hover:no-underline">{{ $person->email }}</a>@endif
                                @if($person->email && ($person->mobile || $person->phone)) · @endif
                                {{ $person->mobile ?: $person->phone }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach

    {{-- Notizen --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Notizen</h2>
        @if($user->notes)
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $user->notes }}</p>
        @else
            <p class="text-sm text-gray-400">Keine Notizen hinterlegt.</p>
        @endif
    </div>
</div>
@endsection

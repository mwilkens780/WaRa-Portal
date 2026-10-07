@extends('layouts.app')
@section('title', 'Kampfrichter & Lizenzen')
@section('page-title', 'Kampfrichter & Lizenzen')

@php
    use App\Models\User;
    use App\View\Components\OfficialsPanel;
    $warn = OfficialsPanel::LICENSE_WARN_MONTHS;
@endphp

@section('content')
<div class="mt-2 max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-700">{{ $users->count() }} Kampfrichter{{ $expiringOnly ? " mit Lizenz, die in {$warn} Monaten ausläuft oder abgelaufen ist" : '' }}</p>
        <div class="flex flex-wrap gap-2">
            <x-ui.button size="sm" :variant="$expiringOnly ? 'secondary' : 'primary'" :href="route('officials.index')">Alle</x-ui.button>
            <x-ui.button size="sm" :variant="$expiringOnly ? 'primary' : 'secondary'" :href="route('officials.index', ['auslaufend' => 1])">Läuft aus</x-ui.button>
        </div>
    </div>

    @if($errors->any())
        <x-ui.alert tone="error">Bitte die Angaben prüfen: {{ $errors->first() }}</x-ui.alert>
    @endif

    @forelse($users as $u)
        @php
            $next = $u->officialQualifications->filter(fn($q) => $q->valid_until)->sortBy('valid_until')->first();
        @endphp
        <x-ui.card :title="$u->lastname . ', ' . $u->firstname" collapsible :storage-key="'official-' . $u->id" :open="false"
                   :meta="(string) $u->officialQualifications->count()">
            <p class="text-xs text-gray-600 mb-3">
                {{ User::ROLE_LABELS[$u->role] ?? $u->role }}
                @if($u->userRoles->isNotEmpty()) · Vereinsrollen: {{ $u->userRoles->pluck('role')->map(fn($r) => User::ROLE_LABELS[$r] ?? $r)->implode(', ') }} @endif
                @if($u->email) · <span class="break-all">{{ $u->email }}</span> @endif
                @if($next) · nächster Ablauf {{ $next->valid_until->format('d.m.Y') }} @endif
            </p>
            @include('officials._qualifications', ['user' => $u])
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="badge" title="Keine Kampfrichter gefunden" /></x-ui.card>
    @endforelse

    {{-- Qualifikation für weitere Person anlegen (wird damit zur Kampfrichterin / zum Kampfrichter in dieser Liste) --}}
    <x-ui.card title="Weitere Person eintragen" collapsible storage-key="official-new" :open="false">
        <form method="POST" x-data="{ id: '' }" :action="id ? @js(url('/kampfrichter')) + '/' + id + '/qualifikationen' : ''" class="space-y-3">
            @csrf
            <x-ui.field label="Person" name="person" as="select" x-model="id" required>
                <option value="">– auswählen –</option>
                @foreach($candidates as $c)
                    <option value="{{ $c->id }}">{{ $c->lastname }}, {{ $c->firstname }}</option>
                @endforeach
            </x-ui.field>
            @include('officials._qualification-fields', ['q' => null, 'listId' => 'qual-titles-new', 'prefix' => 'cand'])
            <datalist id="qual-titles-new">
                @foreach(\App\Models\OfficialQualification::SUGGESTIONS as $s)<option value="{{ $s }}">@endforeach
            </datalist>
            <x-ui.button type="submit" size="sm">Qualifikation anlegen</x-ui.button>
        </form>
    </x-ui.card>
</div>
@endsection

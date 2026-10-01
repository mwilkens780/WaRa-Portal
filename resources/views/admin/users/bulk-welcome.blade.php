@extends('layouts.app')
@section('title', 'Mitglieder einladen')
@section('page-title', 'Mitglieder einladen')

@section('content')
<div class="mt-2 space-y-5"
     x-data="{ chosen: {}, onlyNeverLoggedIn: false,
               count() { return Object.values(this.chosen).filter(Boolean).length } }">

    <a href="{{ route('admin.users.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zur Benutzerverwaltung
    </a>


    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-sm text-gray-600 space-y-2">
        <p>
            Hier bekommen bestehende Mitglieder ihre Willkommensmail: Begrüßung, Link zum Portal und
            ein Einmallink, über den sie sich selbst ein Passwort setzen. Das Initialpasswort in der
            Benutzerverwaltung bleibt davon unberührt und gilt weiter für die persönliche Übergabe.
        </p>
        <p>
            Der Versand läuft über die Warteschlange: der Cron schickt {{ \App\Services\Mailer::BATCH_SIZE }} Mails
            je Lauf. Den Fortschritt siehst du im Mail-Protokoll.
        </p>
    </div>

    @if($maintenance)
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 text-amber-700 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <p class="text-amber-800">
                <strong>Der Wartungsmodus ist aktiv.</strong> Alle Mails gingen jetzt an
                {{ $testAddress ?: 'die (noch nicht hinterlegte) Testadresse' }} – deshalb ist der Massenversand
                gesperrt. Zum Testen einzelner Mails nutze die Testmail in den Einstellungen oder die
                Willkommensmail bei einem einzelnen Benutzer.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.bulk-welcome.send') }}"
          @submit.prevent="if (await $confirm({ title: `${count()} Mitglieder einladen?`, confirmLabel: 'Einladen' })) $el.submit()">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
                <h2 class="text-sm font-semibold text-gray-800">Empfänger</h2>
                <span class="text-xs text-gray-600">{{ $candidates->count() }} aktive Mitglieder mit E-Mail-Adresse</span>
                <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer ml-auto">
                    <input type="checkbox" x-model="onlyNeverLoggedIn" class="rounded text-primary">
                    Nur solche, die sich noch nie angemeldet haben ({{ $neverSet->count() }})
                </label>
                <button type="button" class="text-xs text-gray-500 hover:text-primary"
                        @click="@foreach($candidates as $c) if (!onlyNeverLoggedIn || {{ $c->hasLoggedIn() ? 'false' : 'true' }}) chosen['u{{ $c->id }}'] = true; @endforeach">
                    sichtbare auswählen
                </button>
                <button type="button" class="text-xs text-gray-500 hover:text-primary" @click="chosen = {}">Auswahl leeren</button>
            </div>

            <x-ui.table :card="false" caption="Empfänger der Willkommensmail" class="table-fixed min-w-[760px]"
            :columns="[['label' => 'Aktionen', 'sr' => true, 'class' => 'w-10'], ['label' => 'Name', 'class' => 'w-64'], 'E-Mail', ['label' => 'Rolle', 'class' => 'w-32'], ['label' => 'Stand', 'class' => 'w-40']]">
                        @foreach($candidates as $candidate)
                            @php
                                $nieGesetzt = !$candidate->hasLoggedIn();
                                $schonMal   = $alreadyInvited->contains($candidate->id);
                            @endphp
                            <tr class="hover:bg-gray-50"
                                x-show="!onlyNeverLoggedIn || {{ $nieGesetzt ? 'true' : 'false' }}"
                                :class="chosen['u{{ $candidate->id }}'] ? 'bg-blue-50/40' : ''">
                                <x-ui.td>
                                    <input type="checkbox" name="users[]" value="{{ $candidate->id }}"
                                           aria-label="{{ $candidate->firstname }} {{ $candidate->lastname }} einladen"
                                           x-model="chosen['u{{ $candidate->id }}']" class="rounded text-primary"
                                           {{ $maintenance ? 'disabled' : '' }}>
                                </x-ui.td>
                                <x-ui.td class="text-gray-800 truncate">{{ $candidate->lastname }}, {{ $candidate->firstname }}</x-ui.td>
                                <x-ui.td muted class="text-xs truncate">{{ $candidate->email }}</x-ui.td>
                                <x-ui.td muted class="text-xs">{{ \App\Models\User::ROLE_LABELS[$candidate->role] ?? $candidate->role }}</x-ui.td>
                                <x-ui.td class="text-xs">
                                    @if($nieGesetzt)
                                        <span class="text-amber-700">noch nie angemeldet</span>
                                    @else
                                        <span class="text-green-700">zuletzt {{ $candidate->last_login_at->deBerlin('d.m.Y') }}</span>
                                    @endif
                                    @if($schonMal)
                                        <span class="block text-gray-600">schon eingeladen</span>
                                    @endif
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>

            <div class="px-5 py-4 border-t border-gray-100 flex items-center gap-4">
                <button type="submit" :disabled="count() === 0 || {{ $maintenance ? 'true' : 'false' }}"
                        class="px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    Einladungen einreihen
                </button>
                <span class="text-sm text-gray-500"><strong x-text="count()"></strong> ausgewählt</span>
            </div>
        </div>
    </form>
</div>
@endsection

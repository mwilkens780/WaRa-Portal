@extends('layouts.app')
@section('title', 'Benutzerverwaltung')
@section('page-title', 'Benutzerverwaltung')

@section('content')
<div class="mt-2 space-y-4">

    <div class="flex flex-wrap gap-3 items-center justify-between">
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name oder E-Mail…"
                   class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none w-48">
            <select name="role" aria-label="Nach Rolle filtern" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Alle Rollen</option>
                @foreach(\App\Models\User::ROLE_LABELS as $val => $label)
                    <option value="{{ $val }}" {{ request('role') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="active" aria-label="Nach Status filtern" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Alle</option>
                <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Aktives Mitglied</option>
                <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Ehemaliges Mitglied</option>
            </select>
            @unless($darfEditieren)
                {{-- Vereinsweite Suche: um jemanden zu finden, der noch in keiner
                     eigenen Gruppe ist, und ihn dann zuzuordnen. --}}
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" name="scope" value="all" {{ $vereinsweit ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-primary">
                    vereinsweit suchen
                </label>
            @endunless
            <button type="submit" class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">Suchen</button>
            @if(request()->hasAny(['search','role','active','scope']))
                <a href="{{ route('users-lite.index') }}" class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">Zurücksetzen</a>
            @endif
        </form>
        <a href="{{ route('users-lite.create') }}"
           class="flex items-center gap-2 bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Neuer Benutzer
        </a>
    </div>

    @unless($darfEditieren)
        <p class="text-xs text-gray-500">
            @if($vereinsweit)
                Vereinsweite Suche – Mitglieder außerhalb deiner Gruppen kannst du hier einer deiner Gruppen zuordnen.
            @else
                Angezeigt werden die Mitglieder deiner Gruppen, ihre Eltern, deine Trainerkollegen und die Konten,
                die du selbst angelegt hast. Ändern kannst du die Konten, die du selbst angelegt hast – alle
                anderen Mitgliederdaten pflegt die Geschäftsstelle.
            @endif
        </p>
    @endunless

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Name</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden md:table-cell">E-Mail</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Rolle</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden lg:table-cell">Gruppen</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Mitglied</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Portal</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($users as $user)
                @php
                    $imBereich = $meineIds->contains($user->id);
                    $portal    = $user->portalStatus();
                    // Selbst angelegte Konten darf ein Trainer auch aendern
                    $bearbeitbar = $darfEditieren || (int) $user->created_by === $meineId;
                @endphp
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-800">
                        @if($imBereich)
                            <a href="{{ route('users-lite.show', $user) }}" class="hover:text-primary">{{ $user->name }}</a>
                        @else
                            {{ $user->name }}
                            <span class="ml-1 text-[11px] text-gray-400">nicht in deiner Gruppe</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ $user->email ?? '–' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ $user->role_label }}</span>
                    </td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                        @forelse($user->trainingGroups as $gruppe)
                            <span class="text-[11px] text-gray-600 bg-gray-100 px-2 py-0.5 rounded-full">{{ $gruppe->name }}</span>
                        @empty
                            <span class="text-gray-300 text-xs">–</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-3">
                        @if($user->active)
                            <span class="text-xs font-semibold text-green-700 bg-green-100 px-2 py-0.5 rounded-full">Aktiv</span>
                        @else
                            <span class="text-xs font-semibold text-red-700 bg-red-100 px-2 py-0.5 rounded-full">Ehemalig</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $portal['tone'] }}">{{ $portal['label'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @if($imBereich)
                            <a href="{{ route('users-lite.show', $user) }}"
                               class="text-blue-600 hover:text-blue-800 font-medium text-xs">Karteikarte</a>
                            @if($bearbeitbar)
                                <a href="{{ route('users-lite.edit', $user) }}"
                                   class="ml-2 text-primary hover:text-primary-dark font-medium text-xs">Bearbeiten</a>
                            @endif
                        @elseif($gruppen->isNotEmpty())
                            <form method="POST" action="{{ route('users-lite.assign-group', $user) }}" class="flex items-center gap-2 justify-end">
                                @csrf
                                <select name="group_id" required
                                        class="px-2 py-1 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                    <option value="">Gruppe wählen…</option>
                                    @foreach($gruppen as $gruppe)
                                        <option value="{{ $gruppe->id }}">{{ $gruppe->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-xs font-medium text-primary hover:text-primary-dark">Zuordnen</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                        @if(!$vereinsweit && !$darfEditieren && request()->filled('search'))
                            Niemand in deinen Gruppen gefunden – mit „vereinsweit suchen" findest du auch
                            Mitglieder außerhalb.
                        @else
                            Keine Benutzer gefunden.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($users->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection

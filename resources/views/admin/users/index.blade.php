@extends('layouts.app')
@section('title', 'Benutzerverwaltung')
@section('page-title', 'Benutzerverwaltung')

@section('content')
<div class="mt-2 space-y-4">

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">Suche</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Name oder E-Mail..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Portal-Zugang</label>
                <select name="role" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Alle</option>
                    @foreach(\App\Models\User::ROLE_LABELS as $value => $label)
                        <option value="{{ $value }}" {{ request('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Mitgliedschaft</label>
                <select name="active" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Alle</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Aktives Mitglied</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Ehemaliges Mitglied</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">Filtern</button>
            @if(request()->hasAny(['search','role','active']))
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">Zurücksetzen</a>
            @endif
        </form>
    </div>

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">{{ $users->total() }} Benutzer gefunden</p>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.webclub-import.index') }}"
               class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                WebClub Import
            </a>
            <a href="{{ route('admin.users.create') }}"
               class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Neuer Benutzer
            </a>
        </div>
    </div>

    {{-- Tabelle --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Name</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">E-Mail</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Rollen</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden lg:table-cell">Geburtstag</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Mitglied</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($users as $user)
                        @php
                            $roleColors = [
                                'admin'        => 'bg-purple-100 text-purple-700',
                                'trainer'      => 'bg-blue-100 text-blue-700',
                                'schwimmer'    => 'bg-green-100 text-green-700',
                                'elternteil'   => 'bg-amber-100 text-amber-700',
                                'kampfrichter' => 'bg-rose-100 text-rose-700',
                                'vorstand'     => 'bg-indigo-100 text-indigo-700',
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold text-xs flex-shrink-0">
                                        {{ substr($user->firstname ?: $user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-gray-800">{{ $user->lastname }}</span>
                                        <span class="text-gray-600">, {{ $user->firstname }}</span>
                                        @if($user->membership_number)
                                            <span class="ml-1.5 text-[10px] text-gray-400">{{ $user->membership_number }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 hidden md:table-cell">
                                @if($user->email)
                                    <span class="text-gray-500 text-xs">{{ $user->email }}</span>
                                @else
                                    <span class="text-gray-300 text-xs">–</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    {{-- Portal-Zugang --}}
                                    @if($user->role)
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleColors[$user->role] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $user->role_label }}
                                        </span>
                                    @endif
                                    {{-- Vereinsrollen aus user_roles --}}
                                    @foreach($user->userRoles->where('role', '!=', $user->role) as $ur)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $roleColors[$ur->role] ?? 'bg-gray-100 text-gray-500' }} opacity-75">
                                            {{ \App\Models\User::ROLE_LABELS[$ur->role] ?? $ur->role }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 text-xs hidden lg:table-cell">
                                {{ $user->birth_date ? $user->birth_date->format('d.m.Y') . ' (' . $user->age . ' J.)' : '–' }}
                            </td>
                            <td class="px-5 py-3">
                                @if($user->active)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Aktives Mitglied</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Ehemaliges Mitglied</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-primary hover:text-primary-dark font-medium text-xs">Bearbeiten</a>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-xs {{ $user->active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }} font-medium">
                                                {{ $user->active ? 'Deaktivieren' : 'Aktivieren' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                              onsubmit="return confirm('{{ addslashes($user->name) }} wirklich löschen?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700 font-medium text-xs">Löschen</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">Keine Benutzer gefunden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="flex items-center justify-center gap-1 text-sm py-3 border-t border-gray-100">
                @if($users->onFirstPage())
                    <span class="px-3 py-1.5 rounded-lg text-gray-300 border border-gray-100">‹</span>
                @else
                    <a href="{{ $users->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">‹</a>
                @endif
                @foreach($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                    @if($page == $users->currentPage())
                        <span class="px-3 py-1.5 rounded-lg bg-primary text-white font-medium">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">{{ $page }}</a>
                    @endif
                @endforeach
                @if($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">›</a>
                @else
                    <span class="px-3 py-1.5 rounded-lg text-gray-300 border border-gray-100">›</span>
                @endif
            </div>
        @endif
    </div>

    {{-- DSV-ID Bereinigung --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-amber-800">DSV-IDs bereinigen</h3>
                <p class="text-xs text-amber-700 mt-0.5">
                    Setzt alle ungültigen Nullwerte (<code class="bg-amber-100 px-1 rounded">000000</code>, <code class="bg-amber-100 px-1 rounded">0</code> etc.) in der DSV-ID auf leer.
                    Notwendig nach Importen, die vor dem Fix stattgefunden haben.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.users.cleanup-dsv') }}"
                  onsubmit="return confirm('Alle DSV-IDs mit Nullwert (000000, 0 etc.) auf leer setzen?')">
                @csrf
                <button type="submit"
                        class="flex-shrink-0 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg transition-colors">
                    DSV-IDs bereinigen
                </button>
            </form>
        </div>
        @if(session('success') && str_contains(session('success'), 'DSV-ID'))
            <p class="text-sm text-amber-800 mt-3 font-medium">{{ session('success') }}</p>
        @endif
    </div>

    {{-- Gefahrenzone: Alle Benutzer löschen --}}
    <div class="bg-red-50 border border-red-200 rounded-xl p-5" x-data="{ open: false }">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-red-800">Alle Benutzer löschen</h3>
                <p class="text-xs text-red-600 mt-0.5">Löscht alle Benutzerkonten außer deinem eigenen. Diese Aktion ist nicht rückgängig zu machen.</p>
            </div>
            <button @click="open = !open" type="button"
                    class="flex-shrink-0 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors">
                Alle löschen…
            </button>
        </div>
        <div x-show="open" x-transition class="mt-4 border-t border-red-200 pt-4">
            <form method="POST" action="{{ route('admin.users.destroy-all') }}"
                  onsubmit="return confirm('Wirklich ALLE Benutzer löschen (außer deinem eigenen Konto)?')">
                @csrf @method('DELETE')
                <label class="block text-sm font-medium text-red-800 mb-2">
                    Gib <strong>ALLE LÖSCHEN</strong> ein, um zu bestätigen:
                </label>
                <div class="flex gap-3">
                    <input type="text" name="confirm_text" placeholder="ALLE LÖSCHEN"
                           class="flex-1 px-3 py-2 border border-red-300 rounded-lg text-sm focus:ring-2 focus:ring-red-400 outline-none bg-white">
                    <button type="submit"
                            class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-bold rounded-lg transition-colors">
                        Endgültig löschen
                    </button>
                </div>
                @error('confirm_text')
                    <p class="text-red-700 text-xs mt-1.5">{{ $message }}</p>
                @enderror
            </form>
        </div>
    </div>

</div>
@endsection

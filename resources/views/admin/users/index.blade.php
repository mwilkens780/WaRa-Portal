@extends('layouts.app')
@section('title', 'Benutzerverwaltung')
@section('page-title', 'Benutzerverwaltung')

@section('content')
<div class="mt-2 space-y-4">

    {{-- Filter & Aktionen --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">Suche</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Name oder E-Mail..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Rolle</label>
                <select name="role" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Alle Rollen</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Administrator</option>
                    <option value="trainer" {{ request('role') === 'trainer' ? 'selected' : '' }}>Trainer</option>
                    <option value="schwimmer" {{ request('role') === 'schwimmer' ? 'selected' : '' }}>Schwimmer</option>
                    <option value="elternteil" {{ request('role') === 'elternteil' ? 'selected' : '' }}>Elternteil</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="active" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Alle</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Aktiv</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inaktiv</option>
                </select>
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                Filtern
            </button>
            @if(request()->hasAny(['search','role','active']))
                <a href="{{ route('admin.users.index') }}"
                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                    Zurücksetzen
                </a>
            @endif
        </form>
    </div>

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">{{ $users->total() }} Benutzer gefunden</p>
        <a href="{{ route('admin.users.create') }}"
           class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Neuer Benutzer
        </a>
    </div>

    {{-- Tabelle --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Name</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">E-Mail</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Rolle</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden lg:table-cell">Geburtstag</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold text-xs flex-shrink-0">
                                        {{ substr($user->firstname ?: $user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-gray-800">{{ $user->lastname }}</span>
                                        <span class="text-gray-600">, {{ $user->firstname }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 hidden md:table-cell">{{ $user->email }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $roleColors = [
                                        'admin' => 'bg-purple-100 text-purple-700',
                                        'trainer' => 'bg-blue-100 text-blue-700',
                                        'schwimmer' => 'bg-green-100 text-green-700',
                                        'elternteil' => 'bg-amber-100 text-amber-700',
                                    ];
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleColors[$user->role] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $user->role_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 hidden lg:table-cell">
                                {{ $user->birth_date ? $user->birth_date->format('d.m.Y') . ' (' . $user->age . ' J.)' : '–' }}
                            </td>
                            <td class="px-5 py-3">
                                @if($user->active)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Aktiv</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inaktiv</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="text-primary hover:text-primary-dark font-medium text-xs">Bearbeiten</a>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit"
                                                    class="text-xs {{ $user->active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }} font-medium">
                                                {{ $user->active ? 'Deaktivieren' : 'Aktivieren' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                              onsubmit="return confirm('Benutzer {{ $user->name }} wirklich löschen?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700 font-medium text-xs">Löschen</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-400">
                                Keine Benutzer gefunden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

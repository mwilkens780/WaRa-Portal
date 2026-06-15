@extends('layouts.app')
@section('title', 'Benutzerverwaltung')
@section('page-title', 'Benutzerverwaltung')

@section('content')
<div class="mt-2 space-y-4">

    <div class="flex flex-wrap gap-3 items-center justify-between">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name oder E-Mail…"
                   class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none w-48">
            <select name="role" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Alle Rollen</option>
                @foreach(\App\Models\User::ROLE_LABELS as $val => $label)
                    <option value="{{ $val }}" {{ request('role') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="active" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Alle</option>
                <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Aktiv</option>
                <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inaktiv</option>
            </select>
            <button type="submit" class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">Suchen</button>
            @if(request()->hasAny(['search','role','active']))
                <a href="{{ route('users-lite.index') }}" class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">Zurücksetzen</a>
            @endif
        </form>
        <a href="{{ route('users-lite.create') }}"
           class="flex items-center gap-2 bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Neuer Benutzer
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Name</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">E-Mail</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Rolle</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $user->email ?? '–' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ $user->role_label }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @if($user->active)
                            <span class="text-xs font-semibold text-green-700 bg-green-100 px-2 py-0.5 rounded-full">Aktiv</span>
                        @else
                            <span class="text-xs font-semibold text-red-700 bg-red-100 px-2 py-0.5 rounded-full">Inaktiv</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('users-lite.edit', $user) }}"
                           class="text-blue-600 hover:text-blue-800 font-medium text-xs">Bearbeiten</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">Keine Benutzer gefunden.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($users->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection

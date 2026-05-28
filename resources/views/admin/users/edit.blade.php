@extends('layouts.app')
@section('title', 'Benutzer bearbeiten')
@section('page-title', 'Benutzer bearbeiten: ' . $user->name)

@section('content')
<div class="max-w-2xl mt-2 space-y-5">

    {{-- Initial password box --}}
    @if($user->hasInitialPassword())
    <div class="bg-amber-50 border border-amber-300 rounded-xl px-5 py-4"
         x-data="{ shown: false }">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-amber-800 mb-1">
                    Initialpasswort aktiv
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-200 text-amber-800">noch nicht geändert</span>
                </p>
                <p class="text-xs text-amber-700">
                    Der Benutzer hat sein Passwort noch nicht selbst geändert. Sobald er sich einloggt und ein neues Passwort setzt, ist dieses hier nicht mehr sichtbar.
                </p>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-3">
            <div class="flex items-center gap-2 bg-white border border-amber-300 rounded-lg px-4 py-2 font-mono text-base tracking-widest text-amber-900 select-all">
                <span x-show="!shown" class="text-amber-400 tracking-widest">••••••••</span>
                <span x-show="shown" x-cloak>{{ $initialPassword }}</span>
            </div>
            <button type="button" @click="shown = !shown"
                    class="text-xs text-amber-700 hover:text-amber-900 font-medium underline">
                <span x-text="shown ? 'Verbergen' : 'Anzeigen'">Anzeigen</span>
            </button>
            <button type="button"
                    onclick="navigator.clipboard.writeText('{{ $initialPassword }}').then(() => { this.textContent='Kopiert!'; setTimeout(() => this.textContent='Kopieren', 1500) })"
                    class="text-xs text-amber-700 hover:text-amber-900 font-medium underline">
                Kopieren
            </button>
        </div>
    </div>
    @endif

    {{-- Passwort-Reset --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-gray-800">Neues Initialpasswort generieren</p>
            <p class="text-xs text-gray-500 mt-0.5">
                Setzt ein neues, zufälliges Passwort und zeigt es oben im Initialpasswort-Feld an.
                Das bisherige Passwort des Benutzers wird überschrieben.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
              onsubmit="return confirm('Passwort für {{ addslashes($user->name) }} zurücksetzen?')">
            @csrf
            <button type="submit"
                    class="whitespace-nowrap flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Neues Passwort
            </button>
        </form>
    </div>

    {{-- Main form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5"
              x-data="{ role: '{{ old('role', $user->role) }}' }">
            @csrf
            @method('PUT')

            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname <span class="text-red-500">*</span></label>
                    <input type="text" name="firstname" value="{{ old('firstname', $user->firstname) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('firstname') ? 'border-red-400' : '' }}">
                    @error('firstname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname <span class="text-red-500">*</span></label>
                    <input type="text" name="lastname" value="{{ old('lastname', $user->lastname) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('lastname') ? 'border-red-400' : '' }}">
                    @error('lastname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Primär-Rolle <span class="text-red-500">*</span></label>
                    <select name="role" x-model="role" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                        @foreach(\App\Models\User::ROLE_LABELS as $value => $label)
                            <option value="{{ $value }}" {{ old('role', $user->role) === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @if($user->id === auth()->id())
                        <input type="hidden" name="role" value="{{ $user->role }}">
                        <p class="text-xs text-amber-600 mt-1">Eigene Rolle kann nicht geändert werden.</p>
                    @endif
                    @error('role')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Neues Passwort <span class="text-gray-400 font-normal">(leer = unverändert)</span></label>
                    <input type="password" name="password"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('password') ? 'border-red-400' : '' }}">
                    @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passwort bestätigen</label>
                    <input type="password" name="password_confirmation"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geburtsdatum</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            {{-- Additional roles --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Zusätzliche Rollen</label>
                <div class="flex flex-wrap gap-3">
                    @php $additionalRoles = old('additional_roles', $user->additional_roles ?? []); @endphp
                    @foreach(\App\Models\User::ROLE_LABELS as $value => $label)
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox"
                                   name="additional_roles[]"
                                   value="{{ $value }}"
                                   {{ in_array($value, $additionalRoles) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-primary">
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-1.5">Rollen zusätzlich zur Primär-Rolle. Wirken sich auf Anzeige und Auswertungen aus.</p>
            </div>

            {{-- Children (Elternteil) --}}
            <div x-show="role === 'elternteil'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">Kinder</label>
                <div class="border border-gray-200 rounded-lg divide-y max-h-48 overflow-y-auto">
                    @foreach($swimmers as $swimmer)
                        <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="children[]" value="{{ $swimmer->id }}"
                                   {{ in_array($swimmer->id, old('children', $assignedChildren)) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-primary">
                            <span class="text-sm">{{ $swimmer->name }}
                                {{ $swimmer->birth_date ? '(' . $swimmer->birth_date->format('d.m.Y') . ')' : '' }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            @if($user->id !== auth()->id())
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="active" value="1"
                               {{ old('active', $user->active ? '1' : '0') == '1' ? 'checked' : '' }}
                               class="w-5 h-5 rounded border-gray-300 text-primary">
                        <span class="text-sm font-medium text-gray-700">Konto aktiv</span>
                    </label>
                </div>
            @endif

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Änderungen speichern
                </button>
                <a href="{{ route('admin.users.index') }}"
                   class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

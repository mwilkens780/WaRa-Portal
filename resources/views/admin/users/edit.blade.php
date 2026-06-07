@extends('layouts.app')
@section('title', 'Benutzer bearbeiten')
@section('page-title', 'Benutzer bearbeiten: ' . $user->name)

@section('content')
@php
    $assignedRoles = old('user_roles', $user->userRoles->pluck('role')->toArray());
@endphp

<div class="max-w-3xl mt-2 space-y-5">

    {{-- Initial password --}}
    @if($user->hasInitialPassword())
    <div class="bg-amber-50 border border-amber-300 rounded-xl px-5 py-4" x-data="{ shown: false }">
        <p class="text-sm font-semibold text-amber-800 mb-1">
            Initialpasswort aktiv
            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-200 text-amber-800">noch nicht geändert</span>
        </p>
        <p class="text-xs text-amber-700 mb-3">Der Benutzer hat sein Passwort noch nicht selbst geändert.</p>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 bg-white border border-amber-300 rounded-lg px-4 py-2 font-mono text-base tracking-widest text-amber-900 select-all">
                <span x-show="!shown" class="text-amber-400 tracking-widest">••••••••</span>
                <span x-show="shown" x-cloak>{{ $initialPassword }}</span>
            </div>
            <button type="button" @click="shown = !shown" class="text-xs text-amber-700 hover:text-amber-900 font-medium underline">
                <span x-text="shown ? 'Verbergen' : 'Anzeigen'">Anzeigen</span>
            </button>
            <button type="button"
                    onclick="navigator.clipboard.writeText('{{ $initialPassword }}').then(() => { this.textContent='Kopiert!'; setTimeout(() => this.textContent='Kopieren', 1500) })"
                    class="text-xs text-amber-700 hover:text-amber-900 font-medium underline">Kopieren</button>
        </div>
    </div>
    @endif

    {{-- Password reset --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-gray-800">Neues Initialpasswort generieren</p>
            <p class="text-xs text-gray-500 mt-0.5">Überschreibt das aktuelle Passwort des Benutzers.</p>
        </div>
        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
              onsubmit="return confirm('Passwort für {{ addslashes($user->name) }} zurücksetzen?')">
            @csrf
            <button type="submit" class="whitespace-nowrap flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Neues Passwort
            </button>
        </form>
    </div>

    {{-- Main form --}}
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5"
          x-data="{ role: '{{ old('role', $user->role) }}' }">
        @csrf @method('PUT')

        {{-- ── Stammdaten ──────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Stammdaten</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname <span class="text-red-500">*</span></label>
                    <input type="text" name="firstname" value="{{ old('firstname', $user->firstname) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('firstname') ? 'border-red-400' : '' }}">
                    @error('firstname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname <span class="text-red-500">*</span></label>
                    <input type="text" name="lastname" value="{{ old('lastname', $user->lastname) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('lastname') ? 'border-red-400' : '' }}">
                    @error('lastname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geburtsdatum</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geschlecht</label>
                    <select name="gender" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">– keine Angabe –</option>
                        <option value="M" {{ old('gender', $user->gender) === 'M' ? 'selected' : '' }}>Männlich</option>
                        <option value="F" {{ old('gender', $user->gender) === 'F' ? 'selected' : '' }}>Weiblich</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mitgliedsnummer</label>
                    <input type="text" name="membership_number" value="{{ old('membership_number', $user->membership_number) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DSV-ID</label>
                    <input type="text" name="dsv_id" value="{{ old('dsv_id', $user->dsv_id) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('dsv_id') ? 'border-red-400' : '' }}">
                    @error('dsv_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Eintrittsdatum</label>
                    <input type="date" name="member_since" value="{{ old('member_since', $user->member_since?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                @if($user->id !== auth()->id())
                <div class="flex items-center gap-3 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="active" value="1"
                               {{ old('active', $user->active ? '1' : '0') == '1' ? 'checked' : '' }}
                               class="w-5 h-5 rounded border-gray-300 text-primary">
                        <span class="text-sm font-medium text-gray-700">Aktives Mitglied</span>
                    </label>
                </div>
                @endif
            </div>
        </div>

        {{-- ── Kontakt ─────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Kontakt</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail (Portal-Login)</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    <p class="text-xs text-gray-400 mt-1">Leer lassen wenn kein Portal-Zugang gewünscht.</p>
                    @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail 2</label>
                    <input type="email" name="email2" value="{{ old('email2', $user->email2) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mobil</label>
                    <input type="tel" name="mobile" value="{{ old('mobile', $user->mobile) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
        </div>

        {{-- ── Adresse ─────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Adresse</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Straße</label>
                    <input type="text" name="street" value="{{ old('street', $user->street) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">PLZ</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                    <input type="text" name="city" value="{{ old('city', $user->city) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Land</label>
                    <input type="text" name="country" value="{{ old('country', $user->country) }}" placeholder="Deutschland"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
        </div>

        {{-- ── Rollen & Portal-Zugang ──────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-1">Rollen</h2>
            <p class="text-xs text-gray-500 mb-4">Alle gleichwertigen Vereinsrollen. Der Portal-Zugang bestimmt, welchen Bereich der Benutzer beim Login sieht.</p>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-5">
                @foreach(\App\Models\User::ROLE_LABELS as $value => $label)
                <label class="flex items-center gap-2 text-sm cursor-pointer p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <input type="checkbox" name="user_roles[]" value="{{ $value }}"
                           {{ in_array($value, $assignedRoles) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-primary">
                    <span class="text-gray-700">{{ $label }}</span>
                </label>
                @endforeach
            </div>

            <div class="border-t border-gray-100 pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Portal-Zugang
                    <span class="ml-1 text-xs text-gray-400 font-normal">(welcher App-Bereich beim Login angezeigt wird)</span>
                </label>
                <select name="role" x-model="role"
                        class="w-full md:w-64 px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                        {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                    <option value="">– kein Portal-Zugang –</option>
                    @foreach(\App\Models\User::ROLE_LABELS as $value => $label)
                        <option value="{{ $value }}" {{ old('role', $user->role) === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @if($user->id === auth()->id())
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <p class="text-xs text-amber-600 mt-1">Eigener Portal-Zugang kann nicht geändert werden.</p>
                @endif
                @error('role')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Children (Elternteil) --}}
            <div x-show="role === 'elternteil'" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Kinder</label>
                <div class="border border-gray-200 rounded-lg divide-y max-h-48 overflow-y-auto">
                    @foreach($swimmers as $swimmer)
                    <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" name="children[]" value="{{ $swimmer->id }}"
                               {{ in_array($swimmer->id, old('children', $assignedChildren)) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-primary">
                        <span class="text-sm">{{ $swimmer->name }} {{ $swimmer->birth_date ? '(' . $swimmer->birth_date->format('d.m.Y') . ')' : '' }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>


        {{-- ── Zertifikate & Lizenzen ──────────────────────────────────── --}}
        @if(in_array($user->role, ['trainer','kampfrichter']) || $user->userRoles->whereIn('role', ['trainer','kampfrichter'])->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Lizenzen & Nachweise</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainerlizenz-Nr.</label>
                    <input type="text" name="trainer_license_nr" value="{{ old('trainer_license_nr', $user->trainer_license_nr) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainerlizenz gültig bis</label>
                    <input type="date" name="trainer_license_valid_until" value="{{ old('trainer_license_valid_until', $user->trainer_license_valid_until?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rettungsnachweis bis</label>
                    <input type="date" name="rescue_certificate_until" value="{{ old('rescue_certificate_until', $user->rescue_certificate_until?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Erste-Hilfe bis</label>
                    <input type="date" name="first_aid_until" value="{{ old('first_aid_until', $user->first_aid_until?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Führungszeugnis vom</label>
                    <input type="date" name="police_clearance_date" value="{{ old('police_clearance_date', $user->police_clearance_date?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
        </div>
        @endif

        {{-- ── Notizen ─────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Notizen</h2>
            <textarea name="notes" rows="3"
                      class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                      placeholder="Interne Notizen (nicht für Benutzer sichtbar)">{{ old('notes', $user->notes) }}</textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Änderungen speichern
            </button>
            <a href="{{ route('admin.users.index') }}" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Abbrechen
            </a>
        </div>

    </form>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Benutzer bearbeiten')
@section('page-title', 'Benutzer bearbeiten: ' . $user->name)

@section('content')
<div class="max-w-2xl mt-2">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rolle <span class="text-red-500">*</span></label>
                    <select name="role" x-model="role" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                        <option value="schwimmer" {{ old('role', $user->role) === 'schwimmer' ? 'selected' : '' }}>Schwimmer</option>
                        <option value="trainer" {{ old('role', $user->role) === 'trainer' ? 'selected' : '' }}>Trainer</option>
                        <option value="elternteil" {{ old('role', $user->role) === 'elternteil' ? 'selected' : '' }}>Elternteil</option>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrator</option>
                    </select>
                    @if($user->id === auth()->id())
                        <input type="hidden" name="role" value="{{ $user->role }}">
                        <p class="text-xs text-amber-600 mt-1">Eigene Rolle kann nicht geändert werden.</p>
                    @endif
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

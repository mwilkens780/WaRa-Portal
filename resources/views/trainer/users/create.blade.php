@extends('layouts.app')
@section('title', 'Neuer Benutzer')
@section('page-title', 'Neuer Benutzer')

@section('content')
<div class="max-w-xl mt-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('users-lite.store') }}" class="space-y-5">
            @csrf
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname <span class="text-red-600">*</span></label>
                    <input aria-label="Vorname" type="text" name="firstname" value="{{ old('firstname') }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('firstname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname <span class="text-red-600">*</span></label>
                    <input aria-label="Nachname" type="text" name="lastname" value="{{ old('lastname') }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('lastname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                <input aria-label="E-Mail" type="email" name="email" value="{{ old('email') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rolle <span class="text-red-600">*</span></label>
                <select aria-label="Rolle" name="role" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @foreach(\App\Models\User::ROLE_LABELS as $val => $label)
                        <option value="{{ $val }}" {{ old('role') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($gruppen->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Trainingsgruppe</label>
                <select aria-label="Trainingsgruppe" name="group_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">– keine Zuordnung –</option>
                    @foreach($gruppen as $gruppe)
                        <option value="{{ $gruppe->id }}" {{ old('group_id') == $gruppe->id ? 'selected' : '' }}>{{ $gruppe->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    Ohne Gruppe erscheint das Konto nur so lange in deiner Liste, wie du es selbst angelegt hast.
                </p>
                @error('group_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geburtsdatum</label>
                    <input aria-label="Geburtsdatum" type="date" name="birth_date" value="{{ old('birth_date') }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input aria-label="Telefon" type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            {{-- Der Portal-Zugang entsteht nicht hier: Ein Administrator gibt ihn
                 frei und verschickt die Willkommensmail mit dem Einrichtungslink. --}}
            <p class="text-xs text-gray-500 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2">
                Das Konto wird angelegt, aber noch nicht eingeladen. Den Portal-Zugang gibt ein Administrator
                frei – er verschickt die Willkommensmail, mit der sich das Mitglied ein eigenes Passwort setzt.
            </p>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Anlegen
                </button>
                <a href="{{ route('users-lite.index') }}" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection

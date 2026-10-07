@extends('layouts.app')
@section('title', 'Benutzer bearbeiten')
@section('page-title', 'Benutzer bearbeiten')

@section('content')
<div class="max-w-xl mt-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
        <form method="POST" action="{{ route('users-lite.update', $user) }}" class="space-y-5">
            @csrf @method('PUT')

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname <span class="text-red-600">*</span></label>
                    <input aria-label="Vorname" type="text" name="firstname" value="{{ old('firstname', $user->firstname) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname <span class="text-red-600">*</span></label>
                    <input aria-label="Nachname" type="text" name="lastname" value="{{ old('lastname', $user->lastname) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                <input aria-label="E-Mail" type="email" name="email" value="{{ old('email', $user->email) }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rolle</label>
                <select aria-label="Rolle" name="role" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @foreach(collect(\App\Models\User::ROLE_LABELS)->only(auth()->user()->assignableRoles()) as $val => $label)
                        <option value="{{ $val }}" {{ old('role', $user->role) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geburtsdatum</label>
                    <input aria-label="Geburtsdatum" type="date" name="birth_date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input aria-label="Telefon" type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div>
                {{-- Nullwert: Ein leeres Kaestchen schickt nichts mit, ohne ihn
                     liesse sich der Haken nie wieder entfernen. --}}
                <input type="hidden" name="active" value="0">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', $user->active ? '1' : '0') == '1' ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-primary border-gray-300">
                    <span class="text-sm font-medium text-gray-700">Aktives Mitglied</span>
                </label>
                <p class="text-xs text-gray-400 mt-1 ml-6">Mitgliedschaft im Verein.</p>
            </div>

            {{-- Der Portal-Zugang gehoert nicht hierher: Er entsteht durch die
                 Willkommensmail eines Administrators und die Reaktion darauf. --}}
            @php($portal = $user->portalStatus())
            <div class="border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Portal-Zugang</p>
                <p class="text-sm text-gray-600 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $portal['tone'] }}">{{ $portal['label'] }}</span>
                    @if($user->isPortalActivated())
                        <span class="text-gray-500">aktiviert am {{ $user->portal_activated_at->deBerlin('d.m.Y') }}</span>
                    @else
                        <span class="text-gray-500">Die Einladung verschickt ein Administrator.</span>
                    @endif
                </p>
            </div>

            {{-- Kein Passwortfeld: Ein Trainer soll kein Passwort fuer jemand
                 anderen festlegen. Wer seines vergessen hat, fordert es sich auf
                 der Anmeldeseite selbst neu an; für alles Weitere gibt es die
                 Benutzerverwaltung der Administratoren. --}}
            <div class="border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Passwort</p>
                <p class="text-sm text-gray-600">
                    Passwörter werden hier nicht vergeben. Wer sein Passwort vergessen hat, nutzt auf der
                    Anmeldeseite <strong>„Passwort vergessen?"</strong> und bekommt einen Link per E-Mail.
                    Geht das nicht, hilft ein Administrator weiter.
                </p>
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Speichern
                </button>
                <a href="{{ route('users-lite.show', $user) }}" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection

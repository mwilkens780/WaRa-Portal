@extends('layouts.auth')
@section('title', $isSetup ? 'Passwort einrichten' : 'Neues Passwort setzen')

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2 text-center">
    {{ $isSetup ? 'Willkommen im WaRa-Portal' : 'Neues Passwort setzen' }}
</h2>
<p class="text-sm text-gray-500 text-center mb-6">
    {{ $isSetup
        ? 'Wähle dein persönliches Passwort. Damit meldest du dich künftig an.'
        : 'Wähle ein neues Passwort für deinen Zugang.' }}
</p>

<form method="POST" action="{{ $isSetup ? route('password.setup.store') : route('password.reset.store') }}" class="space-y-5">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-Mail-Adresse</label>
        <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required
               class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition
                      {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
        @error('email')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Neues Passwort</label>
        <input type="password" id="password" name="password" required autofocus autocomplete="new-password"
               class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition
                      {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
        <p class="text-xs text-gray-500 mt-1">Mindestens 10 Zeichen, mit Buchstaben und Ziffern.</p>
        @error('password')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Passwort wiederholen</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
    </div>

    <button type="submit"
            class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 px-4 rounded-lg transition-colors duration-150">
        {{ $isSetup ? 'Passwort speichern und loslegen' : 'Passwort speichern' }}
    </button>
</form>

<p class="text-center text-sm text-gray-500 mt-6">
    <a href="{{ route('login') }}" class="text-primary hover:underline">Zurück zur Anmeldung</a>
</p>
@endsection

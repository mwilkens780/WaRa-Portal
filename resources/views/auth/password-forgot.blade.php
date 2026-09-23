@extends('layouts.auth')
@section('title', 'Passwort vergessen')

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-2 text-center">Passwort vergessen</h2>
<p class="text-sm text-gray-500 text-center mb-6">
    Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link, mit dem du dir ein neues Passwort setzt.
</p>

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg mb-5">
        {{ session('success') }}
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}" class="space-y-5">
    @csrf

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-Mail-Adresse</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
               class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition
                      {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
        @error('email')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit"
            class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 px-4 rounded-lg transition-colors duration-150">
        Link anfordern
    </button>
</form>

<p class="text-center text-sm text-gray-500 mt-6">
    <a href="{{ route('login') }}" class="text-primary hover:underline">Zurück zur Anmeldung</a>
</p>
@endsection

@extends('layouts.auth')
@section('title', 'Anmelden')

@push('scripts')
<script>
window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
});
</script>
@endpush

@section('content')
<h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Anmelden</h2>

<form method="POST" action="{{ route('login.post') }}" class="space-y-5">
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

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
        <input type="password" id="password" name="password" required
               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition
                      {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
    </div>

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600">
            Angemeldet bleiben
        </label>
    </div>

    <button type="submit"
            class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 px-4 rounded-lg transition-colors duration-150">
        Anmelden
    </button>
</form>

<p class="text-center text-sm text-gray-500 mt-6">
    Bei Problemen wende dich an deinen Trainer oder Administrator.
</p>
@endsection

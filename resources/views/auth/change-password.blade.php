@extends('layouts.app')
@section('title', 'Passwort ändern')
@section('page-title', 'Passwort ändern')

@section('content')
<div class="max-w-lg mt-6">
    @if(auth()->user()->hasInitialPassword())
    <div class="flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-xl px-5 py-4 mb-5">
        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-amber-800">Passwort-Änderung erforderlich</p>
            <p class="text-xs text-amber-700 mt-0.5">Du meldest dich zum ersten Mal an oder dein Passwort wurde zurückgesetzt. Bitte wähle jetzt ein eigenes Passwort, um das Portal nutzen zu können.</p>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Aktuelles Passwort</label>
                <input type="password" name="current_password" required
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none {{ $errors->has('current_password') ? 'border-red-400' : '' }}">
                @error('current_password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Neues Passwort</label>
                <input type="password" name="password" required
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none {{ $errors->has('password') ? 'border-red-400' : '' }}">
                <p class="text-xs text-gray-500 mt-1">Mindestens 8 Zeichen, Buchstaben und Zahlen</p>
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Passwort bestätigen</label>
                <input type="password" name="password_confirmation" required
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Passwort ändern
                </button>
                @if(!auth()->user()->hasInitialPassword())
                <a href="{{ url()->previous() }}"
                   class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection

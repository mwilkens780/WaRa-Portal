@extends('layouts.app')
@section('title', 'Support')
@section('page-title', 'Support')

@section('content')
<div class="max-w-2xl mt-2">

    @if(session('error'))
        <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <svg class="w-5 h-5 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <div>
                <h2 class="text-base font-semibold text-gray-800">Support-Ticket erstellen</h2>
                <p class="text-xs text-gray-400 mt-0.5">Fehler melden oder Verbesserungsvorschläge einreichen</p>
            </div>
        </div>

        <form method="POST" action="{{ route('support.store') }}" class="px-6 py-5 space-y-5"
              x-data="{ type: '{{ old('type', 'bug') }}' }">
            @csrf

            {{-- Typ --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Art des Tickets</label>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-3.5 border rounded-xl cursor-pointer transition-colors"
                           :class="type === 'bug' ? 'border-red-300 bg-red-50/50' : 'border-gray-200 hover:bg-gray-50'">
                        <input type="radio" name="type" value="bug" x-model="type"
                               class="mt-0.5 text-red-500 border-gray-300 focus:ring-red-400">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Fehler melden</p>
                            <p class="text-xs text-gray-500 mt-0.5">Etwas funktioniert nicht wie erwartet</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3.5 border rounded-xl cursor-pointer transition-colors"
                           :class="type === 'enhancement' ? 'border-blue-300 bg-blue-50/50' : 'border-gray-200 hover:bg-gray-50'">
                        <input type="radio" name="type" value="enhancement" x-model="type"
                               class="mt-0.5 text-primary border-gray-300 focus:ring-blue-400">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Verbesserungsvorschlag</p>
                            <p class="text-xs text-gray-500 mt-0.5">Idee für eine neue Funktion oder Verbesserung</p>
                        </div>
                    </label>
                </div>
                @error('type')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Titel --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                       placeholder="Kurze, prägnante Beschreibung"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none {{ $errors->has('title') ? 'border-red-400' : '' }}">
                @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Beschreibung --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Beschreibung <span class="text-red-500">*</span>
                </label>
                <textarea name="description" rows="6" required maxlength="5000"
                          x-show="type === 'bug'"
                          placeholder="Was ist passiert? Was hast du erwartet? Wie lässt sich der Fehler reproduzieren?"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none resize-y {{ $errors->has('description') ? 'border-red-400' : '' }}">{{ old('description') }}</textarea>
                <textarea name="description" rows="6" required maxlength="5000"
                          x-show="type === 'enhancement'" x-cloak
                          placeholder="Was soll verbessert werden? Warum wäre das nützlich? Wie könnte es aussehen?"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none resize-y {{ $errors->has('description') ? 'border-red-400' : '' }}">{{ old('description') }}</textarea>
                @error('description')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Benachrichtigung bei Lösung --}}
            @if(auth()->user()->email)
            <div class="flex items-start gap-3 p-3.5 bg-blue-50/40 border border-blue-100 rounded-xl">
                <input type="hidden" name="notify_on_close" value="0">
                <input type="checkbox" name="notify_on_close" value="1" id="notify_on_close"
                       {{ old('notify_on_close') ? 'checked' : '' }}
                       class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary">
                <label for="notify_on_close" class="cursor-pointer">
                    <p class="text-sm font-medium text-gray-700">Per E-Mail benachrichtigen, wenn das Ticket gelöst wurde</p>
                    <p class="text-xs text-gray-500 mt-0.5">An: {{ auth()->user()->email }}</p>
                </label>
            </div>
            @else
            <p class="text-xs text-gray-400">
                Kein E-Mail-Adresse in deinem Profil — Bestätigung und Lösungsbenachrichtigung nicht möglich.
            </p>
            @endif

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                    Ticket einreichen
                </button>
                <p class="text-xs text-gray-400">Das Ticket wird als GitHub-Issue angelegt.</p>
            </div>
        </form>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'DSGVO-Anfrage erfassen')
@section('page-title', 'DSGVO-Anfrage erfassen')

@section('content')
<div class="mt-2 max-w-2xl">
    <a href="{{ route('admin.dsgvo.index') }}" class="text-sm text-gray-500 hover:text-gray-700 mb-4 inline-block">← Zurück zur Übersicht</a>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <p class="text-sm text-gray-500 mb-5">
            Erfassen Sie eingehende DSGVO-Anfragen (z.B. per E-Mail oder Brief) zur Fristenkontrolle und Dokumentation.
        </p>

        <form action="{{ route('admin.dsgvo.store') }}" method="POST" class="space-y-5">
            @csrf

            {{-- Portal-Nutzer (optional) --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Portal-Nutzer (optional)</label>
                <select name="user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                    <option value="">— kein Portal-Konto zugeordnet —</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->lastname }}, {{ $u->firstname }} ({{ $u->email ?? 'keine E-Mail' }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Wenn zugeordnet, kann eine vollständige Datenauskunft exportiert werden.</p>
                @error('user_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Antragsteller --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name der anfragenden Person <span class="text-red-500">*</span></label>
                    <input type="text" name="requester_name" value="{{ old('requester_name') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                           required>
                    @error('requester_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail der anfragenden Person</label>
                    <input type="email" name="requester_email" value="{{ old('requester_email') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                    @error('requester_email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Anfragetyp --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Art der Anfrage <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(\App\Models\DsgvoRequest::$types as $key => $label)
                    <label class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50 transition-colors has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                        <input type="radio" name="type" value="{{ $key }}" {{ old('type') === $key ? 'checked' : '' }} required class="text-primary">
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
                @error('type')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Beschreibung --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung / Anmerkungen</label>
                <textarea name="description" rows="4"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                          placeholder="Inhalt der Anfrage, Eingangsweg (E-Mail, Post, ...) etc.">{{ old('description') }}</textarea>
                @error('description')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-primary text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
                    Anfrage erfassen
                </button>
                <a href="{{ route('admin.dsgvo.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>

    <p class="mt-4 text-xs text-gray-400">
        Die Frist von 30 Tagen beginnt ab dem heutigen Datum (Art. 12 Abs. 3 DSGVO).
        Sie können das tatsächliche Eingangsdatum in den Anmerkungen notieren.
    </p>
</div>
@endsection

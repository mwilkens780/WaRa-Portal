@extends('layouts.app')
@section('title', 'Neuer Benutzer')
@section('page-title', 'Neuer Benutzer')

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5"
              x-data="{ selectedRoles: @json(old('user_roles', [])) }">
            @csrf

            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname <span class="text-red-500">*</span></label>
                    <input type="text" name="firstname" value="{{ old('firstname') }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('firstname') ? 'border-red-400' : '' }}">
                    @error('firstname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname <span class="text-red-500">*</span></label>
                    <input type="text" name="lastname" value="{{ old('lastname') }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('lastname') ? 'border-red-400' : '' }}">
                    @error('lastname')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geburtsdatum</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date') }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            {{-- Rollen --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Rollen</label>
                <div class="flex flex-wrap gap-3">
                    @foreach(\App\Models\User::ROLE_LABELS as $value => $label)
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox"
                                   name="user_roles[]"
                                   value="{{ $value }}"
                                   x-model="selectedRoles"
                                   class="rounded border-gray-300 text-primary">
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-1.5">Portal-Zugang richtet sich nach der ranghöchsten zugewiesenen Rolle.</p>
                @error('user_roles')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Kinder-Zuweisung nur für Elternteile --}}
            <div x-show="selectedRoles.includes('elternteil')" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">Kinder zuweisen</label>
                <div class="border border-gray-200 rounded-lg divide-y max-h-48 overflow-y-auto">
                    @foreach($swimmers as $swimmer)
                        <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="children[]" value="{{ $swimmer->id }}"
                                   {{ in_array($swimmer->id, old('children', [])) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-primary">
                            <span class="text-sm">{{ $swimmer->name }}
                                {{ $swimmer->birth_date ? '(' . $swimmer->birth_date->format('d.m.Y') . ')' : '' }}
                            </span>
                        </label>
                    @endforeach
                    @if($swimmers->isEmpty())
                        <p class="text-sm text-gray-400 px-4 py-3">Noch keine Schwimmer vorhanden.</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="active" value="1"
                           {{ old('active', '1') == '1' ? 'checked' : '' }}
                           class="w-5 h-5 rounded border-gray-300 text-primary">
                    <span class="text-sm font-medium text-gray-700">Konto sofort aktivieren</span>
                </label>
            </div>

            {{-- Hinweis Initialpasswort --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-700">
                Ein Initialpasswort wird automatisch generiert und ist nach der Anlage in der Benutzerverwaltung sichtbar, bis der Benutzer sich erstmalig anmeldet und es ändert.
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Benutzer anlegen
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

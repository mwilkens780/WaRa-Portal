@extends('layouts.app')
@section('title', 'Mein Profil')
@section('page-title', 'Mein Profil')

@section('content')
<div class="max-w-2xl mt-2 space-y-5">

    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        <ul class="space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')

        {{-- Persönliche Daten --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <svg class="w-5 h-5 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <h2 class="text-base font-semibold text-gray-800">Persönliche Daten</h2>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vorname <span class="text-red-500">*</span></label>
                        <input type="text" name="firstname" value="{{ old('firstname', $user->firstname) }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nachname <span class="text-red-500">*</span></label>
                        <input type="text" name="lastname" value="{{ old('lastname', $user->lastname) }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail (primär) <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail (alternativ)</label>
                        <input type="email" name="email2" value="{{ old('email2', $user->email2) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobil</label>
                        <input type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Straße & Hausnummer</label>
                    <input type="text" name="street" value="{{ old('street', $user->street) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PLZ</label>
                        <input type="text" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                        <input type="text" name="city" value="{{ old('city', $user->city) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                    </div>
                </div>

                <div class="pt-1 text-sm text-gray-500">
                    Mitglied seit: <span class="font-medium text-gray-700">{{ $user->member_since ? $user->member_since->format('d.m.Y') : '–' }}</span>
                    &nbsp;|&nbsp; Geburtsdatum: <span class="font-medium text-gray-700">{{ $user->birth_date ? $user->birth_date->format('d.m.Y') : '–' }}</span>
                    &nbsp;|&nbsp; Rolle: <span class="font-medium text-gray-700">{{ $user->role_label }}</span>
                </div>
            </div>
        </div>

        {{-- Einwilligungen (Opt-in) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <svg class="w-5 h-5 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <div>
                    <h2 class="text-base font-semibold text-gray-800">Einwilligungen</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Du kannst diese Einwilligungen jederzeit widerrufen.</p>
                </div>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div x-data="{ on: {{ old('opt_nutrition', $user->opt_nutrition) ? 'true' : 'false' }} }"
                     class="flex items-start gap-4 p-4 border rounded-xl transition-colors"
                     :class="on ? 'border-green-300 bg-green-50/40' : 'border-gray-200'">
                    <div class="flex-shrink-0 pt-0.5">
                        <input type="hidden" name="opt_nutrition" :value="on ? '1' : '0'">
                        <div class="relative w-10 h-5 rounded-full transition-colors cursor-pointer"
                             :class="on ? 'bg-green-500' : 'bg-gray-300'"
                             @click="on = !on">
                            <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                 :class="on ? 'translate-x-5' : 'translate-x-0'"></div>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Ernährungsberatung</p>
                        <p class="text-xs text-gray-500 mt-0.5">Ich stimme zu, dass meine Daten im Rahmen der Ernährungsberatung erfasst und Messwerte gespeichert werden dürfen.</p>
                    </div>
                </div>

                <div x-data="{ on: {{ old('opt_sports_medicine', $user->opt_sports_medicine) ? 'true' : 'false' }} }"
                     class="flex items-start gap-4 p-4 border rounded-xl transition-colors"
                     :class="on ? 'border-blue-300 bg-blue-50/40' : 'border-gray-200'">
                    <div class="flex-shrink-0 pt-0.5">
                        <input type="hidden" name="opt_sports_medicine" :value="on ? '1' : '0'">
                        <div class="relative w-10 h-5 rounded-full transition-colors cursor-pointer"
                             :class="on ? 'bg-primary' : 'bg-gray-300'"
                             @click="on = !on">
                            <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                 :class="on ? 'translate-x-5' : 'translate-x-0'"></div>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Sportmedizinische Untersuchung</p>
                        <p class="text-xs text-gray-500 mt-0.5">Ich stimme zu, dass meine sportmedizinischen Untersuchungsergebnisse im Rahmen der Leistungssporteignung erfasst werden dürfen.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Profil speichern
            </button>
            <a href="{{ route('password.change') }}"
               class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                Passwort ändern
            </a>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Neue Trainingseinheit')
@section('page-title', 'Neue Trainingseinheit')

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('trainer.sessions.store') }}"
              enctype="multipart/form-data" class="space-y-5"
              x-data="{
                  recurrence: '{{ old('recurrence_type', 'none') }}',
                  showRecurrence: false,
                  init() { this.showRecurrence = this.recurrence !== 'none'; }
              }">
            @csrf

            <div class="grid md:grid-cols-2 gap-5">
                {{-- Titel --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           placeholder="z.B. Techniktraining Freistil"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('title') ? 'border-red-400' : '' }}">
                    @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Datum --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum <span class="text-red-500">*</span></label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('date')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Typ --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ <span class="text-red-500">*</span></label>
                    <select name="type" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach([
                            'technik'        => 'Technik',
                            'kondition'      => 'Kondition',
                            'ausdauer'       => 'Ausdauer',
                            'wettkampf'      => 'Wettkampfvorbereitung',
                            'krafttraining'  => 'Krafttraining',
                            'physio'         => 'Physiotherapie',
                            'mentaltraining' => 'Mentaltraining',
                            'sonstiges'      => 'Sonstiges',
                        ] as $val => $label)
                            <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Zeiten --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beginn <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" value="{{ old('start_time', '07:00') }}" required step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('start_time')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ende</label>
                    <input type="time" name="end_time" value="{{ old('end_time', '08:30') }}" step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @error('end_time')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Ort --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainingsort <span class="text-red-500">*</span></label>
                    <input type="text" name="location" value="{{ old('location', 'Stadtbad Norderstedt') }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                {{-- Wiederholung --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wiederholung <span class="text-red-500">*</span></label>
                    <select name="recurrence_type" x-model="recurrence"
                            @change="showRecurrence = recurrence !== 'none'"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="none">Einmalig</option>
                        <option value="weekly">Wöchentlich</option>
                        <option value="biweekly">Zweiwöchentlich</option>
                        <option value="monthly">Monatlich</option>
                    </select>
                </div>

                <div class="md:col-span-2" x-show="showRecurrence" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wiederholung bis <span class="text-red-500">*</span></label>
                    <input type="date" name="recurrence_until" value="{{ old('recurrence_until') }}"
                           :required="showRecurrence"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none {{ $errors->has('recurrence_until') ? 'border-red-400' : '' }}">
                    @error('recurrence_until')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Notizen --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notizen / Trainingsplan</label>
                    <textarea name="notes" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                              placeholder="Beschreibung des Trainings, besondere Übungen, Ziele...">{{ old('notes') }}</textarea>
                </div>

                {{-- Teamplan Anhang --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teamplan (Anhang)</label>
                    <input type="file" name="team_plan" accept=".pdf,.doc,.docx,.jpg,.png"
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-1">PDF, Word, JPG oder PNG – max. 5 MB</p>
                    @error('team_plan')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Einheit anlegen
                </button>
                <a href="{{ route('trainer.sessions.index') }}"
                   class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

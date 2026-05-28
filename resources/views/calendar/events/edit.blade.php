@extends('layouts.app')
@section('title', 'Termin bearbeiten')
@section('page-title', 'Termin bearbeiten')

@section('content')
<div class="mt-2 max-w-xl space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('calendar.events.update', $calendarEvent) }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $calendarEvent->title) }}" required maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Art <span class="text-red-500">*</span></label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    @foreach(\App\Models\CalendarEvent::TYPES as $key => $info)
                        <option value="{{ $key }}" {{ old('type', $calendarEvent->type) === $key ? 'selected' : '' }}>{{ $info['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum von <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" value="{{ old('start_date', $calendarEvent->start_date->format('Y-m-d')) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum bis</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $calendarEvent->end_date?->format('Y-m-d')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uhrzeit von</label>
                    <input type="time" name="start_time" value="{{ old('start_time', $calendarEvent->start_time ? substr($calendarEvent->start_time, 0, 5) : '') }}" step="900"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uhrzeit bis</label>
                    <input type="time" name="end_time" value="{{ old('end_time', $calendarEvent->end_time ? substr($calendarEvent->end_time, 0, 5) : '') }}" step="900"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Saison</label>
                <select name="season_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">– keine Saison –</option>
                    @foreach($seasons as $s)
                        <option value="{{ $s->id }}" {{ old('season_id', $calendarEvent->season_id) == $s->id ? 'selected' : '' }}>Saison {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="description" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ old('description', $calendarEvent->description) }}</textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                    Speichern
                </button>
                <a href="{{ route('calendar.index') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-red-100 p-5">
        <form method="POST" action="{{ route('calendar.events.destroy', $calendarEvent) }}"
              onsubmit="return confirm('Termin löschen?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:text-red-800">Termin löschen</button>
        </form>
    </div>
</div>
@endsection

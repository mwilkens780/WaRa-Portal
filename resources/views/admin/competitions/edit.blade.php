@extends('layouts.app')
@section('title', 'Wettkampf bearbeiten')
@section('page-title', 'Wettkampf bearbeiten')

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('admin.competitions.update', $competition) }}" class="space-y-5">
            @csrf @method('PUT')

            <div class="grid md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name des Wettkampfs <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $competition->name) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort <span class="text-red-500">*</span></label>
                    <input type="text" name="location" value="{{ old('location', $competition->location) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Veranstalter</label>
                    <input type="text" name="organizer" value="{{ old('organizer', $competition->organizer) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum (Beginn) <span class="text-red-500">*</span></label>
                    <input type="date" name="date" value="{{ old('date', $competition->date->format('Y-m-d')) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum (Ende)</label>
                    <input type="date" name="date_end" value="{{ old('date_end', $competition->date_end?->format('Y-m-d')) }}"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach(\App\Models\Competition::TYPE_LABELS as $value => $label)
                            <option value="{{ $value }}" {{ old('type', $competition->type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bahnlänge</label>
                    <select name="course" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">– nicht angegeben –</option>
                        <option value="SCM" {{ old('course', $competition->course) === 'SCM' ? 'selected' : '' }}>25 m (Kurzbahn)</option>
                        <option value="LCM" {{ old('course', $competition->course) === 'LCM' ? 'selected' : '' }}>50 m (Langbahn)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ old('description', $competition->description) }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Speichern
                </button>
                <a href="{{ route('admin.competitions.show', $competition) }}"
                   class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection

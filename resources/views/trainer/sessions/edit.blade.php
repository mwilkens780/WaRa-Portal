@extends('layouts.app')
@section('title', 'Trainingseinheit bearbeiten')
@section('page-title', 'Trainingseinheit bearbeiten')

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('trainer.sessions.update', $session) }}" class="space-y-5">
            @csrf @method('PUT')

            <div class="grid md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $session->title) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Datum <span class="text-red-500">*</span></label>
                    <input type="date" name="date" value="{{ old('date', $session->date->format('Y-m-d')) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach(['technik' => 'Technik', 'kondition' => 'Kondition', 'ausdauer' => 'Ausdauer', 'wettkampf' => 'Wettkampfvorbereitung', 'sonstiges' => 'Sonstiges'] as $val => $label)
                            <option value="{{ $val }}" {{ old('type', $session->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beginn <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" value="{{ old('start_time', $session->start_time) }}" required step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ende</label>
                    <input type="time" name="end_time" value="{{ old('end_time', $session->end_time) }}" step="900"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ort <span class="text-red-500">*</span></label>
                    <input type="text" name="location" value="{{ old('location', $session->location) }}" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                    <textarea name="notes" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ old('notes', $session->notes) }}</textarea>
                </div>
                @if($groups->isNotEmpty())
                @php $assignedGroupIds = old('groups', $session->trainingGroups->pluck('id')->map(fn($id) => (string)$id)->toArray()); @endphp
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trainingsgruppen</label>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($groups as $group)
                            @php $gColors = $group->colorDots; @endphp
                            <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg hover:bg-gray-50 border border-gray-100">
                                <input type="checkbox" name="groups[]" value="{{ $group->id }}"
                                       {{ in_array((string)$group->id, $assignedGroupIds) ? 'checked' : '' }}
                                       class="w-4 h-4 text-primary rounded border-gray-300">
                                <span class="w-2.5 h-2.5 rounded-full {{ $gColors['dot'] }} flex-shrink-0"></span>
                                <span class="text-gray-700">{{ $group->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainer</label>
                    <select name="trainer_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach($allTrainers as $t)
                            <option value="{{ $t->id }}" {{ old('trainer_id', $session->trainer_id) == $t->id ? 'selected' : '' }}>
                                {{ $t->lastname }}, {{ $t->firstname }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Speichern
                </button>
                <a href="{{ route('trainer.sessions.show', $session) }}"
                   class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
@endsection

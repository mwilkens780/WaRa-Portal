@extends('layouts.app')
@section('title', 'Trainingseinheiten')
@section('page-title', 'Trainingseinheiten')

@section('content')
<div class="mt-2 space-y-4">
    <div class="flex justify-end">
        <a href="{{ route('trainer.sessions.create') }}"
           class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Neue Einheit
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Datum</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Einheit</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Typ</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden lg:table-cell">Trainer</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Uhrzeit</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($sessions as $session)
                        @php
                            $typeColors = [
                                'kondition' => 'bg-orange-100 text-orange-700',
                                'technik' => 'bg-blue-100 text-blue-700',
                                'wettkampf' => 'bg-red-100 text-red-700',
                                'ausdauer' => 'bg-green-100 text-green-700',
                                'sonstiges' => 'bg-gray-100 text-gray-600',
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 text-gray-600 whitespace-nowrap">
                                <div>{{ $session->date->format('d.m.Y') }}</div>
                                <div class="text-xs text-gray-400">{{ $session->date->isoFormat('dddd') }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <a href="{{ route('trainer.sessions.show', $session) }}"
                                   class="font-medium text-primary hover:underline">{{ $session->title }}</a>
                                <p class="text-xs text-gray-400">{{ $session->location }}</p>
                            </td>
                            <td class="px-5 py-3 hidden md:table-cell">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$session->type] ?? 'bg-gray-100' }}">
                                    {{ $session->type_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 hidden lg:table-cell">{{ $session->trainer->name }}</td>
                            <td class="px-5 py-3 text-gray-500 hidden md:table-cell whitespace-nowrap">
                                {{ $session->start_time }}
                                @if($session->end_time) – {{ $session->end_time }} @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    <a href="{{ route('trainer.sessions.show', $session) }}"
                                       class="text-primary hover:text-primary-dark text-xs font-medium">Details</a>
                                    <a href="{{ route('trainer.sessions.edit', $session) }}"
                                       class="text-gray-500 hover:text-gray-700 text-xs">Bearbeiten</a>
                                    <form method="POST" action="{{ route('trainer.sessions.destroy', $session) }}"
                                          onsubmit="return confirm('Trainingseinheit löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Löschen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">Noch keine Trainingseinheiten vorhanden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sessions->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $sessions->links() }}</div>
        @endif
    </div>
</div>
@endsection

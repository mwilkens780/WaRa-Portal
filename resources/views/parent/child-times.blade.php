@extends('layouts.app')
@section('title', 'Zeiten von ' . $child->name)
@section('page-title', 'Zeiten: ' . $child->name)

@section('content')
<div class="mt-2">
    <div class="mb-4">
        <a href="{{ route('parent.dashboard') }}" class="text-sm text-primary hover:underline">← Zurück zur Übersicht</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Datum</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Training</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Disziplin</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Distanz</th>
                        <th class="text-right px-5 py-3 font-semibold text-gray-600">Zeit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($times as $time)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-500">{{ $time->trainingSession?->date->format('d.m.Y') ?? '–' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $time->trainingSession?->title ?? '–' }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $time->discipline_label }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $time->distance }} m</td>
                            <td class="px-5 py-3 text-right">
                                <span class="font-mono font-semibold text-primary">{{ $time->formatted_time }}</span>
                                @if($time->is_personal_best)
                                    <span class="ml-1 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-sans font-medium">PB</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Noch keine Zeiten erfasst.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($times->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $times->links() }}</div>
        @endif
    </div>
</div>
@endsection

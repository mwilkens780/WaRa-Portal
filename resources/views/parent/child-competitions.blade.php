@extends('layouts.app')
@section('title', 'Wettkämpfe von ' . $child->name)
@section('page-title', 'Wettkämpfe: ' . $child->name)

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
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Wettkampf</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Datum</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Disziplin</th>
                        <th class="text-right px-5 py-3 font-semibold text-gray-600">Zeit</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Platz</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($results as $result)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-800">{{ $result->competition->name }}</p>
                                <p class="text-xs text-gray-400">{{ $result->competition->location }}</p>
                            </td>
                            <td class="px-5 py-3 text-gray-500 hidden md:table-cell">{{ $result->competition->date->format('d.m.Y') }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $result->distance }}m {{ $result->discipline_label }}</td>
                            <td class="px-5 py-3 text-right">
                                <span class="font-mono font-bold text-primary">{{ $result->formatted_time }}</span>
                                @if($result->is_personal_best)
                                    <span class="ml-1 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-sans font-medium">PB</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                @if($result->placement)
                                    <span class="{{ $result->placement <= 3 ? 'font-bold text-amber-600' : '' }}">
                                        Platz {{ $result->placement }}
                                    </span>
                                @else –
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Noch keine Wettkampfergebnisse.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($results->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $results->links() }}</div>
        @endif
    </div>
</div>
@endsection

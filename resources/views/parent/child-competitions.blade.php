@extends('layouts.app')
@section('title', 'Wettkämpfe von ' . $child->name)
@section('page-title', 'Wettkämpfe: ' . $child->name)

@section('content')
<div class="mt-2 space-y-4">
    <div class="mb-2">
        <a href="{{ route('parent.dashboard') }}" class="text-sm text-primary hover:underline">← Zurück zur Übersicht</a>
    </div>

    @forelse($competitions as $comp)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-100">
                <div>
                    <p class="font-semibold text-gray-800 text-sm">{{ $comp->name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $comp->date_range }}
                        @if($comp->location) · {{ $comp->location }} @endif
                    </p>
                </div>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">{{ $comp->type_label }}</span>
            </div>

            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-50">
                    @foreach($comp->processedResults as $swim)
                        <tr class="hover:bg-gray-50 {{ $swim->is_dns ? 'opacity-60' : '' }}">
                            <td class="px-5 py-2.5 text-gray-700 w-1/3">
                                {{ $swim->distance }} m {{ $swim->discipline_label }}
                                @if($swim->is_final)
                                    <span class="ml-1 text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-medium">Finale</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                @if(!$swim->is_dns)
                                    <span class="font-mono font-bold text-primary">{{ $swim->formatted_time }}</span>
                                    @if($swim->is_personal_best)
                                        <span class="ml-1.5 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-sans font-medium">PB</span>
                                    @endif
                                @elseif($swim->notes)
                                    <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-semibold tracking-wide">{{ $swim->notes }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-gray-500 text-xs hidden sm:table-cell">
                                @if(!empty($swim->placements) && !$swim->is_dns)
                                    @foreach($swim->placements as $p)
                                        <span class="{{ $p->placement <= 3 ? 'font-bold text-amber-600' : '' }}">
                                            @if($p->age_group)<span class="text-gray-400 font-normal">{{ $p->age_group }}: </span>@endif
                                            Platz {{ $p->placement }}
                                        </span>{{ !$loop->last ? ' · ' : '' }}
                                    @endforeach
                                @else –
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Noch keine Wettkampfergebnisse vorhanden.
        </div>
    @endforelse

    @if($competitions->hasPages())
        <div class="flex items-center justify-center gap-1 text-sm py-2">
            @if($competitions->onFirstPage())
                <span class="px-3 py-1.5 rounded-lg text-gray-300 border border-gray-100">‹</span>
            @else
                <a href="{{ $competitions->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50">‹</a>
            @endif
            @foreach($competitions->getUrlRange(max(1, $competitions->currentPage()-2), min($competitions->lastPage(), $competitions->currentPage()+2)) as $page => $url)
                @if($page == $competitions->currentPage())
                    <span class="px-3 py-1.5 rounded-lg bg-primary text-white font-medium">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50">{{ $page }}</a>
                @endif
            @endforeach
            @if($competitions->hasMorePages())
                <a href="{{ $competitions->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50">›</a>
            @else
                <span class="px-3 py-1.5 rounded-lg text-gray-300 border border-gray-100">›</span>
            @endif
        </div>
    @endif
</div>
@endsection

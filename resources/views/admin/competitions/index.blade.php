@extends('layouts.app')
@section('title', 'Wettkämpfe')
@section('page-title', 'Wettkämpfe')

@section('content')
<div class="mt-2 space-y-4">
    <div class="flex justify-end">
        <a href="{{ route('admin.competitions.create') }}"
           class="flex items-center gap-2 bg-accent text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-accent-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Neuer Wettkampf
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Wettkampf</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden md:table-cell">Ort</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Datum</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600 hidden lg:table-cell">Typ</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Ergebnisse</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($competitions as $comp)
                        @php
                            $typeColors = [
                                'vereinsintern'  => 'bg-gray-100 text-gray-600',
                                'regional'       => 'bg-blue-100 text-blue-700',
                                'national'       => 'bg-green-100 text-green-700',
                                'international'  => 'bg-purple-100 text-purple-700',
                                'meisterschaften'=> 'bg-amber-100 text-amber-700',
                                'einladung'      => 'bg-teal-100 text-teal-700',
                            ];
                            $isPast = $comp->date->isPast();
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.competitions.show', $comp) }}"
                                   class="font-medium text-primary hover:underline">{{ $comp->name }}</a>
                                @if(!$isPast)
                                    <span class="ml-2 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">bevorstehend</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-500 hidden md:table-cell">{{ $comp->location }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $comp->date_range }}</td>
                            <td class="px-5 py-3 hidden lg:table-cell">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$comp->type] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $comp->type_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $comp->results_count }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3 justify-end">
                                    <a href="{{ route('admin.competitions.show', $comp) }}"
                                       class="text-primary hover:text-primary-dark text-xs font-medium">Details</a>
                                    <a href="{{ route('admin.competitions.edit', $comp) }}"
                                       class="text-gray-500 hover:text-gray-700 text-xs">Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.competitions.destroy', $comp) }}"
                                          onsubmit="return confirm('Wettkampf und alle Ergebnisse löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Löschen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">Noch keine Wettkämpfe angelegt.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($competitions->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">{{ $competitions->links() }}</div>
        @endif
    </div>
</div>
@endsection

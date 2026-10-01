@extends('layouts.app')
@section('title', 'Wettkämpfe')
@section('page-title', 'Wettkämpfe')

@section('content')
<div class="mt-2 space-y-4">

    {{-- Action buttons --}}
    @if(in_array(auth()->user()->role, ['admin', 'trainer']))
    <div class="flex flex-wrap justify-end gap-2">
        {{-- Wettkampf samt Ergebnissen aus einer DSV-Ergebnisdatei anlegen (Trainer + Admin) --}}
        <a href="{{ route('trainer.dsv-import.index') }}"
           class="flex items-center gap-2 bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            DSV-Ergebnisdatei
        </a>
        @if(auth()->user()->role === 'admin')
        <a href="{{ route('admin.competitions.webclub-import.form') }}"
           class="flex items-center gap-2 bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Veranstaltungen aus WebClub
        </a>
        <a href="{{ route('admin.competitions.create') }}"
           class="flex items-center gap-2 bg-accent text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-accent-dark transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Neuer Wettkampf
        </a>
        @endif
    </div>
    @endif

    {{-- Filter form --}}
    <form method="GET" action="{{ route('admin.competitions.index') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-36">
                <label class="block text-xs font-medium text-gray-500 mb-1">Ort</label>
                <input aria-label="Ort" type="text" name="ort" value="{{ $filters['ort'] ?? '' }}"
                       placeholder="Ortsname…"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/40">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Von</label>
                <input aria-label="Von" type="date" name="von" value="{{ $filters['von'] ?? '' }}"
                       class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/40">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Bis</label>
                <input aria-label="Bis" type="date" name="bis" value="{{ $filters['bis'] ?? '' }}"
                       class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/40">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Typ</label>
                <select aria-label="Typ" name="typ" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/40">
                    <option value="">Alle Typen</option>
                    @foreach(\App\Models\Competition::TYPE_LABELS as $val => $label)
                        <option value="{{ $val }}" @selected(($filters['typ'] ?? '') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                Filtern
            </button>
            @if(array_filter($filters ?? []))
                <a href="{{ route('admin.competitions.index') }}"
                   class="text-sm text-gray-600 hover:text-gray-600 py-2">Zurücksetzen</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <x-ui.table :card="false" caption="Wettkämpfe" stack
            :columns="['Wettkampf', ['label' => 'Ort', 'hide' => 'md'], 'Datum', ['label' => 'Typ', 'hide' => 'lg'], 'Teilnehmer / Meldungen', ['label' => 'Aktionen', 'sr' => true]]">
                    @forelse($competitions as $comp)
                        @php
                            $typeColors = [
                                'vereinsintern'  => 'bg-gray-100 text-gray-600',
                                'regional'       => 'bg-blue-100 text-blue-700',
                                'national'       => 'bg-green-100 text-green-700',
                                'international'  => 'bg-purple-100 text-purple-700',
                                'meisterschaften'=> 'bg-amber-100 text-amber-700',
                                'einladung'      => 'bg-teal-100 text-teal-700',
                                'nop'            => 'bg-rose-100 text-rose-700',
                                'dms'            => 'bg-orange-100 text-orange-700',
                                'shsv'           => 'bg-cyan-100 text-cyan-700',
                            ];
                            $isPast = $comp->date->isPast();
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <x-ui.td label="Wettkampf">
                                <a href="{{ route('admin.competitions.show', $comp) }}"
                                   class="font-medium text-primary hover:underline">{{ $comp->name }}</a>
                                @if(!$isPast)
                                    <span class="ml-2 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">bevorstehend</span>
                                @endif
                            </x-ui.td>
                            <x-ui.td label="Ort" hide="md" muted>{{ $comp->location }}</x-ui.td>
                            <x-ui.td label="Datum" muted>{{ $comp->date_range }}</x-ui.td>
                            <x-ui.td label="Typ" hide="lg">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$comp->type] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $comp->type_label }}
                                </span>
                            </x-ui.td>
                            <x-ui.td label="Teilnehmer / Meldungen">
                                <div class="flex flex-wrap gap-1.5">
                                    @if($comp->participants_count > 0)
                                        <span class="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-medium"
                                              title="{{ $comp->results_count }} Einzelergebnisse">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                                            {{ $comp->participants_count }} Teiln.
                                        </span>
                                    @endif
                                    @if($comp->entries_count > 0)
                                        <span class="inline-flex items-center gap-1 text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-medium"
                                              title="Gemeldete Starts">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                            {{ $comp->entries_count }} Meld.
                                        </span>
                                    @endif
                                    @if($comp->participants_count == 0 && $comp->entries_count == 0)
                                        <span class="text-xs text-gray-600">–</span>
                                    @endif
                                </div>
                            </x-ui.td>
                            <x-ui.td>
                                <div class="flex items-center gap-3 justify-end">
                                    <a href="{{ route('admin.competitions.show', $comp) }}"
                                       class="text-primary hover:text-primary-dark text-xs font-medium">Details</a>
                                    @if(auth()->user()->role === 'admin')
                                        <a href="{{ route('admin.competitions.edit', $comp) }}"
                                           class="text-gray-500 hover:text-gray-700 text-xs">Bearbeiten</a>
                                        <form method="POST" action="{{ route('admin.competitions.destroy', $comp) }}"
                                              data-confirm="Wettkampf und alle Ergebnisse löschen?" data-confirm-label="Löschen" data-confirm-danger>
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-700 text-xs">Löschen</button>
                                        </form>
                                    @endif
                                </div>
                            </x-ui.td>
                        </tr>
                    @empty
                        <tr><x-ui.td align="center" muted colspan="6">Keine Wettkämpfe gefunden.</x-ui.td></tr>
                    @endforelse
                </x-ui.table>
        @if($competitions->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between gap-4 text-sm">
                <span class="text-gray-600">{{ $competitions->total() }} Einträge</span>
                <div class="flex items-center gap-1">
                    @if($competitions->onFirstPage())
                        <span class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-100">‹</span>
                    @else
                        <a href="{{ $competitions->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">‹</a>
                    @endif

                    @foreach($competitions->getUrlRange(max(1, $competitions->currentPage() - 2), min($competitions->lastPage(), $competitions->currentPage() + 2)) as $page => $url)
                        @if($page == $competitions->currentPage())
                            <span class="px-3 py-1.5 rounded-lg bg-primary text-white font-medium">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if($competitions->hasMorePages())
                        <a href="{{ $competitions->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">›</a>
                    @else
                        <span class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-100">›</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

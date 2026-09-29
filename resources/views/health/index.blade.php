@extends('layouts.app')
@section('title', 'Gesundheitsdaten')
@section('page-title', 'Gesundheitsdaten')

@section('content')
@php $authUser = auth()->user(); @endphp
<div class="mt-2 space-y-4">


    @if(isset($swimmers))
        {{-- Trainer / Admin: list of swimmers who opted in --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <svg class="w-5 h-5 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <div>
                    <h2 class="text-base font-semibold text-gray-800">Schwimmer mit freigegebenen Gesundheitsdaten</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Nur Schwimmer, die mindestens eine Einwilligung gegeben haben</p>
                </div>
            </div>

            @if($swimmers->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-gray-400">Keine Schwimmer mit freigegebenen Gesundheitsdaten gefunden.</div>
            @else
            <div class="divide-y divide-gray-50">
                @foreach($swimmers as $swimmer)
                <a href="{{ route('health.user', $swimmer) }}"
                   class="flex items-center justify-between px-6 py-3.5 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary flex-shrink-0">
                            {{ strtoupper(substr($swimmer->firstname ?: $swimmer->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $swimmer->name }}</p>
                            <div class="flex gap-1.5 mt-0.5">
                                @if($swimmer->opt_nutrition)
                                <span class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-medium">Ernährung</span>
                                @endif
                                @if($swimmer->opt_sports_medicine)
                                <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-medium">Sportmedizin</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                @endforeach
            </div>
            @endif
        </div>

    @else
        {{-- Kinder (gesetzliche Vertretung) zuerst - Eltern haben selten eigene Dokumente --}}
        @foreach($wardDocuments as $entry)
            @include('health._documents', [
                'title'     => 'Gesundheitsdokumente: ' . $entry['child']->name,
                'subtitle'  => 'Dokumente, die das Betreuerteam für ' . $entry['child']->firstname . ' hochgeladen hat',
                'documents' => $entry['documents'],
            ])
        @endforeach

        {{-- Eigene Dokumente: fuer Schwimmer immer, fuer reine Eltern nur wenn vorhanden --}}
        @if($documents->isNotEmpty() || $wardDocuments->isEmpty())
            @include('health._documents', [
                'title'     => 'Meine Gesundheitsdokumente',
                'subtitle'  => 'Dokumente, die dein Betreuerteam für dich hochgeladen hat',
                'documents' => $documents,
            ])
        @endif

        @if($wardDocuments->isEmpty() && !$authUser->opt_nutrition && !$authUser->opt_sports_medicine)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3 rounded-xl">
            Du hast noch keine Einwilligungen gegeben.
            <a href="{{ route('profile.index') }}" class="underline font-medium">Profil öffnen</a>, um Einwilligungen zu verwalten.
        </div>
        @endif
    @endif
</div>
@endsection

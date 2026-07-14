@extends('layouts.app')
@section('title', 'Rekorde & Bestenlisten')
@section('page-title', 'Rekorde & Bestenlisten')

@section('content')
@php
    $initTab = request('tab', session('active_record_tab', 'vr'));
    $userIsAdmin = auth()->user()->role === 'admin';
@endphp
<div class="mt-2 space-y-4" x-data="{
    activeTab: '{{ $initTab }}',
    activeCourse: 'Langbahn',
    showAddVrLr: false,
    showAddBestList: false,
    addType: 'vereinsrekord',
    addListType: 'eternal',
    importType: 'vereinsrekord',
    annualYear: {{ now()->year }},
}">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header Actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @if($userIsAdmin)
            {{-- Add buttons (always visible, highlighting current tab) --}}
            <button @click="showAddVrLr = !showAddVrLr; showAddBestList = false; addType = (activeTab === 'lr' ? 'landesrekord' : 'vereinsrekord')"
                    x-show="activeTab === 'vr' || activeTab === 'lr'"
                    :class="showAddVrLr ? 'bg-primary-dark' : 'bg-primary'"
                    class="px-4 py-2 text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Rekord eintragen
            </button>
            <button @click="showAddBestList = !showAddBestList; showAddVrLr = false; addListType = (activeTab === 'annual' ? 'annual' : 'eternal')"
                    x-show="activeTab === 'eternal' || activeTab === 'annual'"
                    :class="showAddBestList ? 'bg-primary-dark' : 'bg-primary'"
                    class="px-4 py-2 text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Eintrag hinzufügen
            </button>
            @endif

            {{-- Export buttons --}}
            <a x-show="activeTab === 'vr'"
               :href="'{{ route('admin.records.export') }}?type=vereinsrekord&course=' + activeCourse"
               class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
            <a x-show="activeTab === 'lr'"
               :href="'{{ route('admin.records.export') }}?type=landesrekord&course=' + activeCourse"
               class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
            <a x-show="activeTab === 'eternal'"
               :href="'{{ route('admin.bestlist.export') }}?list_type=eternal&course=' + activeCourse"
               class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
            <a x-show="activeTab === 'annual'"
               :href="'{{ route('admin.bestlist.export') }}?list_type=annual&course=' + activeCourse + '&year=' + annualYear"
               class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
        </div>
        @if($userIsAdmin)
        <form method="POST" action="{{ route('admin.records.recheck') }}">
            @csrf
            <button type="submit"
                    class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors flex items-center gap-1.5"
                    onclick="return confirm('Alle Wettkampfergebnisse gegen Rekordlisten prüfen?')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Ergebnisse neu prüfen
            </button>
        </form>
        @endif
    </div>

    {{-- Manual Add Form: VR / LR --}}
    @if($userIsAdmin)
    <div x-show="showAddVrLr" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Rekord eintragen</h3>
        <form method="POST" action="{{ route('admin.records.store') }}" class="space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Typ</label>
                    <select name="type" x-model="addType" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="vereinsrekord">Vereinsrekord</option>
                        <option value="landesrekord">Landesrekord</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bahnlänge</label>
                    <select name="course" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="Langbahn">Langbahn (50 m)</option>
                        <option value="Kurzbahn">Kurzbahn (25 m)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Disziplin <span class="text-red-500">*</span></label>
                    <select name="discipline" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="F">Freistil</option>
                        <option value="B">Brust</option>
                        <option value="R">Rücken</option>
                        <option value="S">Schmetterling</option>
                        <option value="L">Lagen</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Distanz (m) <span class="text-red-500">*</span></label>
                    <select name="distance" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                            <option value="{{ $d }}">{{ $d }} m</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Geschlecht <span class="text-red-500">*</span></label>
                    <select name="gender" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="M">Männlich</option>
                        <option value="F">Weiblich</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name Rekordhalter <span class="text-red-500">*</span></label>
                    <input type="text" name="swimmer_name" required placeholder="Vorname Nachname"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Zeit <span class="text-red-500">*</span></label>
                    <div class="flex gap-1 items-center">
                        <input type="number" name="time_minutes" min="0" placeholder="Min" value="0"
                               class="w-14 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                        <span class="text-gray-400">:</span>
                        <input type="number" name="time_seconds" min="0" max="59" placeholder="Sek" required value="0"
                               class="w-14 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                        <span class="text-gray-400">,</span>
                        <input type="number" name="time_cs" min="0" max="99" placeholder="1/100" required value="0"
                               class="w-14 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
                    <input type="date" name="set_date"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Veranstaltungsort</label>
                    <input type="text" name="location" placeholder="Wettkampf / Ort"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            @if($errors->any())
                <div class="text-red-600 text-sm">{{ $errors->first() }}</div>
            @endif
            <div class="flex gap-3">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                    Rekord speichern
                </button>
                <button type="button" @click="showAddVrLr = false"
                        class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>

    {{-- Manual Add Form: Bestenlisten --}}
    <div x-show="showAddBestList" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Bestenlisten-Eintrag hinzufügen</h3>
        <form method="POST" action="{{ route('admin.bestlist.store') }}" class="space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Liste</label>
                    <select name="list_type" x-model="addListType" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="eternal">Ewige Bestenliste</option>
                        <option value="annual">Jahresbestenliste</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bahnlänge</label>
                    <select name="course" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="Langbahn">Langbahn (50 m)</option>
                        <option value="Kurzbahn">Kurzbahn (25 m)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Disziplin <span class="text-red-500">*</span></label>
                    <select name="discipline" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="F">Freistil</option>
                        <option value="B">Brust</option>
                        <option value="R">Rücken</option>
                        <option value="S">Schmetterling</option>
                        <option value="L">Lagen</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Distanz (m) <span class="text-red-500">*</span></label>
                    <select name="distance" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                            <option value="{{ $d }}">{{ $d }} m</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Geschlecht <span class="text-red-500">*</span></label>
                    <select name="gender" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="M">Männlich</option>
                        <option value="F">Weiblich</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Jahrgang <span class="text-red-500">*</span></label>
                    <input type="number" name="birth_year" min="1900" max="{{ now()->year }}" required placeholder="z.B. 2010"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div x-show="addListType === 'annual'">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Jahr der Leistung</label>
                    <input type="number" name="set_year" min="1900" max="{{ now()->year }}" :value="annualYear" placeholder="{{ now()->year }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="swimmer_name" required placeholder="Vorname Nachname"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Zeit <span class="text-red-500">*</span></label>
                    <div class="flex gap-1 items-center">
                        <input type="number" name="time_minutes" min="0" placeholder="Min" value="0"
                               class="w-14 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                        <span class="text-gray-400">:</span>
                        <input type="number" name="time_seconds" min="0" max="59" placeholder="Sek" required value="0"
                               class="w-14 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                        <span class="text-gray-400">,</span>
                        <input type="number" name="time_cs" min="0" max="99" placeholder="1/100" required value="0"
                               class="w-14 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
                    <input type="date" name="set_date"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Veranstaltungsort</label>
                    <input type="text" name="location" placeholder="Wettkampf / Ort"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>
            @if($errors->any())
                <div class="text-red-600 text-sm">{{ $errors->first() }}</div>
            @endif
            <div class="flex gap-3">
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                    Eintrag speichern
                </button>
                <button type="button" @click="showAddBestList = false"
                        class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>

    {{-- Import Form: VR / LR --}}
    <div x-show="activeTab === 'vr' || activeTab === 'lr'" x-cloak
         class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-1">Rekordliste importieren</h3>
        <p class="text-sm text-gray-500 mb-4">Unterstützte Formate: .xlsx, .xls, .csv, .pdf, .docx — der Import zeigt eine Vorschau zum Prüfen.</p>
        <form method="POST" action="{{ route('admin.records.import.upload') }}" enctype="multipart/form-data" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Typ</label>
                <select name="import_type" required
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="vereinsrekord">Vereinsrekorde</option>
                    <option value="landesrekord">Landesrekorde</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Datei</label>
                <input type="file" name="record_file" accept=".xlsx,.xls,.csv,.pdf,.docx,.doc,.txt" required
                       class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-accent text-white font-semibold rounded-lg text-sm hover:bg-accent-dark transition-colors">
                Einlesen → Vorschau
            </button>
        </form>
        @error('record_file')
            <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
        @enderror
    </div>

    {{-- Import Form: Ewige / Jahres Bestenliste --}}
    <div x-show="activeTab === 'eternal' || activeTab === 'annual'" x-cloak
         class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-1">Bestenliste aus Excel importieren</h3>
        <p class="text-sm text-gray-500 mb-4">
            Excel-Datei mit Spalten: <span class="font-mono text-xs bg-gray-100 px-1 rounded">Disziplin | Distanz | Geschlecht | Jahrgang | Name | Zeit</span> — Überschriften werden automatisch erkannt.
        </p>
        <form method="POST" action="{{ route('admin.bestlist.import.upload') }}" enctype="multipart/form-data" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Liste</label>
                <select name="bestlist_type" x-model="activeTab === 'annual' ? 'annual' : 'eternal'"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="eternal" :selected="activeTab === 'eternal'">Ewige Bestenliste</option>
                    <option value="annual" :selected="activeTab === 'annual'">Jahresbestenliste</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Bahnlänge</label>
                <select name="bestlist_course"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="Langbahn" :selected="activeCourse === 'Langbahn'">Langbahn (50 m)</option>
                    <option value="Kurzbahn" :selected="activeCourse === 'Kurzbahn'">Kurzbahn (25 m)</option>
                </select>
            </div>
            <div x-show="activeTab === 'annual'">
                <label class="block text-xs font-medium text-gray-600 mb-1">Jahr (für Jahresbestenliste)</label>
                <input type="number" name="bestlist_year" :value="annualYear"
                       min="1900" :max="{{ now()->year }}" placeholder="{{ now()->year }}"
                       class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Datei (.xlsx, .csv)</label>
                <input type="file" name="bestlist_file" accept=".xlsx,.xls,.csv,.txt" required
                       class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer">
            </div>
            <button type="submit"
                    class="px-4 py-2 bg-accent text-white font-semibold rounded-lg text-sm hover:bg-accent-dark transition-colors">
                Einlesen → Vorschau
            </button>
        </form>
        @error('bestlist_file')
            <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
        @enderror
    </div>
    @endif

    {{-- Main Card: Tabs --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Tab Bar --}}
        <div class="flex border-b border-gray-200 overflow-x-auto">
            <button @click="activeTab = 'vr'"
                    :class="activeTab === 'vr' ? 'border-primary text-primary bg-blue-50/40' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Vereinsrekorde
                @if($vereinsrekorde->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $vereinsrekorde->count() }}</span>
                @endif
            </button>
            <button @click="activeTab = 'eternal'"
                    :class="activeTab === 'eternal' ? 'border-primary text-primary bg-blue-50/40' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Ewige Bestenlisten
                @if($eternalEntries->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $eternalEntries->count() }}</span>
                @endif
            </button>
            <button @click="activeTab = 'annual'"
                    :class="activeTab === 'annual' ? 'border-primary text-primary bg-blue-50/40' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Jahresbestenlisten
                @if($annualEntries->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $annualEntries->count() }}</span>
                @endif
            </button>
            <button @click="activeTab = 'lr'"
                    :class="activeTab === 'lr' ? 'border-primary text-primary bg-blue-50/40' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-1.5">
                Landesrekorde
                @if($landesrekorde->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $landesrekorde->count() }}</span>
                @endif
            </button>
        </div>

        {{-- Course Toggle (shared across all tabs) --}}
        <div class="flex items-center gap-3 px-5 py-3 bg-gray-50/60 border-b border-gray-100">
            <span class="text-xs text-gray-500 font-medium">Bahnlänge:</span>
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button @click="activeCourse = 'Langbahn'"
                        :class="activeCourse === 'Langbahn' ? 'bg-primary text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-4 py-1.5 font-medium transition-colors">
                    Langbahn
                </button>
                <button @click="activeCourse = 'Kurzbahn'"
                        :class="activeCourse === 'Kurzbahn' ? 'bg-primary text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-4 py-1.5 font-medium transition-colors border-l border-gray-200">
                    Kurzbahn
                </button>
            </div>
        </div>

        {{-- VR Tab --}}
        <div x-show="activeTab === 'vr'" x-cloak>
            @include('admin.records._record_table', [
                'records'  => $vereinsrekorde,
                'type'     => 'vereinsrekord',
                'tabId'    => 'vr',
                'isAdmin'  => $userIsAdmin,
            ])
        </div>

        {{-- Ewige Bestenlisten Tab --}}
        <div x-show="activeTab === 'eternal'" x-cloak>
            @include('admin.records._bestlist_table', [
                'entries'   => $eternalEntries,
                'listType'  => 'eternal',
                'isAdmin'   => $userIsAdmin,
            ])
        </div>

        {{-- Jahresbestenlisten Tab --}}
        <div x-show="activeTab === 'annual'" x-cloak>
            @if($availableYears->isEmpty())
                <p class="text-sm text-gray-400 text-center px-5 py-10">Noch keine Jahresbestenlisten-Einträge vorhanden.</p>
            @else
                {{-- Year selector --}}
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/40 flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-medium">Jahr:</span>
                    <select id="annual-year-select"
                            x-model="annualYear"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white shadow-sm focus:ring-2 focus:ring-blue-400 outline-none">
                        @foreach($availableYears as $yr)
                            <option value="{{ $yr }}">{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach($availableYears as $yr)
                <div x-show="annualYear == {{ $yr }}">
                    @include('admin.records._bestlist_table', [
                        'entries'   => $annualEntries->where('set_year', $yr)->values(),
                        'listType'  => 'annual',
                        'isAdmin'   => $userIsAdmin,
                    ])
                </div>
                @endforeach
            @endif
        </div>

        {{-- LR Tab --}}
        <div x-show="activeTab === 'lr'" x-cloak>
            @include('admin.records._record_table', [
                'records'  => $landesrekorde,
                'type'     => 'landesrekord',
                'tabId'    => 'lr',
                'isAdmin'  => $userIsAdmin,
            ])
        </div>

    </div>

</div>
@endsection

@extends('layouts.app')
@section('title', 'Rekorde')
@section('page-title', 'Rekorde')

@section('content')
<div class="mt-2 space-y-4" x-data="{ activeTab: '{{ session('active_record_tab', 'vr') }}', showAddForm: false, importType: 'vereinsrekord' }">

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
            <button @click="showAddForm = !showAddForm; importType = activeTab === 'lr' ? 'landesrekord' : 'vereinsrekord'"
                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Rekord manuell eintragen
            </button>
            <a href="{{ route('admin.records.import.preview') }}"
               class="hidden"
               id="importPreviewLink"></a>
        </div>
        <form method="POST" action="{{ route('admin.records.recheck') }}">
            @csrf
            <button type="submit"
                    class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors flex items-center gap-1.5"
                    onclick="return confirm('Alle Wettkampfergebnisse gegen Rekordlisten prüfen?')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Ergebnisse neu prüfen
            </button>
        </form>
    </div>

    {{-- Manual Add Form --}}
    <div x-show="showAddForm" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Rekord eintragen</h3>
        <form method="POST" action="{{ route('admin.records.store') }}" class="space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Typ</label>
                    <select name="type" x-model="importType" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="vereinsrekord">Vereinsrekord</option>
                        <option value="landesrekord">Landesrekord</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bahnlänge</label>
                    <select name="course" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="LCM">Langbahn (50m)</option>
                        <option value="SCM">Kurzbahn (25m)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Disziplin <span class="text-red-500">*</span></label>
                    <select name="discipline" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="freistil">Freistil</option>
                        <option value="brust">Brust</option>
                        <option value="ruecken">Rücken</option>
                        <option value="schmetterling">Schmetterling</option>
                        <option value="lagen">Lagen</option>
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
                    <label class="block text-xs font-medium text-gray-600 mb-1">Altersklasse</label>
                    <input type="text" name="age_group" placeholder="z.B. AK12 (leer = Offen)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
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
                <button type="button" @click="showAddForm = false"
                        class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>

    {{-- Import Form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
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
                <label class="block text-xs font-medium text-gray-600 mb-1">Bahnlänge</label>
                <select name="import_course" required
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="LCM">Langbahn (50m)</option>
                    <option value="SCM">Kurzbahn (25m)</option>
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

    {{-- Tabs: Vereinsrekorde / Landesrekorde --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex border-b border-gray-200">
            <button @click="activeTab = 'vr'"
                    :class="activeTab === 'vr' ? 'border-primary text-primary bg-blue-50/40' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1.5">
                Vereinsrekorde
                @if($vereinsrekorde->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $vereinsrekorde->count() }}</span>
                @endif
            </button>
            <button @click="activeTab = 'lr'"
                    :class="activeTab === 'lr' ? 'border-primary text-primary bg-blue-50/40' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-1.5">
                Landesrekorde
                @if($landesrekorde->isNotEmpty())
                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-normal">{{ $landesrekorde->count() }}</span>
                @endif
            </button>
        </div>

        {{-- VR Table --}}
        <div x-show="activeTab === 'vr'" x-cloak>
            @include('admin.records._table', ['records' => $vereinsrekorde, 'type' => 'vereinsrekord'])
        </div>

        {{-- LR Table --}}
        <div x-show="activeTab === 'lr'" x-cloak>
            @include('admin.records._table', ['records' => $landesrekorde, 'type' => 'landesrekord'])
        </div>
    </div>

</div>
@endsection

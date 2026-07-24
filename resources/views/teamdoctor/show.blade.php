@extends('layouts.app')
@section('title', 'Teamarzt – ' . $user->name)
@section('page-title', 'Sportmedizin: ' . $user->name)

@section('content')
<div class="max-w-3xl mt-2 space-y-5">

    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <a href="{{ route('teamdoctor.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Zurück zur Kandidatenliste
    </a>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <h2 class="text-base font-semibold text-gray-800">Neues Dokument hochladen</h2>
        </div>
        <form method="POST" action="{{ route('teamdoctor.upload', $user) }}" enctype="multipart/form-data" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PDF-Datei <span class="text-red-500">*</span></label>
                <input type="file" name="document" accept=".pdf" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-gray-100 file:text-gray-700">
                <p class="text-xs text-gray-400 mt-1">Nur PDF, max. 20 MB</p>
            </div>
            <div x-data="{
                tags: [],
                input: '',
                suggestions: {{ json_encode($allTags) }},
                get filtered() { return this.input.length > 0 ? this.suggestions.filter(s => s.toLowerCase().includes(this.input.toLowerCase()) && !this.tags.includes(s)) : [] },
                addTag(tag) { tag = tag.trim(); if (tag && !this.tags.includes(tag)) this.tags.push(tag); this.input = ''; },
                removeTag(i) { this.tags.splice(i, 1); },
                onKeydown(e) { if ((e.key === 'Enter' || e.key === ',') && this.input.trim()) { e.preventDefault(); this.addTag(this.input); } else if (e.key === 'Backspace' && !this.input && this.tags.length) { this.tags.pop(); } }
            }">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                <input type="hidden" name="tags" :value="tags.join(',')">
                <div class="flex flex-wrap gap-1.5 px-3 py-2 border border-gray-300 rounded-lg min-h-[42px] focus-within:ring-2 focus-within:ring-primary/30 focus-within:border-primary">
                    <template x-for="(tag, i) in tags" :key="i">
                        <span class="inline-flex items-center gap-1 bg-primary/10 text-primary text-xs px-2 py-0.5 rounded-full">
                            <span x-text="tag"></span>
                            <button type="button" @click="removeTag(i)" class="hover:text-red-500">&times;</button>
                        </span>
                    </template>
                    <input type="text" x-model="input" @keydown="onKeydown($event)"
                           placeholder="Tag eingeben, Enter zum Hinzufügen"
                           class="flex-1 min-w-[120px] text-sm outline-none bg-transparent placeholder-gray-400"
                           list="tag-suggestions-td">
                    <datalist id="tag-suggestions-td">
                        <template x-for="s in filtered" :key="s"><option :value="s"></option></template>
                    </datalist>
                </div>
                <p class="text-xs text-gray-400 mt-1">Enter oder Komma zum Hinzufügen.</p>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                Hochladen
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-800">Gespeicherte Dokumente ({{ $documents->count() }})</h2>
        </div>
        @if($documents->isEmpty())
        <div class="px-6 py-8 text-center text-sm text-gray-400">Noch keine Dokumente vorhanden.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($documents as $doc)
            <div class="flex items-start justify-between px-6 py-3.5 gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <svg class="w-8 h-8 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ $doc->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $doc->file_size_formatted }} &nbsp;·&nbsp; {{ $doc->created_at->format('d.m.Y H:i') }}
                            @if($doc->uploader) &nbsp;·&nbsp; {{ $doc->uploader->name }} @endif
                        </p>
                        @if($doc->tags)
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($doc->tags as $tag)
                            <span class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $tag }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('health.download', $doc) }}"
                       class="flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary text-xs font-medium rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download
                    </a>
                    <form method="POST" action="{{ route('teamdoctor.destroy', $doc) }}" onsubmit="return confirm('Dokument wirklich löschen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Löschen
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection

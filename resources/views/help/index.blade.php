@extends('layouts.app')
@section('title', 'Hilfe & FAQ')
@section('page-title', 'Hilfe & FAQ')

@section('content')
{{--
    Anleitungen und häufige Fragen (App\Support\HelpCatalog). Standard: die
    Themen für die eigene Rolle ("Für mich"); die Suche durchsucht immer alles.
    Direktlink auf ein Thema: /hilfe#kalender-abo
--}}
<div class="mt-2 max-w-3xl space-y-6"
     x-data="{
        q: '',
        all: false,
        words() { return this.q.trim().toLowerCase().split(/\s+/).filter(w => w) },
        show(el) {
            const w = this.words();
            if (w.length) return w.every(x => el.dataset.text.includes(x));
            return this.all || el.dataset.mine === '1';
        },
        anyIn(section) { return [...section.querySelectorAll('[data-help]')].some(el => this.show(el)) },
        none() { return ![...$root.querySelectorAll('[data-help]')].some(el => this.show(el)) },
        openHash() {
            const el = location.hash ? document.getElementById(location.hash.slice(1)) : null;
            if (el && el.tagName === 'DETAILS') { this.all = true; el.open = true; this.$nextTick(() => el.scrollIntoView({ block: 'start' })) }
        },
     }"
     x-init="openHash()" @hashchange.window="openHash()">

    <x-ui.alert tone="info">
        Hier findest du Anleitungen für den Einstieg und Antworten auf häufige Fragen. Angezeigt werden zuerst die Themen, die zu dir passen –
        über <strong>Alle Themen</strong> siehst du alles. Nicht fündig geworden? Schreib uns über den <a href="{{ route('support.create') }}" class="font-semibold underline">Support</a>.
    </x-ui.alert>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="help-search" class="mb-1 block text-sm font-medium text-gray-700">Suchen</label>
            <input id="help-search" type="search" x-model.debounce.150ms="q" placeholder="z. B. Passwort, absagen, Kalender, App"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/30">
        </div>
        <div class="flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm" role="group" aria-label="Welche Themen anzeigen">
            <button type="button" @click="all = false" :aria-pressed="!all"
                    :class="!all ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-50'"
                    class="rounded-md px-3 py-1.5 font-medium">Für mich</button>
            <button type="button" @click="all = true" :aria-pressed="all"
                    :class="all ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-50'"
                    class="rounded-md px-3 py-1.5 font-medium">Alle Themen</button>
        </div>
    </div>

    @foreach($categories as $cat => $label)
        @if(isset($articles[$cat]))
            <section x-show="anyIn($el)" class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">{{ $label }}</h2>
                @foreach($articles[$cat] as $a)
                    <details id="{{ $a['key'] }}" data-help data-mine="{{ $a['mine'] ? '1' : '0' }}" data-text="{{ $a['index'] }}"
                             x-show="show($el)" class="group rounded-xl border border-gray-100 bg-white shadow-sm">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-medium text-gray-900 hover:bg-gray-50 rounded-xl">
                            <span>{{ $a['title'] }}</span>
                            <svg class="h-4 w-4 flex-shrink-0 text-gray-500 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="rich-text help-article border-t border-gray-100 px-4 py-3">{!! $a['body'] !!}</div>
                    </details>
                @endforeach
            </section>
        @endif
    @endforeach

    <section x-show="anyIn($el)" class="space-y-2">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Häufige Fragen</h2>
        @foreach($faq as $i => $f)
            <details id="faq-{{ $i + 1 }}" data-help data-mine="{{ $f['mine'] ? '1' : '0' }}" data-text="{{ $f['index'] }}"
                     x-show="show($el)" class="group rounded-xl border border-gray-100 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-gray-900 hover:bg-gray-50 rounded-xl">
                    <span>{{ $f['q'] }}</span>
                    <svg class="h-4 w-4 flex-shrink-0 text-gray-500 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="rich-text help-article border-t border-gray-100 px-4 py-3"><p>{!! $f['a'] !!}</p></div>
            </details>
        @endforeach
    </section>

    <div x-show="none()" x-cloak>
        <x-ui.empty-state icon="lifebuoy" title="Nichts gefunden" text="Versuche ein anderes Stichwort oder schreib uns über den Support." />
    </div>

    <x-ui.card title="Noch Fragen?">
        <p class="mb-3 text-sm text-gray-700">Melde Fehler oder schick uns einen Verbesserungsvorschlag – bitte mit kurzer Beschreibung, was du getan hast und auf welchem Gerät.</p>
        <x-ui.button :href="route('support.create')" icon="lifebuoy">Zum Support</x-ui.button>
    </x-ui.card>
</div>
@endsection

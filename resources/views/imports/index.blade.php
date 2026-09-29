@extends('layouts.app')
@section('title', 'Import-Center')
@section('page-title', 'Import-Center')

@section('content')
<div class="mt-2 space-y-8">

    <p class="text-sm text-gray-700 max-w-3xl">
        Alle Datei-Importe an einer Stelle. Jeder Import läuft in drei Schritten:
        <strong>Datei wählen</strong>, <strong>prüfen</strong> (noch nichts gespeichert) und <strong>übernehmen</strong>.
    </p>

    @foreach($areas as $area => $imports)
        <section aria-labelledby="area-{{ \Illuminate\Support\Str::slug($area) }}">
            <h2 id="area-{{ \Illuminate\Support\Str::slug($area) }}" class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-3">{{ $area }}</h2>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($imports as $key => $import)
                    <li>
                        <a href="{{ \App\Support\ImportCatalog::url($key, $import) }}"
                           class="group flex h-full flex-col gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-colors hover:border-primary hover:bg-primary/5
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                            <span class="flex items-start gap-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                                    <x-ui.icon :name="$import['icon']" class="w-5 h-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900 group-hover:text-primary">{{ $import['title'] }}</span>
                                    <span class="block text-xs text-gray-600 mt-0.5">{{ $import['source'] }}</span>
                                </span>
                            </span>
                            <span class="text-sm text-gray-700">{{ $import['text'] }}</span>
                            <span class="mt-auto flex flex-wrap items-center gap-2 pt-1 text-xs">
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-gray-700">{{ $import['formats'] }}</span>
                                @isset($import['context'])
                                    <span class="rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-900">am Objekt: {{ $import['context'] }}</span>
                                @endisset
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    @if($crawlers)
        <section aria-labelledby="area-auto">
            <div class="flex items-center justify-between mb-3">
                <h2 id="area-auto" class="text-xs font-semibold uppercase tracking-wide text-gray-600">Automatische Importe</h2>
                <a href="{{ route('admin.import-log.index') }}" class="text-sm font-medium text-primary hover:underline">Crawler & Import-Log →</a>
            </div>
            <x-ui.card :padded="false">
                <ul class="divide-y divide-gray-100">
                    @foreach($crawlers as $c)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm">
                            <span class="font-medium text-gray-900">{{ $c['label'] }}</span>
                            <span class="text-gray-700">
                                @if($c['last'])
                                    zuletzt {{ $c['last']->diffForHumans() }} <span class="text-gray-600">({{ $c['last']->format('d.m.Y H:i') }})</span>
                                @else
                                    <span class="text-gray-600">noch nie gelaufen</span>
                                @endif
                                @if($c['note'])<span class="block text-xs text-gray-600">{{ $c['note'] }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </section>
    @endif
</div>
@endsection

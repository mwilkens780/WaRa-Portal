@extends('layouts.app')
@section('title', $import['title'] . ' importieren')
@section('page-title', $import['title'] . ' importieren')

@section('content')
<div class="mt-2 max-w-2xl space-y-5">
    <x-ui.import-steps :current="1" />

    <x-ui.card>
        <p class="text-sm text-gray-700 mb-1">{{ $import['text'] }}</p>
        <p class="text-xs text-gray-600 mb-4">Quelle: {{ $import['source'] }} · Vor dem Übernehmen zeigt eine Vorschau, was sich ändert.</p>
        @include('imports._upload', ['import' => $import])
    </x-ui.card>

    <div class="flex flex-wrap gap-4 text-sm">
        <a href="{{ route('imports.index') }}" class="text-gray-700 hover:text-primary">← Import-Center</a>
        @isset($import['back'])
            <a href="{{ route($import['back'][0]) }}" class="text-gray-700 hover:text-primary">{{ $import['back'][1] }}</a>
        @endisset
    </div>
</div>
@endsection

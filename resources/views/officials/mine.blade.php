@extends('layouts.app')
@section('title', 'Meine Qualifikationen')
@section('page-title', 'Meine Qualifikationen')

@section('content')
<div class="mt-2 max-w-3xl space-y-6">
    <x-ui.alert tone="info">
        Trage hier deine Kampfrichter-Qualifikationen mit Lizenznummer und Ablaufdatum ein. Vorstand und Geschäftsstelle sehen
        die Angaben; sechs Monate vor Ablauf erinnert dich das Dashboard. Die Hauptlizenz steht auch in deinem Mitgliedsdatensatz.
    </x-ui.alert>
    @if($errors->any())
        <x-ui.alert tone="error">Bitte die Angaben prüfen: {{ $errors->first() }}</x-ui.alert>
    @endif
    @include('officials._qualifications', ['user' => $user])
</div>
@endsection

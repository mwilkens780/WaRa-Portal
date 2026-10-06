@extends('layouts.legal')
@section('title', 'Einladung: ' . $event->title)

{{-- Gast ohne Portal-Konto: persönlicher Link (Token), siehe GuestInvitationController --}}
@php
    $statusTone = ['offen' => 'neutral', 'zugesagt' => 'success', 'vielleicht' => 'warning', 'abgesagt' => 'danger'];
    $filesByCat = $event->files->groupBy('category');
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-sm text-gray-600">{{ $event->type_label }}</p>
        <h1 class="text-2xl font-bold text-gray-900">{{ $event->title }}</h1>
        <p class="mt-1 text-sm text-gray-700">Einladung für {{ $invitee->display_name }}</p>
    </div>

    @if(session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if($errors->any())
        <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <x-ui.card>
        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
            <div><dt class="text-gray-600">Wann</dt><dd class="font-medium text-gray-900">{{ $event->when_label }}</dd></div>
            @if($event->location)
                <div><dt class="text-gray-600">Ort</dt><dd class="font-medium text-gray-900 break-words">{{ $event->location }}</dd></div>
            @endif
        </dl>
        @if($event->description)
            <p class="mt-4 text-sm text-gray-800 whitespace-pre-line">{{ $event->description }}</p>
        @endif
    </x-ui.card>

    @if($event->rsvp_enabled)
        <x-ui.card title="Deine Rückmeldung">
            <p class="text-sm text-gray-700 mb-3">
                Aktuell: <x-ui.badge :tone="$statusTone[$invitee->status] ?? 'neutral'">{{ $invitee->status_label }}</x-ui.badge>
            </p>
            @if($event->rsvpOpen())
                <form method="POST" action="{{ route('invitation.guest.respond', $invitee->token) }}" class="space-y-3">
                    @csrf
                    <x-ui.field label="Kommentar (optional)" name="comment" :value="$invitee->comment" maxlength="500" />
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="submit" name="status" value="zugesagt">Zusagen</x-ui.button>
                        <x-ui.button type="submit" name="status" value="vielleicht" variant="secondary">Vielleicht</x-ui.button>
                        <x-ui.button type="submit" name="status" value="abgesagt" variant="secondary">Absagen</x-ui.button>
                    </div>
                </form>
            @else
                <p class="text-sm text-gray-600">Die Anmeldung ist geschlossen.</p>
            @endif
        </x-ui.card>
    @endif

    @if($event->agenda)
        <x-ui.card title="Agenda">
            <x-ui.rich-text :html="$event->agenda" />
        </x-ui.card>
    @endif

    @if($event->files->isNotEmpty())
        <x-ui.card title="Unterlagen und Protokolle">
            <div class="space-y-4">
                @foreach(\App\Models\CalendarEventFile::CATEGORIES as $cat => $catLabel)
                    @continue(!$filesByCat->has($cat))
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800 mb-2">{{ $catLabel }}</h2>
                        <ul class="space-y-1">
                            @foreach($filesByCat[$cat] as $file)
                                <li>
                                    <a href="{{ route('invitation.guest.file', [$invitee->token, $file]) }}" class="text-sm font-medium text-primary hover:underline break-words"
                                       @if($file->isLink()) target="_blank" rel="noopener" @endif>{{ $file->title }}</a>
                                    @if($file->size_label) <span class="text-xs text-gray-600">({{ $file->size_label }})</span> @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <p class="text-xs text-gray-600">Dieser Link ist persönlich und funktioniert ohne Anmeldung. Bitte nicht weitergeben.</p>
</div>
@endsection

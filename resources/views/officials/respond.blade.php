@extends('layouts.app')
@section('title', 'Kampfgericht: ' . $req->competition->name)
@section('page-title', 'Kampfgericht')

@php
    use App\Models\CompetitionOfficialRequest;
    $c       = $req->competition;
    $chosen  = old('positions', $invitee->positions ?? []);
@endphp

@section('content')
<div class="mt-2 max-w-3xl space-y-6">
    <x-ui.page-header :back="route('invitations.index')" back-label="Einladungen"
                      :subtitle="$c->name . ' · ' . $c->date->format('d.m.Y') . ($c->location ? ' · ' . $c->location : '')" />

    @if($req->message)
        <x-ui.alert tone="info">{{ $req->message }}</x-ui.alert>
    @endif

    @if($invitee->responded_at)
        <p class="text-sm text-gray-700">Zuletzt geantwortet am {{ $invitee->responded_at->format('d.m.Y, H:i') }} Uhr – du kannst deine Angaben ändern{{ $req->deadline ? ' bis zum ' . $req->deadline->format('d.m.Y') : '' }}.</p>
    @endif

    @if(!$req->isOpen())
        <x-ui.alert tone="warning">Die Abfrage ist geschlossen. Für Änderungen wende dich bitte an den Vorstand.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('officials.respond.update', $req) }}" class="space-y-6">
        @csrf @method('PUT')
        @error('days') <x-ui.alert tone="error">{{ $message }}</x-ui.alert> @enderror

        <x-ui.card title="Verfügbarkeit">
            <div class="space-y-5">
                @foreach($days as $i => $day)
                    @php
                        $date  = \Illuminate\Support\Carbon::parse($day);
                        $value = old("days.$day.available", ($a = $invitee->availableOn($day)) === null ? '' : ($a ? '1' : '0'));
                    @endphp
                    <fieldset class="space-y-2 {{ $i ? 'border-t border-gray-100 pt-4' : '' }}">
                        <legend class="text-sm font-semibold text-gray-900">{{ $date->isoFormat('dddd, D. MMMM YYYY') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['1' => 'Ich kann', '0' => 'Ich kann nicht'] as $v => $label)
                                <label class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="days[{{ $day }}][available]" value="{{ $v }}" @checked((string) $value === (string) $v) @disabled(!$req->isOpen())
                                           class="border-gray-300 text-primary focus:ring-primary/30">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <x-ui.field label="Kommentar zu diesem Tag" :name="'days[' . $day . '][comment]'" :id="'day-comment-' . $day"
                                    :value="$invitee->commentOn($day)" maxlength="300" placeholder="z. B. nur vormittags" :disabled="!$req->isOpen()" />
                    </fieldset>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Wunschpositionen">
            <fieldset>
                <legend class="sr-only">Wunschpositionen</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach(CompetitionOfficialRequest::POSITIONS as $code => $label)
                        <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm text-gray-800 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="positions[]" value="{{ $code }}" @checked(in_array($code, $chosen, true)) @disabled(!$req->isOpen())
                                   class="rounded border-gray-300 text-primary focus:ring-primary/30">
                            <span>{{ $label }} <span class="text-gray-600">({{ $code }})</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="mt-4">
                <x-ui.field label="Anmerkung" name="comment" as="textarea" rows="3" :value="$invitee->comment" maxlength="1000"
                            placeholder="z. B. Lizenz, Erfahrung, Mitfahrgelegenheit" :disabled="!$req->isOpen()" />
            </div>
        </x-ui.card>

        @if($req->isOpen())
            <x-ui.button type="submit">Rückmeldung speichern</x-ui.button>
        @endif
    </form>
</div>
@endsection

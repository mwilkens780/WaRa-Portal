@extends('layouts.app')
@section('title', $event->title)
@section('page-title', $event->title)

@php
    use App\Models\CalendarEventFile;
    use App\Models\CalendarEventInvitee;
    $statusTone = ['offen' => 'neutral', 'zugesagt' => 'success', 'vielleicht' => 'warning', 'abgesagt' => 'danger'];
    $counts     = $invitees->countBy('status');
    $filesByCat = $event->files->groupBy('category');
@endphp

@section('content')
<div class="mt-2 max-w-4xl space-y-6">

    <x-ui.page-header :back="route('calendar.index', ['date' => $event->start_date->format('Y-m-d')])" back-label="Kalender"
                      :subtitle="$event->type_label">
        <x-slot:actions>
            @if(app(\App\Services\CalendarScope::class)->eventVisible(auth()->user(), $event))
                <x-ui.button variant="secondary" icon="calendar" :href="route('calendar.export', ['termin', $event->id])">In meinen Kalender</x-ui.button>
            @endif
        @if($canManage)
                <x-ui.button variant="secondary" icon="pencil" :href="route('calendar.events.edit', $event)">Bearbeiten</x-ui.button>
                @if($event->rsvp_enabled && ($counts['offen'] ?? 0) > 0)
                    <form method="POST" action="{{ route('calendar.events.remind', $event) }}"
                          data-confirm="Erinnerung an {{ $counts['offen'] }} Eingeladene ohne Rückmeldung schicken?" data-confirm-label="Erinnern">
                        @csrf
                        <x-ui.button type="submit" variant="secondary">Erinnerung senden</x-ui.button>
                    </form>
                @endif
        @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Eckdaten --}}
    <x-ui.card>
        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
            <div><dt class="text-gray-600">Wann</dt><dd class="font-medium text-gray-900">{{ $event->when_label }}</dd></div>
            @if($event->location)
                <div><dt class="text-gray-600">Ort</dt><dd class="font-medium text-gray-900 break-words">{{ $event->location }}</dd></div>
            @endif
            @if($event->creator)
                <div><dt class="text-gray-600">Eingeladen von</dt><dd class="text-gray-900">{{ $event->creator->firstname }} {{ $event->creator->lastname }}</dd></div>
            @endif
            @if($event->rsvp_enabled && $canSee)
                <div>
                    <dt class="text-gray-600">Anmeldung</dt>
                    <dd class="text-gray-900">
                        {{ $counts['zugesagt'] ?? 0 }} zugesagt{{ $event->capacity ? ' von ' . $event->capacity . ' Plätzen' : '' }}
                        @if($event->rsvp_deadline) · bis {{ $event->rsvp_deadline->format('d.m.Y') }} @endif
                        @if(!$event->rsvpOpen()) <x-ui.badge>geschlossen</x-ui.badge> @endif
                    </dd>
                </div>
            @endif
        </dl>
        @if($event->description && $canSee)
            <p class="mt-4 text-sm text-gray-800 whitespace-pre-line">{{ $event->description }}</p>
        @endif
    </x-ui.card>

    @if(!$canSee)
        <x-ui.alert tone="info">Agenda, Unterlagen und Teilnehmende sehen nur die Eingeladenen.</x-ui.alert>
    @else

        {{-- Eigene Rückmeldung (und die minderjähriger Kinder) --}}
        @foreach($myInvitations as $inv)
            @php $forChild = $inv->user_id !== auth()->id(); @endphp
            <x-ui.card :title="$forChild ? 'Rückmeldung für ' . $inv->user->firstname : 'Deine Rückmeldung'">
                <p class="text-sm text-gray-700 mb-3">
                    Aktuell: <x-ui.badge :tone="$statusTone[$inv->status] ?? 'neutral'">{{ $inv->status_label }}</x-ui.badge>
                    @if($inv->comment) <span class="text-gray-600">– „{{ $inv->comment }}“</span> @endif
                </p>
                @if($event->rsvp_enabled && $event->rsvpOpen())
                    <form method="POST" action="{{ route('calendar.events.respond', [$event, $inv]) }}" class="space-y-3">
                        @csrf
                        <x-ui.field label="Kommentar (optional)" name="comment" :value="$inv->comment" maxlength="500"
                                    :id="'comment-' . $inv->id" placeholder="z. B. komme 15 Minuten später" />
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button type="submit" name="status" value="zugesagt" :disabled="$event->isFull() && $inv->status !== 'zugesagt'">Zusagen</x-ui.button>
                            <x-ui.button type="submit" name="status" value="vielleicht" variant="secondary">Vielleicht</x-ui.button>
                            <x-ui.button type="submit" name="status" value="abgesagt" variant="secondary">Absagen</x-ui.button>
                        </div>
                        @if($event->isFull() && $inv->status !== 'zugesagt')
                            <p class="text-sm text-amber-800">Alle Plätze sind vergeben.</p>
                        @endif
                    </form>
                @elseif($event->rsvp_enabled)
                    <p class="text-sm text-gray-600">Die Anmeldung ist geschlossen.</p>
                @endif
            </x-ui.card>
        @endforeach

        {{-- Agenda --}}
        @if($event->agenda)
            <x-ui.card title="Agenda">
                <x-ui.rich-text :html="$event->agenda" />
            </x-ui.card>
        @endif

        {{-- Unterlagen, Anhänge, Protokolle --}}
        <x-ui.card title="Unterlagen und Protokolle">
            @if($event->files->isEmpty())
                <p class="text-sm text-gray-600">Noch keine Unterlagen.</p>
            @endif
            <div class="space-y-5">
                @foreach(CalendarEventFile::CATEGORIES as $cat => $catLabel)
                    @continue(!$filesByCat->has($cat))
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 mb-2">{{ $catLabel }}</h3>
                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                            @foreach($filesByCat[$cat] as $file)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                    <a href="{{ route('calendar.events.files.download', [$event, $file]) }}" class="min-w-0 text-sm font-medium text-primary hover:underline break-words"
                                       @if($file->isLink()) target="_blank" rel="noopener" @endif>
                                        {{ $file->title }}
                                    </a>
                                    <span class="flex items-center gap-2 text-xs text-gray-600">
                                        @if($file->isLink()) Link @elseif($file->size_label) {{ $file->size_label }} @endif
                                        @if($file->source_file_id) · aus früherem Termin @endif
                                        @if($canManage)
                                            <form method="POST" action="{{ route('calendar.events.files.destroy', [$event, $file]) }}"
                                                  data-confirm="„{{ $file->title }}“ entfernen?" data-confirm-label="Entfernen" data-confirm-danger>
                                                @csrf @method('DELETE')
                                                <x-ui.icon-button type="submit" icon="trash" :label="'„' . $file->title . '“ entfernen'" />
                                            </form>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            @if($canManage)
                <form method="POST" action="{{ route('calendar.events.files.store', $event) }}" enctype="multipart/form-data"
                      class="mt-6 border-t border-gray-100 pt-5 space-y-4"
                      x-data="{ mode: '{{ old('mode', 'file') }}', category: '{{ old('category', $event->start_date->isPast() ? 'protokoll' : 'agenda') }}' }">
                    @csrf
                    <h3 class="text-sm font-semibold text-gray-800">Hinzufügen</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Bereich" name="category" as="select" x-model="category">
                            @foreach(CalendarEventFile::CATEGORIES as $cat => $catLabel)
                                <option value="{{ $cat }}">{{ $catLabel }}</option>
                            @endforeach
                        </x-ui.field>
                        <x-ui.field label="Art" name="mode" as="select" x-model="mode">
                            <option value="file">Datei hochladen</option>
                            <option value="link">Link</option>
                            @if($previousProtocols->isNotEmpty())
                                <option value="previous">Protokoll eines früheren Termins</option>
                            @endif
                        </x-ui.field>
                    </div>
                    <x-ui.field label="Titel" name="title" maxlength="200" hint="Leer lassen: Dateiname bzw. Titel des früheren Protokolls." />
                    <div x-show="mode === 'file'">
                        <x-ui.file-drop name="file" label="Datei" accept=".pdf,.doc,.docx,.odt,.xls,.xlsx,.ods,.ppt,.pptx,.odp,.txt,.jpg,.jpeg,.png" :max-mb="20" :required="false" />
                    </div>
                    <div x-show="mode === 'link'" x-cloak>
                        <x-ui.field label="Link" name="url" type="url" placeholder="https://…" />
                    </div>
                    @if($previousProtocols->isNotEmpty())
                        <div x-show="mode === 'previous'" x-cloak>
                            <x-ui.field label="Früheres Protokoll" name="source_file_id" as="select">
                                @foreach($previousProtocols as $p)
                                    <option value="{{ $p->id }}">{{ $p->event->start_date->format('d.m.Y') }} – {{ $p->event->title }}: {{ $p->title }}</option>
                                @endforeach
                            </x-ui.field>
                        </div>
                    @endif
                    <label class="flex items-start gap-3 text-sm text-gray-800" x-show="category === 'protokoll'" x-cloak>
                        <input type="checkbox" name="notify" value="1" checked class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary/30">
                        <span>Eingeladene per Mail auf das neue Protokoll hinweisen</span>
                    </label>
                    <x-ui.button type="submit" variant="secondary" icon="plus">Hinzufügen</x-ui.button>
                </form>
            @endif
        </x-ui.card>

        {{-- Eingeladene --}}
        @if($canManage)
            <x-ui.table caption="Eingeladene" title="Eingeladene"
                        :meta="collect(CalendarEventInvitee::STATUSES)->map(fn($l, $k) => ($counts[$k] ?? 0) . ' ' . mb_strtolower($l))->implode(' · ')"
                        :columns="['Name', ['label' => 'Herkunft', 'hide' => 'md'], 'Rückmeldung', ['label' => 'Kommentar', 'hide' => 'sm'], ['label' => 'Aktionen', 'sr' => true]]"
                        :empty="$invitees->isEmpty()" empty-title="Noch niemand eingeladen" stack>
                @foreach($invitees as $inv)
                    <tr class="hover:bg-gray-50">
                        <x-ui.td label="Name" strong>
                            {{ $inv->display_name }}
                            @if($inv->isGuest()) <span class="block text-xs font-normal text-gray-600 break-all">{{ $inv->guest_email }}</span> @endif
                        </x-ui.td>
                        <x-ui.td label="Herkunft" hide="md" muted>{{ CalendarEventInvitee::SOURCES[$inv->source] ?? $inv->source }}</x-ui.td>
                        <x-ui.td label="Rückmeldung"><x-ui.badge :tone="$statusTone[$inv->status] ?? 'neutral'">{{ $inv->status_label }}</x-ui.badge></x-ui.td>
                        <x-ui.td label="Kommentar" hide="sm" class="text-sm text-gray-700">{{ $inv->comment }}</x-ui.td>
                        <x-ui.td align="right">
                            <form method="POST" action="{{ route('calendar.events.invitee.destroy', [$event, $inv]) }}"
                                  data-confirm="{{ $inv->display_name }} ausladen?" data-confirm-label="Ausladen" data-confirm-danger>
                                @csrf @method('DELETE')
                                <x-ui.icon-button type="submit" icon="trash" :label="$inv->display_name . ' ausladen'" />
                            </form>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>

            <x-ui.card title="Weitere einladen" collapsible storage-key="event-invite-more" :open="$invitees->isEmpty()">
                <form method="POST" action="{{ route('calendar.events.invite', $event) }}" class="space-y-5">
                    @csrf
                    @include('calendar.events._invite', ['fixedAudience' => $event->audience])
                    <x-ui.button type="submit">Einladen</x-ui.button>
                </form>
            </x-ui.card>
        @elseif($event->hasInvitations())
            <p class="text-sm text-gray-600">
                {{ $counts['zugesagt'] ?? 0 }} Zusagen, {{ $counts['vielleicht'] ?? 0 }} vielleicht, {{ $invitees->count() }} Eingeladene insgesamt.
            </p>
        @endif
    @endif
</div>
@endsection

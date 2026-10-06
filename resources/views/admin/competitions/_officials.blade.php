{{--
    Reiter "Kampfgericht": Kampfrichter-Abfrage starten (Vorstand/Admin) und
    Rückmeldungen auswerten – je Veranstaltungstag und je Wunschposition.
    Erwartet: $competition, $officialRequest, $canManageOfficials, $myOfficialInvite,
              $officialCandidates, $officialCount
--}}
@php
    use App\Models\CompetitionOfficialRequest;
    use Illuminate\Support\Carbon;
    $req      = $officialRequest;
    $days     = $req?->days() ?? [];
    $invitees = $req ? $req->invitees->sortBy(fn($i) => [$i->responded_at ? 0 : 1, $i->user?->lastname]) : collect();
    $answered = $invitees->whereNotNull('responded_at');
@endphp

@if($myOfficialInvite && !$canManageOfficials)
    <x-ui.alert tone="info">
        Du bist für diesen Wettkampf als Kampfrichter angefragt.
        <a href="{{ route('officials.respond', $req) }}" class="font-semibold underline">Rückmeldung {{ $myOfficialInvite->responded_at ? 'ändern' : 'geben' }}</a>
    </x-ui.alert>
@endif

@if($canManageOfficials)
    @if($req)
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-700">
                {{ $answered->count() }} von {{ $invitees->count() }} haben geantwortet
                @if($req->deadline) · Rückmeldung bis {{ $req->deadline->format('d.m.Y') }} @endif
                @if(!$req->isOpen()) · <x-ui.badge>geschlossen</x-ui.badge> @endif
            </p>
            <div class="flex flex-wrap gap-2">
                @if($invitees->whereNull('responded_at')->isNotEmpty() && $req->isOpen())
                    <form method="POST" action="{{ route('admin.competitions.officials.remind', $competition) }}"
                          data-confirm="Erinnerung an {{ $invitees->whereNull('responded_at')->count() }} Kampfrichter ohne Rückmeldung schicken?" data-confirm-label="Erinnern">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">Erinnerung senden</x-ui.button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.competitions.officials.toggle', $competition) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">{{ $req->closed_at ? 'Wieder öffnen' : 'Abfrage schließen' }}</x-ui.button>
                </form>
            </div>
        </div>

        {{-- Auswertung: je Tag und je Position, wer verfügbar ist --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card title="Verfügbar je Tag">
                <ul class="space-y-2 text-sm">
                    @foreach($days as $day)
                        @php $yes = $invitees->filter(fn($i) => $i->availableOn($day) === true); @endphp
                        <li>
                            <span class="font-medium text-gray-900">{{ Carbon::parse($day)->isoFormat('dd, D.M.') }}:</span>
                            {{ $yes->count() }} verfügbar
                            @if($yes->isNotEmpty())
                                <span class="block text-gray-600">{{ $yes->map(fn($i) => $i->user?->firstname . ' ' . $i->user?->lastname)->implode(', ') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
            <x-ui.card title="Wunschpositionen (Verfügbare)">
                @php
                    $byPos = [];
                    foreach ($invitees->filter(fn($i) => $i->availableAnyDay()) as $i) {
                        foreach ($i->positions ?? [] as $p) $byPos[$p][] = $i->user?->firstname . ' ' . $i->user?->lastname;
                    }
                @endphp
                @if(empty($byPos))
                    <p class="text-sm text-gray-600">Noch keine Wunschpositionen.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach(CompetitionOfficialRequest::POSITIONS as $code => $label)
                            @continue(empty($byPos[$code]))
                            <li><span class="font-medium text-gray-900">{{ $label }} ({{ count($byPos[$code]) }}):</span>
                                <span class="text-gray-700">{{ implode(', ', $byPos[$code]) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        @php
            $cols = ['Name'];
            foreach ($days as $day) $cols[] = Carbon::parse($day)->isoFormat('dd D.M.');
            $cols[] = ['label' => 'Positionen', 'hide' => 'md'];
            $cols[] = ['label' => 'Anmerkung', 'hide' => 'lg'];
        @endphp
        <x-ui.table caption="Rückmeldungen der Kampfrichter" title="Rückmeldungen" :columns="$cols"
                    :empty="$invitees->isEmpty()" empty-title="Noch niemand angefragt" stack>
            @foreach($invitees as $inv)
                <tr class="hover:bg-gray-50">
                    <x-ui.td label="Name" strong>
                        {{ $inv->user?->firstname }} {{ $inv->user?->lastname }}
                        @unless($inv->responded_at)<span class="block text-xs font-normal text-gray-600">noch keine Rückmeldung</span>@endunless
                    </x-ui.td>
                    @foreach($days as $day)
                        @php $a = $inv->availableOn($day); @endphp
                        <x-ui.td :label="Carbon::parse($day)->isoFormat('dd D.M.')">
                            @if($a === true) <x-ui.badge tone="success">ja</x-ui.badge>
                            @elseif($a === false) <x-ui.badge tone="danger">nein</x-ui.badge>
                            @else <span class="text-gray-600">–</span> @endif
                            @if($inv->commentOn($day)) <span class="block text-xs text-gray-700">{{ $inv->commentOn($day) }}</span> @endif
                        </x-ui.td>
                    @endforeach
                    <x-ui.td label="Positionen" hide="md" class="text-sm text-gray-700">{{ collect($inv->positions ?? [])->map(fn($p) => CompetitionOfficialRequest::POSITIONS[$p] ?? $p)->implode(', ') }}</x-ui.td>
                    <x-ui.td label="Anmerkung" hide="lg" class="text-sm text-gray-700">{{ $inv->comment }}</x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    {{-- Zuordnung durch den Kampfrichterobmann und Freigabe zur Meldung --}}
    @if($req)
        @php
            $byDay     = $req->sessionsByDay();
            $sessions  = collect($byDay)->flatMap(fn($nrs, $day) => collect($nrs)->map(fn($nr) => ['nr' => $nr, 'day' => $day]))->sortBy('nr')->values();
            $available = $invitees->filter(fn($i) => $i->availableAnyDay());
            $final     = (bool) $req->finalized_at;
            $assigned  = $invitees->flatMap(fn($i) => $i->assignments);
        @endphp
        <x-ui.card title="Zuordnung und Meldung">
            @error('assign') <x-ui.alert tone="error" class="mb-4">{{ $message }}</x-ui.alert> @enderror

            @if($final)
                <x-ui.alert tone="success" class="mb-4">
                    Freigegeben am {{ $req->finalized_at->format('d.m.Y, H:i') }} Uhr{{ $req->finalizer ? ' von ' . $req->finalizer->firstname . ' ' . $req->finalizer->lastname : '' }}:
                    {{ $assigned->pluck('competition_official_invitee_id')->unique()->count() }} Kampfrichter mit {{ $assigned->count() }} Einsätzen stehen in der
                    <a href="{{ route('admin.competitions.dsv7.meldedatei', $competition) }}" class="font-semibold underline">Meldedatei</a> (KARIMELDUNG).
                </x-ui.alert>
            @elseif(!$req->readyToAssign())
                <x-ui.alert tone="info" class="mb-4">
                    Gemeldet werden kann, sobald alle geantwortet haben – oder du die Abfrage schließt.
                    Die Zuordnung lässt sich schon jetzt vorbereiten.
                </x-ui.alert>
            @endif

            @if($available->isEmpty())
                <p class="text-sm text-gray-600">Noch niemand hat für einen Tag zugesagt.</p>
            @else
                <form method="POST" action="{{ route('admin.competitions.officials.assign', $competition) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-ui.table :card="false" caption="Zuordnung der Kampfrichter zu den Abschnitten" dense>
                        <x-slot:head>
                            <x-ui.th dense>Name</x-ui.th>
                            @foreach($sessions as $s)
                                <x-ui.th dense>Abschnitt {{ $s['nr'] }}<span class="block font-normal normal-case">{{ Carbon::parse($s['day'])->isoFormat('dd D.M.') }}</span></x-ui.th>
                            @endforeach
                            <x-ui.th dense>Gruppe</x-ui.th>
                        </x-slot:head>
                                @foreach($available as $inv)
                                    @php
                                        $name   = trim($inv->user?->firstname . ' ' . $inv->user?->lastname);
                                        $wishes = $inv->positions ?? [];
                                        $byNr   = $inv->assignments->keyBy('session_number');
                                    @endphp
                                    <tr>
                                        <x-ui.td dense strong class="whitespace-nowrap">
                                            {{ $name }}
                                            @if($wishes)<span class="block text-xs font-normal text-gray-600">Wunsch: {{ implode(', ', $wishes) }}</span>@endif
                                        </x-ui.td>
                                        @foreach($sessions as $s)
                                            <x-ui.td dense>
                                                @if($inv->availableOn($s['day']) === true)
                                                    <select aria-label="{{ $name }}, Abschnitt {{ $s['nr'] }}" name="rows[{{ $inv->id }}][sessions][{{ $s['nr'] }}]" @disabled($final)
                                                            class="w-full min-w-[8rem] rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                                                        <option value="">– nicht eingesetzt –</option>
                                                        @foreach($wishes as $code)
                                                            <option value="{{ $code }}" @selected($byNr->get($s['nr'])?->position === $code)>★ {{ CompetitionOfficialRequest::POSITIONS[$code] ?? $code }}</option>
                                                        @endforeach
                                                        @foreach(CompetitionOfficialRequest::POSITIONS as $code => $label)
                                                            @continue(in_array($code, $wishes, true))
                                                            <option value="{{ $code }}" @selected($byNr->get($s['nr'])?->position === $code)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if($inv->commentOn($s['day']))<span class="block text-xs text-gray-600 mt-1">{{ $inv->commentOn($s['day']) }}</span>@endif
                                                @else
                                                    <span class="text-gray-600" aria-label="nicht verfügbar">–</span>
                                                @endif
                                            </x-ui.td>
                                        @endforeach
                                        <x-ui.td dense>
                                            <select aria-label="Kampfrichtergruppe {{ $name }}" name="rows[{{ $inv->id }}][group]" @disabled($final)
                                                    class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                                                @foreach(CompetitionOfficialRequest::KARI_GROUPS as $code => $label)
                                                    <option value="{{ $code }}" @selected($inv->group === $code)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </x-ui.td>
                                    </tr>
                                @endforeach
                    </x-ui.table>
                    <p class="text-xs text-gray-600">★ = Wunschposition. Die Gruppe wird aus der ersten Position vorgeschlagen und steht so in der KARIMELDUNG.</p>
                    @unless($final)
                        <x-ui.button type="submit" variant="secondary">Zuordnung speichern</x-ui.button>
                    @endunless
                </form>

                {{-- Besetzung je Abschnitt --}}
                @if($assigned->isNotEmpty())
                    <div class="mt-6 border-t border-gray-100 pt-4">
                        <h3 class="text-sm font-semibold text-gray-800 mb-2">Besetzung je Abschnitt</h3>
                        <ul class="space-y-1 text-sm text-gray-700">
                            @foreach($sessions as $s)
                                @php $inSession = $assigned->where('session_number', $s['nr']); @endphp
                                <li>
                                    <span class="font-medium text-gray-900">Abschnitt {{ $s['nr'] }}:</span>
                                    @if($inSession->isEmpty()) <span class="text-amber-800">noch niemand</span>
                                    @else {{ $inSession->countBy('position')->map(fn($n, $code) => $n . '× ' . (CompetitionOfficialRequest::POSITIONS[$code] ?? $code))->implode(', ') }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap gap-2">
                    @if($final)
                        <form method="POST" action="{{ route('admin.competitions.officials.unfinalize', $competition) }}"
                              data-confirm="Freigabe zurücknehmen? Bis zur neuen Freigabe stehen keine Kampfrichter in der Meldedatei." data-confirm-label="Zurücknehmen">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="secondary">Freigabe zurücknehmen</x-ui.button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.competitions.officials.finalize', $competition) }}"
                              data-confirm="Zuordnung als Kampfrichter-Meldung freigeben? Die Eingesetzten stehen dann in der Meldedatei." data-confirm-label="Freigeben">
                            @csrf
                            <x-ui.button type="submit" :disabled="!$req->readyToAssign() || $assigned->isEmpty()">Zur Meldung freigeben</x-ui.button>
                        </form>
                    @endif
                </div>
            @endif
        </x-ui.card>
    @endif

    {{-- Abfrage starten bzw. weitere anfragen --}}
    <x-ui.card :title="$req ? 'Weitere Kampfrichter anfragen' : 'Kampfrichter-Abfrage starten'" :collapsible="(bool) $req" storage-key="officials-invite" :open="!$req">
        <form method="POST" action="{{ route('admin.competitions.officials.store', $competition) }}" class="space-y-5">
            @csrf
            @error('user_ids') <x-ui.alert tone="error">{{ $message }}</x-ui.alert> @enderror
            <label class="flex items-start gap-3 text-sm text-gray-800">
                <input type="checkbox" name="all_officials" value="1" @checked(old('all_officials', !$req))
                       class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary/30">
                <span>Alle Kampfrichter anfragen <span class="text-gray-600">({{ $officialCount }} mit Rolle Kampfrichter)</span></span>
            </label>
            @include('calendar.events._user-picker', ['name' => 'user_ids', 'label' => 'Gezielt einzelne Personen', 'people' => $officialCandidates])
            @unless($req)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Rückmeldung bis" name="deadline" type="date" :value="$competition->meldeschluss?->format('Y-m-d')" />
                </div>
                <x-ui.field label="Nachricht an die Kampfrichter" name="message" as="textarea" rows="3" maxlength="2000"
                            placeholder="z. B. Wir müssen je Abschnitt vier Kampfrichter stellen." />
            @endunless
            <x-ui.button type="submit">{{ $req ? 'Anfragen' : 'Abfrage starten' }}</x-ui.button>
        </form>
    </x-ui.card>
@endif

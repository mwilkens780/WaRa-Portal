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

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

    {{-- Bedarf → Besetzung → Freigabe (Kampfrichterobmann, Auftrag Martin 07.10.2026) --}}
    @if($req)
        @php
            $byDay     = $req->sessionsByDay();
            $sessions  = collect($byDay)->flatMap(fn($nrs, $day) => collect($nrs)->map(fn($nr) => ['nr' => $nr, 'day' => $day]))->sortBy('nr')->values();
            $final     = (bool) $req->finalized_at;
            $needs     = $req->needs;
            $needGrid  = $needs->groupBy('session_number')->map(fn($g) => $g->pluck('count', 'position'));
            $assigned  = $invitees->flatMap(fn($i) => $i->assignments);
            $vacancies = $req->vacancies();
            $openTotal = collect($vacancies)->flatten()->sum();
            $nameOf    = fn($i) => trim($i->user?->firstname . ' ' . $i->user?->lastname);
        @endphp

        @error('assign') <x-ui.alert tone="error">{{ $message }}</x-ui.alert> @enderror

        {{-- 1. Bedarf --}}
        <x-ui.card title="1. Gesuchte Positionen je Abschnitt" collapsible storage-key="officials-needs" :open="$needs->isEmpty()"
                   :meta="$needs->sum('count') . ' gesucht'">
            <p class="text-sm text-gray-600 mb-3">Wie viele Kampfrichter je Position und Abschnitt der Verein stellen muss (z. B. laut Ausschreibung). Daraus entstehen die offenen Positionen.</p>
            <form method="POST" action="{{ route('admin.competitions.officials.needs', $competition) }}" class="space-y-4">
                @csrf @method('PUT')
                <x-ui.table :card="false" caption="Gesuchte Positionen je Abschnitt" dense>
                    <x-slot:head>
                        <x-ui.th dense>Position</x-ui.th>
                        @foreach($sessions as $s)
                            <x-ui.th dense align="center">Abschnitt {{ $s['nr'] }}<span class="block font-normal normal-case">{{ Carbon::parse($s['day'])->isoFormat('dd D.M.') }}</span></x-ui.th>
                        @endforeach
                    </x-slot:head>
                    @foreach(CompetitionOfficialRequest::POSITIONS as $code => $label)
                        <tr>
                            <x-ui.td dense>{{ $label }} <span class="text-gray-600">({{ $code }})</span></x-ui.td>
                            @foreach($sessions as $s)
                                <x-ui.td dense align="center">
                                    <input type="number" min="0" max="20" inputmode="numeric" @disabled($final)
                                           aria-label="{{ $label }}, Abschnitt {{ $s['nr'] }}"
                                           name="need[{{ $s['nr'] }}][{{ $code }}]" value="{{ $needGrid[$s['nr']][$code] ?? '' }}"
                                           class="w-16 rounded-lg border border-gray-300 px-2 py-1 text-center text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                                </x-ui.td>
                            @endforeach
                        </tr>
                    @endforeach
                </x-ui.table>
                @unless($final)<x-ui.button type="submit" variant="secondary">Bedarf speichern</x-ui.button>@endunless
            </form>
        </x-ui.card>

        {{-- 2. Besetzung aus den Rückmeldungen --}}
        <x-ui.card title="2. Gesuchte Positionen besetzen" :meta="$needs->isEmpty() ? null : ($openTotal ? $openTotal . ' offen' : 'komplett')">
            @if($final)
                <x-ui.alert tone="success" class="mb-4">
                    Freigegeben am {{ $req->finalized_at->format('d.m.Y, H:i') }} Uhr{{ $req->finalizer ? ' von ' . $req->finalizer->firstname . ' ' . $req->finalizer->lastname : '' }}:
                    {{ $assigned->pluck('competition_official_invitee_id')->unique()->count() }} Kampfrichter mit {{ $assigned->count() }} Einsätzen stehen in der
                    <a href="{{ route('admin.competitions.dsv7.meldedatei', $competition) }}" class="font-semibold underline">Meldedatei</a> (KARIMELDUNG).
                </x-ui.alert>
            @elseif(!$req->readyToAssign())
                <x-ui.alert tone="info" class="mb-4">Gemeldet werden kann, sobald alle geantwortet haben – oder du die Abfrage schließt. Die Besetzung lässt sich schon jetzt vorbereiten.</x-ui.alert>
            @endif

            @if($needs->isEmpty())
                <p class="text-sm text-gray-600">Zuerst unter 1. die gesuchten Positionen festlegen.</p>
            @else
                <form method="POST" action="{{ route('admin.competitions.officials.assign', $competition) }}" class="space-y-5">
                    @csrf @method('PUT')
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach($sessions as $s)
                            @php
                                $sNeeds = $needs->where('session_number', $s['nr']);
                                $open   = array_sum($vacancies[$s['nr']] ?? []);
                                $pool   = $invitees->filter(fn($i) => $i->availableOn($s['day']) === true);
                            @endphp
                            @continue($sNeeds->isEmpty())
                            <fieldset class="rounded-lg border border-gray-200 p-4 space-y-3">
                                <legend class="px-1 text-sm font-semibold text-gray-900">
                                    Abschnitt {{ $s['nr'] }} · {{ Carbon::parse($s['day'])->isoFormat('dd D.M.') }}
                                    <x-ui.badge :tone="$open ? 'warning' : 'success'" class="ml-1">{{ $open ? $open . ' offen' : 'komplett' }}</x-ui.badge>
                                </legend>
                                @if($pool->isEmpty())
                                    <p class="text-sm text-amber-800">Für diesen Tag hat niemand zugesagt.</p>
                                @endif
                                @foreach($sNeeds as $need)
                                    @php
                                        $current = $invitees->filter(fn($i) => $i->assignments->contains(fn($a) => $a->session_number === $s['nr'] && $a->position === $need->position))->values();
                                        $sorted  = $pool->sortBy(fn($i) => [in_array($need->position, $i->positions ?? [], true) ? 0 : 1, $i->user?->lastname]);
                                        $label   = CompetitionOfficialRequest::POSITIONS[$need->position] ?? $need->position;
                                    @endphp
                                    @for($k = 0; $k < $need->count; $k++)
                                        @php $sel = old("slots.{$s['nr']}.{$need->position}.{$k}", $current[$k]->id ?? ''); @endphp
                                        <div>
                                            <label for="slot-{{ $s['nr'] }}-{{ $need->position }}-{{ $k }}" class="block text-xs font-medium text-gray-700">{{ $label }}{{ $need->count > 1 ? ' ' . ($k + 1) : '' }}</label>
                                            <select id="slot-{{ $s['nr'] }}-{{ $need->position }}-{{ $k }}" name="slots[{{ $s['nr'] }}][{{ $need->position }}][]" @disabled($final)
                                                    class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 {{ $sel ? 'border-gray-300' : 'border-amber-300 bg-amber-50' }}">
                                                <option value="">– offen –</option>
                                                @foreach($sorted as $i)
                                                    @php $quals = $i->user?->officialQualifications?->pluck('title')->unique()->implode(', '); @endphp
                                                    <option value="{{ $i->id }}" @selected((string) $sel === (string) $i->id)>
                                                        {{ in_array($need->position, $i->positions ?? [], true) ? '★ ' : '' }}{{ $nameOf($i) }}{{ $quals ? ' – ' . $quals : '' }}{{ $i->commentOn($s['day']) ? ' (' . $i->commentOn($s['day']) . ')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endfor
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-600">★ = hat sich diese Position gewünscht. Zur Auswahl stehen nur Personen, die für den Tag zugesagt haben; hinter dem Namen stehen ihre Qualifikationen.</p>

                    {{-- Kampfrichtergruppe je eingesetzter Person (KARIMELDUNG) --}}
                    @php $people = $invitees->filter(fn($i) => $i->assignments->isNotEmpty()); @endphp
                    @if($people->isNotEmpty())
                        <div class="border-t border-gray-100 pt-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-2">Kampfrichtergruppe für die Meldung</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($people as $i)
                                    <x-ui.field :label="$nameOf($i)" name="groups[{{ $i->id }}]" :id="'group-' . $i->id" as="select" :disabled="$final">
                                        @foreach(CompetitionOfficialRequest::KARI_GROUPS as $code => $glabel)
                                            <option value="{{ $code }}" @selected($i->group === $code)>{{ $glabel }}</option>
                                        @endforeach
                                    </x-ui.field>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @unless($final)<x-ui.button type="submit">Besetzung speichern</x-ui.button>@endunless
                </form>
            @endif
        </x-ui.card>

        {{-- 3. Freigabe zur Meldung --}}
        @if($needs->isNotEmpty())
            <x-ui.card title="3. Melden">
                @if($final)
                    <form method="POST" action="{{ route('admin.competitions.officials.unfinalize', $competition) }}"
                          data-confirm="Freigabe zurücknehmen? Bis zur neuen Freigabe stehen keine Kampfrichter in der Meldedatei." data-confirm-label="Zurücknehmen">
                        @csrf @method('DELETE')
                        <x-ui.button type="submit" variant="secondary">Freigabe zurücknehmen</x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.competitions.officials.finalize', $competition) }}" class="space-y-3"
                          data-confirm="Besetzung als Kampfrichter-Meldung freigeben? Die Eingesetzten stehen dann in der Meldedatei." data-confirm-label="Freigeben">
                        @csrf
                        <p class="text-sm text-gray-700">
                            @if($openTotal) Noch <strong>{{ $openTotal }}</strong> gesuchte Position{{ $openTotal === 1 ? ' ist' : 'en sind' }} offen.
                            @else Alle gesuchten Positionen sind besetzt. @endif
                        </p>
                        @if($openTotal)
                            <label class="flex items-start gap-3 text-sm text-gray-800">
                                <input type="checkbox" name="despite_vacancies" value="1" class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary/30">
                                <span>Trotz offener Positionen melden</span>
                            </label>
                        @endif
                        <x-ui.button type="submit" :disabled="!$req->readyToAssign() || $assigned->isEmpty()">Zur Meldung freigeben</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        @endif
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

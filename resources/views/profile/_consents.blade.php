{{--
    Einwilligungen Ernaehrungsberatung / Sportmedizin fuer eine Person.

    Erwartet: $person (User), $forChild (bool - Eltern willigen fuer ihr Kind ein),
              $readonly (bool - Kind sieht, was die Eltern entschieden haben)

    Echte Checkboxen statt Klick-Divs: per Tastatur schaltbar und fuer
    Screenreader als Schalter mit Zustand erkennbar.
--}}
@php
    $wessen = $forChild ? 'die Daten von ' . $person->firstname : 'meine Daten';
    $idp    = 'consent-' . $person->id;
    $items  = [
        'opt_nutrition' => [
            'title' => 'Ernährungsberatung',
            'text'  => "Ich stimme zu, dass {$wessen} im Rahmen der Ernährungsberatung erfasst und Messwerte "
                     . 'gespeichert werden dürfen. Einsehen dürfen sie die Ernährungsberatung, die Trainer '
                     . ($forChild ? 'der Trainingsgruppen' : 'meiner Trainingsgruppen') . ' und die Administratoren des Portals.',
            'on'    => 'peer-checked:bg-green-500',
        ],
        'opt_sports_medicine' => [
            'title' => 'Sportmedizinische Untersuchung',
            'text'  => 'Ich stimme zu, dass ' . ($forChild ? 'die sportmedizinischen Untersuchungsergebnisse von ' . $person->firstname : 'meine sportmedizinischen Untersuchungsergebnisse')
                     . ' im Rahmen der Leistungssporteignung erfasst werden dürfen. Einsehen dürfen sie der Teamarzt, die Trainer '
                     . ($forChild ? 'der Trainingsgruppen' : 'meiner Trainingsgruppen') . ' und die Administratoren des Portals.',
            'on'    => 'peer-checked:bg-primary',
        ],
    ];
@endphp

<div class="space-y-4">
    @foreach($items as $field => $item)
        @php $checked = (bool) old($field, $person->{$field}); @endphp
        <label for="{{ $idp }}-{{ $field }}"
               class="flex items-start gap-4 p-4 border rounded-xl {{ $readonly ? 'bg-gray-50 cursor-not-allowed' : 'cursor-pointer hover:border-gray-300' }} {{ $checked ? 'border-gray-300' : 'border-gray-200' }}">
            <span class="relative flex-shrink-0 mt-0.5">
                @unless($readonly)
                    <input type="hidden" name="{{ $field }}" value="0">
                @endunless
                <input type="checkbox" role="switch" id="{{ $idp }}-{{ $field }}" name="{{ $field }}" value="1"
                       @checked($checked) @disabled($readonly)
                       aria-describedby="{{ $idp }}-{{ $field }}-text"
                       class="peer sr-only">
                {{-- Sichtbarer Schalter; Zustand kommt aus der Checkbox (peer) --}}
                <span aria-hidden="true"
                      class="block w-10 h-6 rounded-full bg-gray-300 transition-colors {{ $item['on'] }}
                             peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2
                             after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow
                             after:transition-transform peer-checked:after:translate-x-4"></span>
            </span>
            <span>
                <span class="block text-sm font-semibold text-gray-800">
                    {{ $item['title'] }}
                    @if($readonly)
                        {{-- Nur in der Ansicht: der Schalter ist gesperrt, der Text sagt den Stand --}}
                        <span class="ml-1 text-xs font-medium {{ $checked ? 'text-green-700' : 'text-gray-500' }}">{{ $checked ? 'erteilt' : 'nicht erteilt' }}</span>
                    @endif
                </span>
                <span id="{{ $idp }}-{{ $field }}-text" class="block text-xs text-gray-500 mt-0.5">{{ $item['text'] }}</span>
            </span>
        </label>
    @endforeach
</div>

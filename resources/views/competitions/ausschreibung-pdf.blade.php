<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.5; }
  h1 { font-size: 18pt; color: #1B5EAB; margin-bottom: 4px; }
  h2 { font-size: 12pt; color: #1B5EAB; margin: 16px 0 6px; border-bottom: 1px solid #1B5EAB; padding-bottom: 2px; }
  h3 { font-size: 10pt; font-weight: bold; margin: 10px 0 4px; }
  .header { text-align: center; padding: 20px 0 12px; border-bottom: 2px solid #1B5EAB; margin-bottom: 16px; }
  .subtitle { font-size: 11pt; color: #555; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9pt; }
  th { background: #1B5EAB; color: #fff; padding: 4px 8px; text-align: left; }
  td { padding: 3px 8px; border-bottom: 1px solid #e5e7eb; }
  tr:nth-child(even) td { background: #f8f9fa; }
  .meta-grid { display: table; width: 100%; margin-bottom: 16px; }
  .meta-row { display: table-row; }
  .meta-label { display: table-cell; width: 38%; font-weight: bold; padding: 2px 8px 2px 0; color: #555; font-size: 9pt; }
  .meta-value { display: table-cell; padding: 2px 0; font-size: 9pt; }
  .section { margin-bottom: 14px; }
  .badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 1px 6px; border-radius: 4px; font-size: 8pt; }
  .footer { margin-top: 24px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 8pt; color: #777; }
  .page-break { page-break-after: always; }
</style>
</head>
<body>

{{-- Header --}}
<div class="header">
  <h1>Ausschreibung</h1>
  <div class="subtitle">{{ $competition->name }}</div>
  @if($competition->date)
    <div class="subtitle">{{ $competition->date->format('d.m.Y') }}
      @if($competition->date_end && $competition->date_end->ne($competition->date))
        – {{ $competition->date_end->format('d.m.Y') }}
      @endif
    </div>
  @endif
  @if($competition->location)
    <div class="subtitle">{{ $competition->location }}</div>
  @endif
</div>

{{-- Eckdaten --}}
<div class="section">
  <h2>Veranstaltungsdaten</h2>
  <div class="meta-grid">
    @if($competition->organizer)
    <div class="meta-row">
      <div class="meta-label">Veranstalter</div>
      <div class="meta-value">{{ $competition->organizer }}</div>
    </div>
    @endif
    @if($competition->ausrichter && $competition->ausrichter !== $competition->organizer)
    <div class="meta-row">
      <div class="meta-label">Ausrichter</div>
      <div class="meta-value">{{ $competition->ausrichter }}</div>
    </div>
    @endif
    @php $venue = $competition->venue_details ?? []; @endphp
    @if(!empty($venue['name']))
    <div class="meta-row">
      <div class="meta-label">Wettkampfstätte</div>
      <div class="meta-value">{{ $venue['name'] }}@if(!empty($venue['street'])), {{ $venue['street'] }}@endif@if(!empty($venue['city'])), {{ $venue['city'] }}@endif</div>
    </div>
    @endif
    <div class="meta-row">
      <div class="meta-label">Bahnlänge</div>
      <div class="meta-value">{{ $competition->course_label }}</div>
    </div>
    @if(!empty($venue['lanes_heats']))
    <div class="meta-row">
      <div class="meta-label">Bahnen (Vorlauf / Finale)</div>
      <div class="meta-value">{{ $venue['lanes_heats'] }} / {{ $venue['lanes_finals'] ?? '8' }}</div>
    </div>
    @endif
    @if(!empty($venue['water_temp_c']))
    <div class="meta-row">
      <div class="meta-label">Wassertemperatur</div>
      <div class="meta-value">{{ $venue['water_temp_c'] }} °C</div>
    </div>
    @endif
    @if(!empty($venue['timing']))
    <div class="meta-row">
      <div class="meta-label">Zeitmessung</div>
      <div class="meta-value">{{ $venue['timing'] }}</div>
    </div>
    @endif
  </div>
</div>

{{-- Meldung --}}
@php $entry = $competition->contact_info['entry'] ?? []; @endphp
@if(!empty($entry) || $competition->meldeschluss)
<div class="section">
  <h2>Meldung</h2>
  <div class="meta-grid">
    @if($competition->meldeschluss)
    <div class="meta-row">
      <div class="meta-label">Meldeschluss</div>
      <div class="meta-value"><strong>{{ $competition->meldeschluss->format('d.m.Y') }}</strong></div>
    </div>
    @endif
    @if(!empty($entry['contact_email']))
    <div class="meta-row">
      <div class="meta-label">Meldeanschrift</div>
      <div class="meta-value">{{ $entry['contact_name'] ?? '' }} &lt;{{ $entry['contact_email'] }}&gt;</div>
    </div>
    @endif
    @if(!empty($entry['fee_individual_cents']))
    <div class="meta-row">
      <div class="meta-label">Meldegeld Einzel</div>
      <div class="meta-value">{{ number_format($entry['fee_individual_cents'] / 100, 2, ',', '.') }} €</div>
    </div>
    @endif
    @if(!empty($entry['payment_iban']))
    <div class="meta-row">
      <div class="meta-label">Bankverbindung</div>
      <div class="meta-value">{{ $entry['payment_bank'] ?? '' }}<br>IBAN: {{ $entry['payment_iban'] }} · BIC: {{ $entry['payment_bic'] ?? '' }}</div>
    </div>
    @endif
    @if(!empty($entry['payment_reference']))
    <div class="meta-row">
      <div class="meta-label">Verwendungszweck</div>
      <div class="meta-value">{{ $entry['payment_reference'] }}</div>
    </div>
    @endif
  </div>
</div>
@endif

{{-- Wettkampffolge --}}
@if($sessions->isNotEmpty())
<div class="section">
  <h2>Wettkampffolge</h2>
  @foreach($sessions as $sessionNum => $sessionEvents)
    @php $first = $sessionEvents->first(); @endphp
    <h3>
      Abschnitt {{ $sessionNum }}
      @if($first->session_name) – {{ $first->session_name }} @endif
      @if($first->session_date) ({{ $first->session_date->format('d.m.Y') }}) @endif
    </h3>
    <table>
      <thead>
        <tr>
          <th style="width:8%">WK</th>
          <th style="width:40%">Disziplin</th>
          <th style="width:22%">Wertungsklasse</th>
          <th style="width:15%">Geschlecht</th>
          <th style="width:15%">Pflichtzeit</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sessionEvents->sortBy('event_number') as $event)
        <tr>
          <td>{{ $event->event_number }}</td>
          <td>{{ $event->distance }}m {{ $event->discipline_label }}</td>
          <td>{{ $event->age_group ?: 'Offene Klasse' }}</td>
          <td>{{ $event->gender_label }}</td>
          <td>{{ $event->formatted_qualifying_time ?? '–' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach
</div>
@endif

{{-- Allgemeine Bestimmungen --}}
@if($competition->description)
<div class="section">
  <h2>Allgemeine Bestimmungen</h2>
  <p style="font-size:9pt; white-space: pre-wrap;">{{ $competition->description }}</p>
</div>
@endif

{{-- Sonderregeln --}}
@php $rules = $competition->announcement_data['special_rules'] ?? []; @endphp
@if(count($rules))
<div class="section">
  <h2>Besondere Bestimmungen</h2>
  @foreach($rules as $rule)
    <h3>{{ $rule['title'] ?? '' }}
      @if(!empty($rule['is_deviation_from_wb']))
        <span class="badge">Abweichung WB</span>
      @endif
    </h3>
    <p style="font-size:9pt; margin-bottom:8px;">{{ $rule['text'] ?? '' }}</p>
  @endforeach
</div>
@endif

{{-- Pflichtzeiten-Tabelle --}}
@if($pflichtzeiten->isNotEmpty())
<div class="section page-break">
  <h2>Pflichtzeiten / Qualifikationszeiten</h2>
  @php
    $grouped = $pflichtzeiten->groupBy(fn($e) => $e->discipline . '_' . $e->distance . '_' . $e->gender);
  @endphp
  <table>
    <thead>
      <tr>
        <th>Strecke</th>
        <th>Disziplin</th>
        <th>Geschlecht</th>
        <th>Wertungsklasse</th>
        <th>Pflichtzeit</th>
        <th>Meldegeld</th>
      </tr>
    </thead>
    <tbody>
      @foreach($pflichtzeiten->sortBy('distance')->sortBy('discipline') as $event)
      <tr>
        <td>{{ $event->distance }}m</td>
        <td>{{ $event->discipline_label }}</td>
        <td>{{ $event->gender_label }}</td>
        <td>{{ $event->age_group ?: 'OK' }}</td>
        <td>{{ $event->formatted_qualifying_time ?? '–' }}</td>
        <td>{{ $event->meldegeld ? number_format($event->meldegeld, 2, ',', '.') . ' €' : '–' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

<div class="footer">
  <p>Ausschreibung generiert von WaRa-Portal · SG Wasserratten Norderstedt e.V. · {{ now()->format('d.m.Y') }}</p>
</div>

</body>
</html>

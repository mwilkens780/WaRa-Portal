<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10pt;
    color: #1a1a1a;
    line-height: 1.6;
    background: #fff;
  }

  /* ── Header ── */
  .header {
    background: #1B5EAB;
    color: #fff;
    padding: 18px 24px 14px;
    margin-bottom: 20px;
  }
  .header-club {
    font-size: 8pt;
    opacity: 0.75;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 4px;
  }
  .header-title {
    font-size: 16pt;
    font-weight: bold;
    line-height: 1.2;
  }
  .header-meta {
    font-size: 9pt;
    opacity: 0.85;
    margin-top: 4px;
  }

  /* ── Body text (Quill HTML) ── */
  .text-section {
    padding: 0 24px;
    margin-bottom: 22px;
  }
  .text-section p   { margin-bottom: 8px; }
  .text-section h1  { font-size: 14pt; font-weight: bold; color: #1B5EAB; margin: 12px 0 6px; }
  .text-section h2  { font-size: 12pt; font-weight: bold; color: #1B5EAB; margin: 10px 0 5px; }
  .text-section h3  { font-size: 11pt; font-weight: bold; margin: 8px 0 4px; }
  .text-section ul,
  .text-section ol  { margin: 6px 0 8px 18px; }
  .text-section li  { margin-bottom: 2px; }
  .text-section strong { font-weight: bold; }
  .text-section em     { font-style: italic; }

  /* ── Divider ── */
  .divider {
    border: none;
    border-top: 2px solid #1B5EAB;
    margin: 18px 24px;
    opacity: 0.2;
  }

  /* ── Section heading ── */
  .section-heading {
    padding: 6px 24px 10px;
    font-size: 9pt;
    font-weight: bold;
    color: #1B5EAB;
    text-transform: uppercase;
    letter-spacing: 0.07em;
  }

  /* ── Results table ── */
  .results-table {
    width: calc(100% - 48px);
    margin: 0 24px;
    border-collapse: collapse;
    font-size: 8.5pt;
  }
  .results-table thead tr {
    background: #1B5EAB;
    color: #fff;
  }
  .results-table th {
    padding: 5px 8px;
    text-align: left;
    font-weight: 600;
    font-size: 7.5pt;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }
  .results-table th.right { text-align: right; }
  .results-table td {
    padding: 4px 8px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
  }
  .results-table tr:nth-child(even) td { background: #f8fafc; }
  .results-table .discipline-header td {
    background: #e8f0fb;
    color: #1B5EAB;
    font-weight: bold;
    font-size: 8pt;
    padding: 4px 8px;
    border-top: 1px solid #c5d8f5;
    border-bottom: 1px solid #c5d8f5;
  }
  .mono    { font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; color: #1B5EAB; }
  .dns     { opacity: 0.45; }
  .badge   { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7pt; font-weight: bold; margin-right: 2px; }
  .b-pb    { background: #dcfce7; color: #166534; }
  .b-final { background: #f3e8ff; color: #6b21a8; }
  .b-vr    { background: #1B5EAB; color: #fff; }
  .b-lr    { background: #f59e0b; color: #fff; }
  .b-dns   { background: #e5e7eb; color: #374151; }
  .place-top { font-weight: bold; color: #b45309; }
  .right   { text-align: right; }

  /* ── Footer ── */
  .footer {
    margin-top: 28px;
    padding: 8px 24px;
    border-top: 1px solid #e5e7eb;
    font-size: 7.5pt;
    color: #9ca3af;
    display: flex;
    justify-content: space-between;
  }
</style>
</head>
<body>

{{-- ── Header ─────────────────────────────────────────── --}}
<div class="header">
  <div class="header-club">SG Wasserratten Norderstedt e.V. · Wettkampfauswertung</div>
  <div class="header-title">{{ $competition->name }}</div>
  <div class="header-meta">
    {{ $competition->date_range }}
    @if($competition->location) &nbsp;·&nbsp; {{ $competition->location }} @endif
    @if($competition->level) &nbsp;·&nbsp; {{ $competition->level_label }} @endif
  </div>
</div>

{{-- ── Auswertungstext ──────────────────────────────────── --}}
@if($analysisHtml)
<div class="text-section">
  {!! $analysisHtml !!}
</div>
<hr class="divider">
@endif

{{-- ── Ergebnistabelle ─────────────────────────────────── --}}
<div class="section-heading">Ergebnisse unserer Schwimmer</div>

<table class="results-table">
  <thead>
    <tr>
      <th>Schwimmer</th>
      <th>Disziplin</th>
      <th class="right">Zeit</th>
      <th>Platz</th>
      <th>Auszeichnungen</th>
    </tr>
  </thead>
  <tbody>
    @foreach($results as $key => $group)
      @php $first = $group->first(); @endphp
      <tr class="discipline-header">
        <td colspan="5">{{ $first->distance }}m {{ $first->discipline_label }}</td>
      </tr>
      @foreach($group->sortBy(fn($s) => $s->is_dns ? PHP_INT_MAX : $s->time_ms) as $swim)
        <tr class="{{ $swim->is_dns ? 'dns' : '' }}">
          <td>{{ $swim->user?->name }}</td>
          <td style="font-size:8pt;color:#6b7280;">
            {{ $swim->distance }}m {{ $swim->discipline_label }}
            @if($swim->gender) <span style="color:#9ca3af">({{ $swim->gender === 'M' ? 'm' : 'w' }})</span> @endif
          </td>
          <td class="right">
            @if(!$swim->is_dns)
              <span class="mono">{{ $swim->formatted_time }}</span>
            @elseif($swim->notes)
              <span class="badge b-dns">{{ $swim->notes }}</span>
            @endif
          </td>
          <td>
            @if(!empty($swim->placements))
              @foreach($swim->placements as $p)
                <span class="{{ $p->placement <= 3 ? 'place-top' : '' }}" style="font-size:8pt;">
                  @if($p->age_group)<span style="color:#9ca3af;font-size:7pt;">{{ $p->age_group }}: </span>@endif
                  Platz {{ $p->placement }}
                </span>
                @if(!$loop->last) &nbsp;·&nbsp; @endif
              @endforeach
            @else
              <span style="color:#d1d5db;">–</span>
            @endif
          </td>
          <td>
            @if($swim->is_final)          <span class="badge b-final">Finale</span> @endif
            @if($swim->is_personal_best && !$swim->is_dns) <span class="badge b-pb">PB</span> @endif
            @if($swim->breaks_vereinsrekord) <span class="badge b-vr">VR</span> @endif
            @if($swim->breaks_landesrekord)  <span class="badge b-lr">LR</span> @endif
          </td>
        </tr>
      @endforeach
    @endforeach
  </tbody>
</table>

{{-- ── Footer ──────────────────────────────────────────── --}}
<div class="footer">
  <span>SG Wasserratten Norderstedt e.V.</span>
  <span>Erstellt am {{ now()->format('d.m.Y') }}</span>
</div>

</body>
</html>

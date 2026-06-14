<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $competition->name }} – Auswertung</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  /* ── Basis ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #1a1a1a;
    background: #f0f2f5;
    padding: 24px 0 60px;
  }

  /* ── Aktions-Leiste (nicht im PDF) ── */
  .action-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: #1B5EAB;
    color: #fff;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  }
  .action-bar span { flex: 1; font-size: 14px; font-weight: 600; }
  .btn {
    padding: 7px 18px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: opacity 0.15s;
  }
  .btn:hover { opacity: 0.88; }
  .btn-primary { background: #fff; color: #1B5EAB; }
  .btn-secondary { background: rgba(255,255,255,0.15); color: #fff; }
  .btn:disabled { opacity: 0.5; cursor: not-allowed; }

  /* ── A4-Seite ── */
  #pdf-content {
    width: 210mm;
    min-height: 297mm;
    margin: 48px auto 0;
    background: #fff;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    padding: 0 0 20mm;
  }

  /* ── Header ── */
  .pdf-header {
    background: #1B5EAB;
    color: #fff;
    padding: 20px 24px 16px;
  }
  .pdf-header-club {
    font-size: 9px;
    opacity: 0.75;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 5px;
  }
  .pdf-header-title {
    font-size: 20px;
    font-weight: 700;
    line-height: 1.2;
  }
  .pdf-header-meta {
    font-size: 12px;
    opacity: 0.85;
    margin-top: 5px;
  }

  /* ── Auswertungstext ── */
  .pdf-text {
    padding: 20px 24px 16px;
    line-height: 1.7;
    color: #222;
  }
  .pdf-text p  { margin-bottom: 10px; }
  .pdf-text h1 { font-size: 16px; font-weight: 700; color: #1B5EAB; margin: 14px 0 6px; }
  .pdf-text h2 { font-size: 14px; font-weight: 700; color: #1B5EAB; margin: 12px 0 5px; }
  .pdf-text h3 { font-size: 13px; font-weight: 700; margin: 10px 0 4px; }
  .pdf-text ul, .pdf-text ol { margin: 6px 0 10px 20px; }
  .pdf-text li { margin-bottom: 3px; }
  .pdf-text strong { font-weight: 700; }
  .pdf-text em     { font-style: italic; }
  .pdf-text.empty  { display: none; }

  /* ── Trennlinie ── */
  .pdf-divider {
    border: none;
    border-top: 2px solid #1B5EAB;
    opacity: 0.15;
    margin: 0 24px 16px;
  }

  /* ── Tabellen-Überschrift ── */
  .pdf-section-title {
    padding: 0 24px 10px;
    font-size: 9px;
    font-weight: 700;
    color: #1B5EAB;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }

  /* ── Ergebnistabelle ── */
  .pdf-table {
    width: calc(100% - 48px);
    margin: 0 24px;
    border-collapse: collapse;
    font-size: 11px;
  }
  .pdf-table thead tr {
    background: #1B5EAB;
    color: #fff;
  }
  .pdf-table th {
    padding: 6px 8px;
    text-align: left;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .pdf-table th.right { text-align: right; }
  .pdf-table td {
    padding: 5px 8px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
  }
  .pdf-table tr.even td { background: #f8fafc; }
  .pdf-table tr.group-header td {
    background: #e8f0fb;
    color: #1B5EAB;
    font-weight: 700;
    font-size: 10px;
    padding: 5px 8px;
    border-top: 1px solid #c5d8f5;
  }
  .time   { font-family: 'Courier New', monospace; font-weight: 700; color: #1B5EAB; }
  .muted  { color: #888; }
  .dns    { opacity: 0.45; }
  .badge  {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: 700;
    margin-right: 2px;
    vertical-align: middle;
  }
  .b-pb    { background: #dcfce7; color: #15803d; }
  .b-final { background: #f3e8ff; color: #7e22ce; }
  .b-vr    { background: #1B5EAB; color: #fff; }
  .b-lr    { background: #d97706; color: #fff; }
  .b-dns   { background: #e5e7eb; color: #374151; }
  .place-top { font-weight: 700; color: #b45309; }
  .right   { text-align: right; }

  /* ── Footer ── */
  .pdf-footer {
    margin-top: 24px;
    padding: 10px 24px 0;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    font-size: 9px;
    color: #aaa;
  }

  /* ── Print-Overrides ── */
  @media print {
    body { background: #fff; padding: 0; }
    .action-bar { display: none !important; }
    #pdf-content {
      width: 100%;
      margin: 0;
      box-shadow: none;
      padding-bottom: 0;
    }
  }
</style>
</head>
<body>

{{-- Aktions-Leiste (erscheint nicht im PDF) --}}
<div class="action-bar" id="action-bar">
  <span>{{ $competition->name }} – Auswertung</span>
  <button class="btn btn-secondary" onclick="window.close()">Schließen</button>
  <button class="btn btn-primary" id="btn-download" onclick="generatePdf()">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
    PDF herunterladen
  </button>
</div>

{{-- A4-Seite --}}
<div id="pdf-content">

  {{-- Header --}}
  <div class="pdf-header">
    <div class="pdf-header-club">SG Wasserratten Norderstedt e.V. &nbsp;·&nbsp; Wettkampfauswertung</div>
    <div class="pdf-header-title">{{ $competition->name }}</div>
    <div class="pdf-header-meta">
      {{ $competition->date_range }}
      @if($competition->location) &nbsp;·&nbsp; {{ $competition->location }} @endif
      @if($competition->course) &nbsp;·&nbsp; {{ $competition->course_label }} @endif
    </div>
  </div>

  {{-- Auswertungstext --}}
  @if($analysisHtml)
  <div class="pdf-text">
    {!! $analysisHtml !!}
  </div>
  <hr class="pdf-divider">
  @endif

  {{-- Ergebnistabelle --}}
  @if($results->isNotEmpty())
  <div class="pdf-section-title">Ergebnisse unserer Schwimmer</div>
  <table class="pdf-table">
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
      @php $rowIndex = 0; @endphp
      @foreach($results as $group)
        @php $first = $group->first(); @endphp
        <tr class="group-header">
          <td colspan="5">{{ $first->distance }}m {{ $first->discipline_label }}</td>
        </tr>
        @foreach($group->sortBy(fn($s) => $s->is_dns ? PHP_INT_MAX : $s->time_ms) as $swim)
          @php $rowIndex++; @endphp
          <tr class="{{ $swim->is_dns ? 'dns' : '' }} {{ $rowIndex % 2 === 0 ? 'even' : '' }}">
            <td>{{ $swim->user?->name }}</td>
            <td class="muted">{{ $swim->distance }}m {{ $swim->discipline_label }}</td>
            <td class="right">
              @if(!$swim->is_dns)
                <span class="time">{{ $swim->formatted_time }}</span>
              @elseif($swim->notes)
                <span class="badge b-dns">{{ $swim->notes }}</span>
              @endif
            </td>
            <td>
              @if(!empty($swim->placements))
                @foreach($swim->placements as $p)
                  <span class="{{ $p->placement <= 3 ? 'place-top' : 'muted' }}">
                    @if($p->age_group)<span class="muted" style="font-size:9px">{{ $p->age_group }}: </span>@endif{{ $p->placement }}.
                  </span>
                @endforeach
              @else
                <span class="muted">–</span>
              @endif
            </td>
            <td>
              @if($swim->is_final)           <span class="badge b-final">Finale</span> @endif
              @if($swim->is_personal_best && !$swim->is_dns) <span class="badge b-pb">PB</span> @endif
              @if($swim->breaks_vereinsrekord) <span class="badge b-vr">VR</span> @endif
              @if($swim->breaks_landesrekord)  <span class="badge b-lr">LR</span> @endif
            </td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- Footer --}}
  <div class="pdf-footer">
    <span>SG Wasserratten Norderstedt e.V.</span>
    <span>Erstellt am {{ now()->format('d.m.Y') }}</span>
  </div>
</div>

<script>
const FILENAME = '{{ $filename ?? 'Auswertung' }}';

function generatePdf() {
    const btn = document.getElementById('btn-download');
    const bar = document.getElementById('action-bar');
    btn.disabled = true;
    btn.innerHTML = '⏳ Wird erstellt…';
    bar.style.display = 'none';

    const element = document.getElementById('pdf-content');
    const opt = {
        margin:      [8, 8, 12, 8],
        filename:    FILENAME + '.pdf',
        image:       { type: 'jpeg', quality: 0.97 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak:   { mode: 'avoid-all' }
    };

    html2pdf().set(opt).from(element).save().then(() => {
        bar.style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> PDF herunterladen';
    });
}
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $session->title }} – Druckansicht</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #111; background: #fff; }
        h1 { font-size: 16pt; font-weight: bold; margin-bottom: 4px; }
        h2 { font-size: 12pt; font-weight: bold; margin: 20px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        h3 { font-size: 10pt; font-weight: bold; margin: 12px 0 4px; }
        .meta { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 16px; font-size: 10pt; color: #444; }
        .meta span { white-space: nowrap; }
        .meta strong { color: #111; }
        .block { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; page-break-inside: avoid; }
        .block-header { background: #f5f5f5; padding: 6px 10px; font-size: 10pt; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; border-bottom: 1px solid #ddd; }
        .block-body { padding: 8px 10px; }
        .badge { display: inline-block; font-size: 8.5pt; padding: 1px 6px; border-radius: 10px; font-weight: 600; }
        .badge-blue  { background: #dbeafe; color: #1d4ed8; }
        .badge-teal  { background: #ccfbf1; color: #0f766e; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-gray  { background: #f3f4f6; color: #374151; }
        .comment { font-style: italic; color: #555; border-left: 3px solid #ddd; padding-left: 8px; margin-top: 6px; font-size: 9.5pt; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 6px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: center; }
        th { background: #f0f4ff; font-weight: 600; }
        td.name-cell { text-align: left; white-space: nowrap; font-weight: 500; }
        td.value-cell { font-family: monospace; min-width: 52px; }
        .attendance-table td { text-align: left; }
        .present { color: #166534; font-weight: bold; }
        .absent  { color: #991b1b; }
        .section-distance { font-size: 10pt; color: #444; margin-bottom: 10px; }
        .page-break { page-break-before: always; }
        @media print {
            body { font-size: 10pt; }
            .no-print { display: none; }
            @page { margin: 15mm; size: A4; }
        }
    </style>
</head>
<body>
<div style="padding: 8px 0 0;">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
        <div>
            <h1>{{ $session->title }}</h1>
            <div class="meta">
                <span><strong>{{ $session->date->format('d.m.Y') }}</strong> ({{ $session->date->isoFormat('dddd') }})</span>
                <span>{{ $session->start_time }}@if($session->end_time) – {{ $session->end_time }}@endif Uhr</span>
                <span>{{ $session->location }}</span>
                <span>Trainer: {{ $session->trainer->name }}</span>
                <span class="badge badge-gray">{{ $session->type_label }}</span>
            </div>
        </div>
        <button onclick="window.print()" class="no-print"
                style="padding:6px 14px; background:#4f46e5; color:white; border:none; border-radius:6px; cursor:pointer; font-size:10pt;">
            Drucken / PDF
        </button>
    </div>

    @if($session->notes)
        <p style="font-size:10pt; color:#555; margin-bottom:12px; font-style:italic;">{{ $session->notes }}</p>
    @endif

    {{-- ======================== TRAININGSPLAN ======================== --}}
    @if($session->trainingPlan)
        <h2>Trainingsplan</h2>

        @php
            $planTotalMeters = $session->trainingPlan->blocks->sum(fn($b) => ($b->repetitions ?? 0) * ($b->distance ?? 0));
            $allMaterials = $session->trainingPlan->blocks->flatMap(fn($b) => $b->materials ?? [])->unique()->values();
        @endphp

        @if($planTotalMeters > 0 || $allMaterials->isNotEmpty())
            <p class="section-distance">
                @if($planTotalMeters > 0)
                    Gesamtdistanz: <strong>{{ number_format($planTotalMeters) }} m</strong>
                @endif
                @if($allMaterials->isNotEmpty())
                    &nbsp;·&nbsp;
                    @foreach($allMaterials as $mat)
                        <span class="badge badge-teal">{{ $mat }}</span>
                    @endforeach
                @endif
            </p>
        @endif

        @if($session->trainingPlan->description)
            <p style="font-size:10pt; color:#444; margin-bottom:12px; white-space:pre-line;">{{ $session->trainingPlan->description }}</p>
        @endif

        @php $blockNum = 0; @endphp
        @foreach($session->trainingPlan->blocks as $block)
            @php
                $blockNum++;
                $iMin = $block->start_interval_seconds ? intdiv($block->start_interval_seconds, 60) : 0;
                $iSec = $block->start_interval_seconds ? $block->start_interval_seconds % 60 : 0;
                $rMin = $block->recovery_seconds ? intdiv($block->recovery_seconds, 60) : 0;
                $rSec = $block->recovery_seconds ? $block->recovery_seconds % 60 : 0;
            @endphp
            <div class="block">
                <div class="block-header">
                    <strong>Block {{ $blockNum }}</strong>
                    @if($block->label)
                        <span>{{ $block->label }}</span>
                        <span style="color:#ccc;">·</span>
                    @endif
                    @if($block->repetitions && $block->distance)
                        <span style="font-family:monospace; font-weight:bold;">{{ $block->repetitions }} × {{ $block->distance }} m</span>
                        <span class="badge badge-gray">= {{ number_format($block->repetitions * $block->distance) }} m</span>
                    @endif
                    @if($block->start_interval_seconds)
                        <span class="badge badge-blue">Intervall: {{ $iMin }}:{{ str_pad($iSec, 2, '0', STR_PAD_LEFT) }}</span>
                    @endif
                    @if($block->recovery_seconds)
                        <span class="badge" style="background:#dcfce7;color:#166534;">Pause: {{ $rMin }}:{{ str_pad($rSec, 2, '0', STR_PAD_LEFT) }}</span>
                    @endif
                </div>
                <div class="block-body">
                    @php $hasBadges = !empty($block->disciplines) || !empty($block->materials) || !empty($block->additions); @endphp
                    @if($hasBadges)
                        <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px;">
                            @foreach($block->disciplines ?? [] as $disc)
                                <span class="badge badge-blue">{{ \App\Models\TrainingPlanBlock::$disciplineLabels[$disc] ?? $disc }}</span>
                            @endforeach
                            @foreach($block->materials ?? [] as $mat)
                                <span class="badge badge-teal">{{ $mat }}</span>
                            @endforeach
                            @foreach($block->additions ?? [] as $add)
                                <span class="badge badge-amber">{{ $add }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if($block->comment)
                        <p class="comment">{{ $block->comment }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- ======================== ZEITEN-TABELLEN ======================== --}}
    @if($session->trainingPlan && $session->trainingPlan->blocks->where('repetitions', '>', 0)->isNotEmpty() && $swimmers->isNotEmpty())
        <div class="page-break"></div>
        <h2>Zeiten</h2>
        @php $blockNum = 0; @endphp
        @foreach($session->trainingPlan->blocks as $block)
            @if(($block->repetitions ?? 0) > 0)
                @php
                    $blockNum++;
                    $blockTimeRow = $blockTimesMap[$block->id] ?? [];
                @endphp
                <h3>
                    Block {{ $blockNum }}
                    @if($block->label) – {{ $block->label }}@endif
                    @if($block->repetitions && $block->distance) ({{ $block->repetitions }} × {{ $block->distance }} m)@endif
                </h3>
                <div style="overflow-x:auto; margin-bottom:14px;">
                    <table>
                        <thead>
                            <tr>
                                <th style="text-align:left; min-width:100px;">Schwimmer</th>
                                @for($i = 1; $i <= min($block->repetitions, 50); $i++)
                                    <th>{{ $i }}.</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($swimmers as $sw)
                                <tr>
                                    <td class="name-cell">{{ $sw->firstname }} {{ substr($sw->lastname ?? '', 0, 1) }}.</td>
                                    @for($i = 1; $i <= min($block->repetitions, 50); $i++)
                                        @php $cs = $blockTimeRow[$sw->id][$i] ?? null; @endphp
                                        <td class="value-cell">{{ $cs ? \App\Models\TrainingBlockTime::format($cs) : '' }}</td>
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    @endif

    {{-- ======================== ANWESENHEITSLISTE ======================== --}}
    <div class="page-break"></div>
    <h2>Anwesenheitsliste</h2>
    @if($swimmers->isEmpty())
        <p style="color:#888; font-size:10pt;">Keine Schwimmer zugewiesen.</p>
    @else
        <table class="attendance-table" style="max-width:600px;">
            <thead>
                <tr>
                    <th style="text-align:left; width:220px;">Name</th>
                    <th style="width:80px;">Anwesend</th>
                    <th style="text-align:left;">Notiz</th>
                </tr>
            </thead>
            <tbody>
                @foreach($swimmers as $sw)
                    @php
                        $att = $session->attendances->where('user_id', $sw->id)->first();
                        $isPresent = in_array($sw->id, $attendedIds);
                    @endphp
                    <tr>
                        <td>
                            {{ $sw->name }}
                            @if($sw->birth_date)
                                <span style="color:#888; font-size:8.5pt;">({{ $sw->age }} J.)</span>
                            @endif
                            @if($att?->pre_absent)
                                <span class="badge badge-gray" style="font-size:7.5pt;">abgesagt</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            @if($isPresent)
                                <span class="present">✓</span>
                            @else
                                <span class="absent">–</span>
                            @endif
                        </td>
                        <td style="font-size:9pt; color:#555;">{{ $att?->notes }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</div>
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
</body>
</html>

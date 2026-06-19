<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><style>body{font-family:sans-serif;font-size:14px;color:#374151}h2{color:#1B5EAB}.btn{display:inline-block;background:#1B5EAB;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;margin-top:12px}.footer{margin-top:24px;font-size:12px;color:#9CA3AF}</style>
</head>
<body>
<h2>Freier Platz im Gasttraining</h2>

<p>Hallo {{ $recipient->firstname }},</p>

<p>
    für das Gasttraining <strong>{{ $session->title }}</strong>
    am <strong>{{ $session->date->isoFormat('dddd, D. MMMM YYYY') }}</strong>
    @if($session->start_time) um <strong>{{ substr($session->start_time, 0, 5) }} Uhr</strong>@endif
    @if($session->location) in <strong>{{ $session->location }}</strong>@endif
    ist ein Platz frei geworden.
</p>

<p>
    Deine Gruppe ist als Gastgruppe eingetragen. Du kannst dir jetzt einen Platz buchen –
    <strong>first come, first serve</strong> bis die maximale Teilnehmerzahl
    ({{ $session->max_participants }}) erreicht ist.
</p>

<a href="{{ route('swimmer.session.book-guest', $session) }}" class="btn">Jetzt Platz buchen</a>

<p class="footer">
    Diese Nachricht wurde automatisch vom WaRa-Portal gesendet.<br>
    SG Wasserratten Norderstedt e.V.
</p>
</body>
</html>

Hallo {{ $user->firstname ?? $user->name }},

dein Support-Ticket wurde gelöst!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ticket #{{ $ticket->github_issue_number }}
{{ $ticket->typeLabel() }}: {{ $ticket->title }}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

@if($ticket->github_issue_url)
Details auf GitHub: {{ $ticket->github_issue_url }}
@endif

Falls das Problem weiterhin besteht, kannst du jederzeit ein neues Ticket einreichen.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
WaRa-Portal · SG Wasserratten Norderstedt e.V.

Hallo {{ $user->firstname ?? $user->name }},

dein Support-Ticket wurde erfolgreich eingereicht. Vielen Dank!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ticket #{{ $ticket->github_issue_number }}
{{ $ticket->typeLabel() }}: {{ $ticket->title }}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{{ $ticket->description }}

@if($ticket->github_issue_url)
GitHub-Issue: {{ $ticket->github_issue_url }}
@endif

@if($ticket->notify_on_close)
Du wirst per E-Mail benachrichtigt, sobald dieses Ticket gelöst wurde.
@endif

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
WaRa-Portal · SG Wasserratten Norderstedt e.V.

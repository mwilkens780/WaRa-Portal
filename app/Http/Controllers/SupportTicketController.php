<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketCreatedMail;
use App\Mail\SupportTicketResolvedMail;
use App\Models\SupportTicket;
use App\Services\GitHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportTicketController extends Controller
{
    public function create()
    {
        return view('support.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'          => ['required', 'in:bug,enhancement'],
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string', 'max:5000'],
            'notify_on_close' => ['nullable', 'boolean'],
        ]);

        $user   = auth()->user();
        $github = app(GitHubService::class);

        if (!$github->isConfigured()) {
            return back()->withInput()
                ->with('error', 'Support-System ist noch nicht konfiguriert. Bitte wende dich direkt an den Administrator.');
        }

        $label = $data['type'] === 'bug' ? 'bug' : 'enhancement';
        $body  = implode("\n\n", [
            "**Gemeldet von:** {$user->name} (#{$user->id})",
            "**Typ:** " . ($data['type'] === 'bug' ? 'Fehler' : 'Verbesserungsvorschlag'),
            "---",
            $data['description'],
        ]);

        try {
            $issue = $github->createIssue($data['title'], $body, $label);
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'Das Ticket konnte nicht bei GitHub angelegt werden. Bitte versuche es später erneut.');
        }

        $ticket = SupportTicket::create([
            'user_id'             => $user->id,
            'type'                => $data['type'],
            'title'               => $data['title'],
            'description'         => $data['description'],
            'github_issue_number' => $issue['number'],
            'github_issue_url'    => $issue['url'],
            'notify_on_close'     => $request->boolean('notify_on_close'),
        ]);

        if ($user->email) {
            try {
                Mail::to($user->email)->send(new SupportTicketCreatedMail($ticket, $user));
            } catch (\Throwable) {
                // Mail-Fehler nicht an den User weitergeben
            }
        }

        return back()->with('success', "Ticket #" . $issue['number'] . " wurde erfolgreich angelegt.");
    }

    /**
     * GitHub Webhook: fired when an issue is closed.
     * Must be registered without CSRF verification.
     */
    public function webhook(Request $request)
    {
        $secret = config('services.github.webhook_secret', '');
        if ($secret !== '') {
            $sig      = $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($expected, $sig)) {
                abort(403, 'Invalid signature');
            }
        }

        if ($request->header('X-GitHub-Event') !== 'issues') {
            return response()->json(['ok' => true]);
        }

        $payload = $request->json()->all();
        if (($payload['action'] ?? '') !== 'closed') {
            return response()->json(['ok' => true]);
        }

        $number = $payload['issue']['number'] ?? null;
        if (!$number) {
            return response()->json(['ok' => true]);
        }

        $ticket = SupportTicket::where('github_issue_number', $number)
            ->whereNull('github_closed_at')
            ->first();

        if (!$ticket) {
            return response()->json(['ok' => true]);
        }

        $ticket->update(['github_closed_at' => now()]);

        if ($ticket->notify_on_close && $ticket->user?->email) {
            try {
                Mail::to($ticket->user->email)->send(new SupportTicketResolvedMail($ticket, $ticket->user));
            } catch (\Throwable) {
                // Mail-Fehler nicht an den Webhook-Response weitergeben
            }
        }

        return response()->json(['ok' => true]);
    }
}

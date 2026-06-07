<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSignupRequest;
use App\Models\CompetitionSignupResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompetitionSignupController extends Controller
{
    public function store(Request $request, Competition $competition)
    {
        if ($competition->signupRequest()->exists()) {
            return back()->with('error', 'Es existiert bereits eine Anmeldeabfrage für diesen Wettkampf.');
        }

        $data = $request->validate([
            'message'              => ['nullable', 'string'],
            'deadline'             => ['nullable', 'date'],
            'eligible_group_ids'   => ['nullable', 'array'],
            'eligible_group_ids.*' => ['exists:training_groups,id'],
            'eligible_user_ids'    => ['nullable', 'array'],
            'eligible_user_ids.*'  => ['exists:users,id'],
            'attachment'           => ['nullable', 'file', 'max:10240'],
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('competition-attachments', 'local');
        }

        CompetitionSignupRequest::create([
            'competition_id'     => $competition->id,
            'status'             => 'draft',
            'message'            => $data['message'] ?? null,
            'deadline'           => $data['deadline'] ?? null,
            'eligible_group_ids' => $data['eligible_group_ids'] ?? null,
            'eligible_user_ids'  => $data['eligible_user_ids'] ?? null,
            'attachment_path'    => $attachmentPath,
            'created_by_id'      => auth()->id(),
        ]);

        return back()->with('success', 'Anmeldeabfrage als Entwurf gespeichert.');
    }

    public function update(Request $request, Competition $competition, CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->isDraft()) {
            return back()->with('error', 'Nur Entwürfe können bearbeitet werden.');
        }

        $data = $request->validate([
            'message'              => ['nullable', 'string'],
            'deadline'             => ['nullable', 'date'],
            'eligible_group_ids'   => ['nullable', 'array'],
            'eligible_group_ids.*' => ['exists:training_groups,id'],
            'eligible_user_ids'    => ['nullable', 'array'],
            'eligible_user_ids.*'  => ['exists:users,id'],
            'attachment'           => ['nullable', 'file', 'max:10240'],
        ]);

        $attachmentPath = $signupRequest->attachment_path;
        if ($request->hasFile('attachment')) {
            if ($attachmentPath) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $attachmentPath = $request->file('attachment')->store('competition-attachments', 'local');
        }

        $signupRequest->update([
            'message'            => $data['message'] ?? null,
            'deadline'           => $data['deadline'] ?? null,
            'eligible_group_ids' => $data['eligible_group_ids'] ?? null,
            'eligible_user_ids'  => $data['eligible_user_ids'] ?? null,
            'attachment_path'    => $attachmentPath,
        ]);

        return back()->with('success', 'Anmeldeabfrage aktualisiert.');
    }

    public function activate(Competition $competition, CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->isDraft()) {
            return back()->with('error', 'Nur Entwürfe können aktiviert werden.');
        }

        $eligibleUsers = $signupRequest->eligibleUsers();
        if ($eligibleUsers->isEmpty()) {
            return back()->with('error', 'Keine berechtigten Schwimmer definiert. Bitte Gruppen oder einzelne Schwimmer auswählen.');
        }

        $signupRequest->update([
            'status'       => 'active',
            'activated_at' => now(),
        ]);

        foreach ($eligibleUsers as $user) {
            CompetitionSignupResponse::firstOrCreate(
                ['competition_signup_request_id' => $signupRequest->id, 'user_id' => $user->id],
                ['status' => 'pending']
            );
        }

        return back()->with('success', "Anmeldeabfrage gestartet – {$eligibleUsers->count()} Schwimmer wurden eingeladen.");
    }

    public function close(Competition $competition, CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->isActive()) {
            return back()->with('error', 'Nur aktive Abfragen können geschlossen werden.');
        }

        $signupRequest->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return back()->with('success', 'Anmeldeabfrage geschlossen.');
    }

    public function remind(Competition $competition, CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->isActive()) {
            return back()->with('error', 'Erinnerungen können nur bei aktiven Abfragen gesendet werden.');
        }

        $count = $signupRequest->responses()
            ->where('status', 'pending')
            ->update(['reminder_sent_at' => now()]);

        return back()->with('success', "Erinnerung für {$count} Schwimmer markiert.");
    }

    public function destroy(Competition $competition, CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->isDraft()) {
            return back()->with('error', 'Nur Entwürfe können gelöscht werden.');
        }

        if ($signupRequest->attachment_path) {
            Storage::disk('local')->delete($signupRequest->attachment_path);
        }

        $signupRequest->delete();

        return back()->with('success', 'Anmeldeabfrage gelöscht.');
    }

    public function downloadAttachment(Competition $competition, CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->attachment_path || !Storage::disk('local')->exists($signupRequest->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($signupRequest->attachment_path);
    }
}

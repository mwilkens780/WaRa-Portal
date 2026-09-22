<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Models\HealthDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class HealthDataController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (in_array($user->role, ['trainer', 'admin'])) {
            if ($user->role === 'trainer') {
                $swimmerIds = $user->trainerGroups()
                    ->with('swimmers')
                    ->get()
                    ->flatMap(fn($g) => $g->swimmers->pluck('id'))
                    ->unique();

                $swimmers = User::whereIn('id', $swimmerIds)
                    ->where(fn($q) => $q->where('opt_nutrition', true)->orWhere('opt_sports_medicine', true))
                    ->orderBy('lastname')->orderBy('firstname')
                    ->get();
            } else {
                $swimmers = User::where(fn($q) => $q->where('opt_nutrition', true)->orWhere('opt_sports_medicine', true))
                    ->orderBy('lastname')->orderBy('firstname')
                    ->get();
            }

            return view('health.index', compact('swimmers'));
        }

        $documents = $user->healthDocuments()->with('uploader')->latest()->get();
        return view('health.index', compact('documents'));
    }

    public function showForUser(User $user)
    {
        $authUser = auth()->user();

        if ($authUser->role === 'trainer') {
            $swimmerIds = $authUser->trainerGroups()
                ->with('swimmers')
                ->get()
                ->flatMap(fn($g) => $g->swimmers->pluck('id'))
                ->unique();

            if (!$swimmerIds->contains($user->id)) abort(403);
        } elseif ($authUser->role !== 'admin') {
            abort(403);
        }

        // Nur Kategorien mit bestehender Einwilligung - nach einem Widerruf
        // sieht der Trainer die Dokumente nicht mehr
        $documents = $user->healthDocuments()
            ->whereIn('category', HealthDocument::consentedCategories($user))
            ->with('uploader')->latest()->get();
        return view('health.user', compact('user', 'documents'));
    }

    public function download(HealthDocument $doc)
    {
        $authUser = auth()->user();
        $isOwner  = $authUser->id === $doc->user_id;

        // Download-Sperre nach Widerruf: Ohne bestehende Einwilligung kommt nur
        // noch die betroffene Person selbst an ihr Dokument (Auskunftsrecht) -
        // auch Admins, Trainer, Ernaehrungsberatung und Teamarzt nicht.
        if (!$isOwner && !$doc->hasConsent()) {
            abort(403, 'Die Einwilligung für diese Dokumente wurde widerrufen.');
        }

        $canAccess = $isOwner || match ($authUser->role) {
            'admin'              => true,
            'ernaehrungsberater' => $doc->category === 'nutrition',
            'teamarzt'           => $doc->category === 'sports_medicine',
            'trainer'            => $authUser->trainerGroups()
                ->with('swimmers')
                ->get()
                ->flatMap(fn($g) => $g->swimmers->pluck('id'))
                ->unique()
                ->contains($doc->user_id),
            default => false,
        };

        if (!$canAccess) abort(403);

        if (!Storage::disk('local')->exists($doc->stored_path)) {
            abort(404, 'Datei nicht gefunden.');
        }

        return Storage::disk('local')->download($doc->stored_path, $doc->original_filename);
    }
}

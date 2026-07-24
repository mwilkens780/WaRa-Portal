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

        $documents = $user->healthDocuments()->with('uploader')->latest()->get();
        return view('health.user', compact('user', 'documents'));
    }

    public function download(HealthDocument $doc)
    {
        $authUser = auth()->user();

        $canAccess = match ($authUser->role) {
            'admin'              => true,
            'ernaehrungsberater' => $doc->category === 'nutrition',
            'teamarzt'           => $doc->category === 'sports_medicine',
            'trainer'            => $authUser->trainerGroups()
                ->with('swimmers')
                ->get()
                ->flatMap(fn($g) => $g->swimmers->pluck('id'))
                ->unique()
                ->contains($doc->user_id),
            default => $authUser->id === $doc->user_id,
        };

        if (!$canAccess) abort(403);

        if (!Storage::disk('local')->exists($doc->stored_path)) {
            abort(404, 'Datei nicht gefunden.');
        }

        return Storage::disk('local')->download($doc->stored_path, $doc->original_filename);
    }
}

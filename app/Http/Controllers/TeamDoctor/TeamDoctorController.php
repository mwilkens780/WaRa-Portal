<?php

namespace App\Http\Controllers\TeamDoctor;

use App\Http\Controllers\Controller;
use App\Models\HealthDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeamDoctorController extends Controller
{
    public function index()
    {
        $candidates = User::where('opt_sports_medicine', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        return view('teamdoctor.index', compact('candidates'));
    }

    public function show(User $user)
    {
        if (!$user->opt_sports_medicine) abort(404);

        $documents = $user->healthDocuments()
            ->where('category', 'sports_medicine')
            ->with('uploader')
            ->latest()
            ->get();
        $allTags = HealthDocument::allTags();

        return view('teamdoctor.show', compact('user', 'documents', 'allTags'));
    }

    public function upload(Request $request, User $user)
    {
        if (!$user->opt_sports_medicine) abort(404);

        $data = $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'tags'     => ['nullable', 'string'],
        ]);

        $file = $request->file('document');
        $path = $file->store('health-documents/sports-medicine', 'local');
        $tags = array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))));

        HealthDocument::create([
            'user_id'           => $user->id,
            'uploaded_by'       => auth()->id(),
            'category'          => 'sports_medicine',
            'title'             => $data['title'],
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $path,
            'file_size'         => $file->getSize(),
            'tags'              => $tags ?: null,
        ]);

        return redirect()->route('teamdoctor.show', $user)
            ->with('success', 'Dokument hochgeladen.');
    }

    public function destroy(HealthDocument $doc)
    {
        if ($doc->category !== 'sports_medicine') abort(403);

        Storage::disk('local')->delete($doc->stored_path);
        $doc->delete();

        return back()->with('success', 'Dokument gelöscht.');
    }
}

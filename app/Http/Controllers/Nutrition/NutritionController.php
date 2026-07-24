<?php

namespace App\Http\Controllers\Nutrition;

use App\Http\Controllers\Controller;
use App\Models\HealthDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NutritionController extends Controller
{
    public function index()
    {
        $candidates = User::where('opt_nutrition', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        return view('nutrition.index', compact('candidates'));
    }

    public function show(User $user)
    {
        if (!$user->opt_nutrition) abort(404);

        $documents = $user->healthDocuments()
            ->where('category', 'nutrition')
            ->with('uploader')
            ->latest()
            ->get();
        $allTags = HealthDocument::allTags();

        return view('nutrition.show', compact('user', 'documents', 'allTags'));
    }

    public function upload(Request $request, User $user)
    {
        if (!$user->opt_nutrition) abort(404);

        $data = $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'tags'     => ['nullable', 'string'],
        ]);

        $file = $request->file('document');
        $path = $file->store('health-documents/nutrition', 'local');
        $tags = array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))));

        HealthDocument::create([
            'user_id'           => $user->id,
            'uploaded_by'       => auth()->id(),
            'category'          => 'nutrition',
            'title'             => $data['title'],
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $path,
            'file_size'         => $file->getSize(),
            'tags'              => $tags ?: null,
        ]);

        return redirect()->route('nutrition.show', $user)
            ->with('success', 'Dokument hochgeladen.');
    }

    public function destroy(HealthDocument $doc)
    {
        if ($doc->category !== 'nutrition') abort(403);

        Storage::disk('local')->delete($doc->stored_path);
        $doc->delete();

        return back()->with('success', 'Dokument gelöscht.');
    }
}

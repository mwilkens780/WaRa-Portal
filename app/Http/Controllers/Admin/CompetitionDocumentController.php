<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompetitionDocumentController extends Controller
{
    public function store(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'category' => ['required', 'in:ausschreibung,protokoll,meldeergebnis,sonstige'],
            'file'     => ['required', 'file', 'max:51200'],
        ]);

        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $path         = $file->store("competition-docs/{$competition->id}", 'local');

        CompetitionDocument::create([
            'competition_id' => $competition->id,
            'category'       => $data['category'],
            'original_name'  => $originalName,
            'path'           => $path,
            'size'           => $file->getSize(),
            'created_by_id'  => auth()->id(),
        ]);

        return back()->with('success', "Dokument \"{$originalName}\" hochgeladen.");
    }

    public function download(Competition $competition, CompetitionDocument $document)
    {
        abort_unless($document->competition_id === $competition->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Competition $competition, CompetitionDocument $document)
    {
        abort_unless($document->competition_id === $competition->id, 404);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Dokument gelöscht.');
    }
}

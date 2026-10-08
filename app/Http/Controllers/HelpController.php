<?php

namespace App\Http\Controllers;

use App\Support\HelpCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Hilfe & FAQ: Anleitungen und häufige Fragen zur Einführung des Portals.
 *  - /hilfe            angemeldet, zuerst die Themen für die eigene Rolle
 *  - /hilfe/anmeldung  öffentlich: Anmeldung, Passwort, App, Kalender-Abo
 */
class HelpController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $articles = collect(HelpCatalog::articles())->map(function ($a) use ($user) {
            $html = HelpCatalog::render($a['html']);
            return $a + [
                'body'  => $html,
                'mine'  => HelpCatalog::audience($a['audience'], $user),
                'index' => Str::lower($a['title'] . ' ' . $a['keywords'] . ' ' . strip_tags($html)),
            ];
        });

        $faq = collect(HelpCatalog::faq())->map(fn($f) => $f + [
            'mine'  => HelpCatalog::audience($f['audience'], $user),
            'index' => Str::lower($f['q'] . ' ' . strip_tags($f['a'])),
        ]);

        return view('help.index', [
            'categories' => HelpCatalog::CATEGORIES,
            'articles'   => $articles->groupBy('category'),
            'faq'        => $faq,
        ]);
    }

    public function public()
    {
        if (auth()->check()) {
            return redirect()->route('help.index');
        }

        return view('help.public', [
            'articles' => collect(HelpCatalog::articles())->where('public', true)
                ->map(fn($a) => $a + ['body' => HelpCatalog::render($a['html'])]),
            'faq'      => collect(HelpCatalog::faq())->where('public', true),
        ]);
    }
}

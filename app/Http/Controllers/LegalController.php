<?php

namespace App\Http\Controllers;

class LegalController extends Controller
{
    public function impressum()
    {
        return view('legal.impressum');
    }

    public function datenschutz()
    {
        return view('legal.datenschutz');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile.index', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'firstname'           => ['required', 'string', 'max:100'],
            'lastname'            => ['required', 'string', 'max:100'],
            'email'               => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'email2'              => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'mobile'              => ['nullable', 'string', 'max:50'],
            'street'              => ['nullable', 'string', 'max:255'],
            'postal_code'         => ['nullable', 'string', 'max:20'],
            'city'                => ['nullable', 'string', 'max:100'],
            'opt_nutrition'       => ['boolean'],
            'opt_sports_medicine' => ['boolean'],
        ]);

        $data['opt_nutrition']       = $request->boolean('opt_nutrition');
        $data['opt_sports_medicine'] = $request->boolean('opt_sports_medicine');

        $user->update($data);

        return redirect()->route('profile.index')
            ->with('success', 'Profil gespeichert.');
    }
}

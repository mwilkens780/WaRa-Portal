<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WebClubImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserLiteController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where('firstname', 'like', $term)
                  ->orWhere('lastname', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }
        if ($request->filled('active')) {
            $query->where('active', $request->active === '1');
        }

        $users = $query->orderBy('lastname')->orderBy('firstname')->paginate(25)->withQueryString();

        return view('trainer.users.index', compact('users'));
    }

    public function create()
    {
        return view('trainer.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname' => ['required', 'string', 'max:100'],
            'lastname'  => ['required', 'string', 'max:100'],
            'email'     => ['nullable', 'email', 'unique:users'],
            'role'      => ['required', 'in:' . implode(',', User::ROLES)],
            'birth_date'=> ['nullable', 'date'],
            'phone'     => ['nullable', 'string', 'max:30'],
        ]);

        $plain = WebClubImportService::generateInitialPassword();

        $data['name']             = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['password']         = Hash::make($plain);
        $data['initial_password'] = $plain;
        $data['active']           = true;

        $user = User::create($data);

        return redirect()->route('users-lite.edit', $user)
            ->with('success', "Benutzer \"{$user->name}\" angelegt – Initialpasswort: {$plain}");
    }

    public function edit(User $user)
    {
        return view('trainer.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'firstname'  => ['required', 'string', 'max:100'],
            'lastname'   => ['required', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'role'       => ['required', 'in:' . implode(',', User::ROLES)],
            'birth_date' => ['nullable', 'date'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'mobile'     => ['nullable', 'string', 'max:30'],
            'active'     => ['boolean'],
        ]);

        $data['name']   = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['active'] = $request->has('active') ? $request->boolean('active') : $user->active;

        if ($request->filled('password')) {
            $request->validate(['password' => ['confirmed', Password::min(8)->letters()->numbers()]]);
            $data['password']         = Hash::make($request->password);
            $data['initial_password'] = null;
        }

        $user->update($data);

        return redirect()->route('users-lite.index')
            ->with('success', "Benutzer \"{$user->name}\" aktualisiert.");
    }

    public function toggleActive(User $user)
    {
        $user->update(['active' => !$user->active]);
        $status = $user->active ? 'aktiviert' : 'deaktiviert';
        return back()->with('success', "Benutzer \"{$user->name}\" {$status}.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WebClubImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
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
                  ->orWhere('name', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }
        if ($request->filled('active')) {
            $query->where('active', $request->active === '1');
        }

        $users = $query->orderBy('lastname')->orderBy('firstname')->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $swimmers = User::where('role', 'schwimmer')->orderBy('lastname')->orderBy('firstname')->get();
        return view('admin.users.create', compact('swimmers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname'          => ['required', 'string', 'max:100'],
            'lastname'           => ['required', 'string', 'max:100'],
            'email'              => ['required', 'email', 'unique:users'],
            'password'           => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role'               => ['required', 'in:' . implode(',', User::ROLES)],
            'additional_roles'   => ['nullable', 'array'],
            'additional_roles.*' => ['in:' . implode(',', User::ROLES)],
            'birth_date'         => ['nullable', 'date'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'active'             => ['boolean'],
            'children'           => ['nullable', 'array'],
            'children.*'         => ['exists:users,id'],
        ]);

        $data['name']             = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['password']         = Hash::make($data['password']);
        $data['active']           = $request->boolean('active', true);
        $data['additional_roles'] = empty($data['additional_roles']) ? null : $data['additional_roles'];

        $user = User::create($data);

        if ($data['role'] === 'elternteil' && !empty($data['children'])) {
            $user->children()->sync($data['children']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Benutzer \"{$user->name}\" wurde erfolgreich angelegt.");
    }

    public function edit(User $user)
    {
        $swimmers         = User::where('role', 'schwimmer')->orderBy('lastname')->orderBy('firstname')->get();
        $assignedChildren = $user->children()->pluck('users.id')->toArray();
        // $user->attributes in Blade goes through __get() and would resolve to an 'attributes' DB column (null).
        // Pass the raw value explicitly so the view doesn't need to access the protected property.
        $initialPassword  = $user->getRawOriginal('initial_password');
        return view('admin.users.edit', compact('user', 'swimmers', 'assignedChildren', 'initialPassword'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'firstname'          => ['required', 'string', 'max:100'],
            'lastname'           => ['required', 'string', 'max:100'],
            'email'              => ['required', 'email', 'unique:users,email,' . $user->id],
            'role'               => ['required', 'in:' . implode(',', User::ROLES)],
            'additional_roles'   => ['nullable', 'array'],
            'additional_roles.*' => ['in:' . implode(',', User::ROLES)],
            'birth_date'         => ['nullable', 'date'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'active'             => ['boolean'],
            'children'           => ['nullable', 'array'],
            'children.*'         => ['exists:users,id'],
        ]);

        $data['name']             = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['additional_roles'] = empty($data['additional_roles']) ? null : $data['additional_roles'];

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Password::min(8)->letters()->numbers()],
            ]);
            $data['password']          = Hash::make($request->password);
            $data['initial_password']  = null; // manual password clears initial status
        }

        $data['active'] = $request->boolean('active');
        $user->update($data);

        if ($data['role'] === 'elternteil') {
            $user->children()->sync($data['children'] ?? []);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Benutzer \"{$user->name}\" wurde aktualisiert.");
    }

    public function resetPassword(User $user)
    {
        $plain = WebClubImportService::generateInitialPassword();

        $user->update([
            'password'         => Hash::make($plain),
            'initial_password' => $plain,
        ]);

        return back()->with('success', "Neues Initialpasswort für \"{$user->name}\" gesetzt: {$plain}");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Du kannst dein eigenes Konto nicht löschen.']);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "Benutzer \"{$name}\" wurde gelöscht.");
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Du kannst dein eigenes Konto nicht deaktivieren.']);
        }

        $user->update(['active' => !$user->active]);
        $status = $user->active ? 'aktiviert' : 'deaktiviert';

        return back()->with('success', "Benutzer \"{$user->name}\" wurde {$status}.");
    }
}

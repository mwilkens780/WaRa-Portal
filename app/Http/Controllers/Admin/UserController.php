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

        $users = $query->with('userRoles')->orderBy('lastname')->orderBy('firstname')->paginate(20)->withQueryString();

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
            'firstname'    => ['required', 'string', 'max:100'],
            'lastname'     => ['required', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'unique:users'],
            'user_roles'   => ['nullable', 'array'],
            'user_roles.*' => ['in:' . implode(',', User::ROLES)],
            'birth_date'   => ['nullable', 'date'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'active'       => ['boolean'],
            'children'     => ['nullable', 'array'],
            'children.*'   => ['exists:users,id'],
        ]);

        $roles = $data['user_roles'] ?? [];

        // Primary role = highest-priority role in selection (order from User::ROLES)
        $primaryRole = collect(User::ROLES)->first(fn($r) => in_array($r, $roles)) ?? 'schwimmer';

        $plain = WebClubImportService::generateInitialPassword();

        $data['name']             = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['role']             = $primaryRole;
        $data['password']         = Hash::make($plain);
        $data['initial_password'] = $plain;
        $data['active']           = $request->boolean('active', true);
        unset($data['user_roles'], $data['children']);

        $user = User::create($data);
        if ($roles) {
            $user->syncRoles($roles);
        }

        if (in_array('elternteil', $roles) && !empty($request->children)) {
            $user->children()->sync($request->children);
        }

        // Redirect to edit so admin can see and copy the initial password
        return redirect()->route('admin.users.edit', $user)
            ->with('success', "Benutzer \"{$user->name}\" angelegt – Initialpasswort ist unten sichtbar.");
    }

    public function edit(User $user)
    {
        $user->load('userRoles');
        $swimmers         = User::where('role', 'schwimmer')->orderBy('lastname')->orderBy('firstname')->get();
        $assignedChildren = $user->children()->pluck('users.id')->toArray();
        $initialPassword  = $user->getRawOriginal('initial_password');
        return view('admin.users.edit', compact('user', 'swimmers', 'assignedChildren', 'initialPassword'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'firstname'    => ['required', 'string', 'max:100'],
            'lastname'     => ['required', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'role'         => ['nullable', 'in:' . implode(',', User::ROLES)],
            'user_roles'   => ['nullable', 'array'],
            'user_roles.*' => ['in:' . implode(',', User::ROLES)],
            'birth_date'   => ['nullable', 'date'],
            'gender'       => ['nullable', 'in:M,F'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'mobile'       => ['nullable', 'string', 'max:30'],
            'email2'       => ['nullable', 'email'],
            'dsv_id'       => ['nullable', 'string', 'max:20', 'unique:users,dsv_id,' . $user->id],
            'membership_number' => ['nullable', 'string', 'max:30'],
            'member_since' => ['nullable', 'date'],
            'street'       => ['nullable', 'string', 'max:255'],
            'postal_code'  => ['nullable', 'string', 'max:10'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
            'active'       => ['boolean'],
            'trainer_license_nr'              => ['nullable', 'string', 'max:50'],
            'trainer_license_valid_until'     => ['nullable', 'date'],
            'rescue_certificate_until'        => ['nullable', 'date'],
            'first_aid_until'                 => ['nullable', 'date'],
            'police_clearance_date'           => ['nullable', 'date'],
            'kampfrichter_license_nr'         => ['nullable', 'string', 'max:50'],
            'kampfrichter_license_issued'     => ['nullable', 'date'],
            'kampfrichter_license_valid_until' => ['nullable', 'date'],
            'children'     => ['nullable', 'array'],
            'children.*'   => ['exists:users,id'],
        ]);

        $data['name']   = trim($data['firstname'] . ' ' . $data['lastname']);
        $roles = $data['user_roles'] ?? [];
        unset($data['user_roles'], $data['children']);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Password::min(8)->letters()->numbers()],
            ]);
            $data['password']         = Hash::make($request->password);
            $data['initial_password'] = null;
        }

        $data['active'] = $request->has('active') ? $request->boolean('active') : $user->active;
        $user->update($data);
        $user->syncRoles($roles);

        if (($data['role'] ?? '') === 'elternteil') {
            $user->children()->sync($request->children ?? []);
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

    public function destroyAll(Request $request)
    {
        $request->validate([
            'confirm_text' => ['required', 'in:ALLE LÖSCHEN'],
        ], [
            'confirm_text.in' => 'Bitte gib "ALLE LÖSCHEN" ein, um zu bestätigen.',
        ]);

        $count = User::where('role', '!=', 'admin')->count();
        User::where('role', '!=', 'admin')->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "{$count} Nicht-Admin-Benutzer wurden gelöscht. Alle Administratoren bleiben erhalten.");
    }

    public function cleanupDsvIds()
    {
        $count = User::whereRaw("dsv_id REGEXP '^0+$'")->count();
        User::whereRaw("dsv_id REGEXP '^0+$'")->update(['dsv_id' => null]);

        return redirect()->route('admin.users.index')
            ->with('success', "DSV-ID bereinigt: {$count} Einträge mit Nullwert (000000 etc.) auf leer gesetzt.");
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

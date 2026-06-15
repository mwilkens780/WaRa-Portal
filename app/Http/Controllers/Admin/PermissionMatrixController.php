<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuPermission;
use App\Models\User;
use Illuminate\Http\Request;

class PermissionMatrixController extends Controller
{
    public function index()
    {
        $roles    = array_keys(User::ROLE_LABELS);
        $items    = MenuPermission::MENU_ITEMS;
        $matrix   = [];

        foreach ($roles as $role) {
            if ($role === 'admin') continue; // admin always has everything
            $matrix[$role] = MenuPermission::where('role', $role)
                ->pluck('allowed', 'menu_key')
                ->toArray();
        }

        return view('admin.permissions.index', compact('roles', 'items', 'matrix'));
    }

    public function update(Request $request)
    {
        $roles    = array_keys(User::ROLE_LABELS);
        $allKeys  = array_keys(MenuPermission::MENU_ITEMS);
        $granted  = $request->input('permissions', []);

        foreach ($roles as $role) {
            if ($role === 'admin') continue;
            foreach ($allKeys as $key) {
                MenuPermission::updateOrCreate(
                    ['role' => $role, 'menu_key' => $key],
                    ['allowed' => isset($granted[$role][$key])]
                );
            }
        }

        MenuPermission::clearCache();

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Berechtigungen gespeichert.');
    }
}

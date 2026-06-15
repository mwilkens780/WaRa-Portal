<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'maintenance_mode'         => Setting::getBool('maintenance_mode'),
            'maintenance_message'      => Setting::getCached('maintenance_message',
                'Das Portal wird gerade gewartet. Bitte versuche es später erneut.'),
            'maintenance_bypass_users' => Setting::getBypassUserIds(),
        ];

        $users = User::where('role', '!=', 'admin')
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        return view('admin.settings.index', compact('settings', 'users'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'maintenance_mode'         => ['boolean'],
            'maintenance_message'      => ['nullable', 'string', 'max:1000'],
            'maintenance_bypass_users' => ['nullable', 'array'],
            'maintenance_bypass_users.*' => ['integer', 'exists:users,id'],
        ]);

        Setting::set('maintenance_mode',
            $request->boolean('maintenance_mode') ? '1' : '0');
        Setting::set('maintenance_message',
            $data['maintenance_message'] ?? 'Das Portal wird gerade gewartet. Bitte versuche es später erneut.');
        Setting::set('maintenance_bypass_users',
            json_encode($data['maintenance_bypass_users'] ?? []));

        Setting::clearCache();

        return redirect()->route('admin.settings.index')
            ->with('success', 'Einstellungen gespeichert.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

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

        $webclub = [
            'enabled'              => Setting::getBool('crawler.webclub.enabled', false),
            'base_url'             => Setting::getCached('crawler.webclub.base_url', ''),
            'username'             => Setting::getCached('crawler.webclub.username', ''),
            'password_set'         => Setting::getCached('crawler.webclub.password', '') !== '',
            'scrape_competitions'  => Setting::getBool('crawler.webclub.scrape_competitions', true),
            'scrape_persons'       => Setting::getBool('crawler.webclub.scrape_persons', true),
            'lookback_days'        => (int) Setting::getCached('crawler.webclub.lookback_days', 90),
            'lookahead_days'       => (int) Setting::getCached('crawler.webclub.lookahead_days', 365),
            'headless'             => Setting::getBool('crawler.webclub.headless', true),
            'timeout_ms'           => (int) Setting::getCached('crawler.webclub.timeout_ms', 15000),
            'timeout_seconds'      => (int) Setting::getCached('crawler.webclub.timeout_seconds', 300),
            'node_path'            => Setting::getCached('crawler.webclub.node_path', ''),
        ];

        $users = User::where('role', '!=', 'admin')
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        return view('admin.settings.index', compact('settings', 'webclub', 'users'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'maintenance_mode'           => ['boolean'],
            'maintenance_message'        => ['nullable', 'string', 'max:1000'],
            'maintenance_bypass_users'   => ['nullable', 'array'],
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

    public function updateWebClub(Request $request)
    {
        $data = $request->validate([
            'webclub_enabled'              => ['boolean'],
            'webclub_base_url'             => ['nullable', 'url', 'max:255'],
            'webclub_username'             => ['nullable', 'string', 'max:255'],
            'webclub_password'             => ['nullable', 'string', 'max:500'],
            'webclub_scrape_competitions'  => ['boolean'],
            'webclub_scrape_persons'       => ['boolean'],
            'webclub_lookback_days'        => ['nullable', 'integer', 'min:0', 'max:730'],
            'webclub_lookahead_days'       => ['nullable', 'integer', 'min:0', 'max:730'],
            'webclub_headless'             => ['boolean'],
            'webclub_timeout_ms'           => ['nullable', 'integer', 'min:5000', 'max:60000'],
            'webclub_timeout_seconds'      => ['nullable', 'integer', 'min:60', 'max:1800'],
        ]);

        Setting::set('crawler.webclub.enabled',
            $request->boolean('webclub_enabled') ? '1' : '0');
        Setting::set('crawler.webclub.base_url',
            $data['webclub_base_url'] ?? '');
        Setting::set('crawler.webclub.username',
            $data['webclub_username'] ?? '');

        // Passwort nur speichern wenn ein neues eingegeben wurde
        if (!empty($data['webclub_password'])) {
            Setting::set('crawler.webclub.password',
                Crypt::encryptString($data['webclub_password']));
        }

        Setting::set('crawler.webclub.scrape_competitions',
            $request->boolean('webclub_scrape_competitions') ? '1' : '0');
        Setting::set('crawler.webclub.scrape_persons',
            $request->boolean('webclub_scrape_persons') ? '1' : '0');
        Setting::set('crawler.webclub.lookback_days',
            (string) ($data['webclub_lookback_days'] ?? 90));
        Setting::set('crawler.webclub.lookahead_days',
            (string) ($data['webclub_lookahead_days'] ?? 365));
        Setting::set('crawler.webclub.headless',
            $request->boolean('webclub_headless') ? '1' : '0');
        Setting::set('crawler.webclub.timeout_ms',
            (string) ($data['webclub_timeout_ms'] ?? 15000));
        Setting::set('crawler.webclub.timeout_seconds',
            (string) ($data['webclub_timeout_seconds'] ?? 300));
        Setting::set('crawler.webclub.node_path',
            $data['webclub_node_path'] ?? '');

        Setting::clearCache();

        return redirect()->route('admin.settings.index')
            ->with('webclub_success', 'WebClub-Einstellungen gespeichert.');
    }
}

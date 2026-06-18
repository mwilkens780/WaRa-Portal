<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    // DSV-Daten Bundesländer-Mapping (verifizierte StateIDs)
    public const DSV_STATES = [
        ['id' => 5,  'name' => 'Bremen',                  'short' => 'BSSV'],
        ['id' => 6,  'name' => 'Hamburg',                 'short' => 'HSV'],
        ['id' => 8,  'name' => 'Mecklenburg-Vorpommern',  'short' => 'SVMV'],
        ['id' => 9,  'name' => 'Niedersachsen',            'short' => 'NSV'],
        ['id' => 14, 'name' => 'Schleswig-Holstein',       'short' => 'SHSV'],
        ['id' => 17, 'name' => 'Nordrhein-Westfalen',      'short' => 'WDSV'],
        ['id' => 12, 'name' => 'Sachsen',                  'short' => 'SVS'],
    ];

    public function index()
    {
        $settings = [
            'maintenance_mode'         => Setting::getBool('maintenance_mode'),
            'maintenance_message'      => Setting::getCached('maintenance_message',
                'Das Portal wird gerade gewartet. Bitte versuche es später erneut.'),
            'maintenance_bypass_users' => Setting::getBypassUserIds(),

            // Crawler
            'crawler_dsvdata_enabled'       => Setting::getBool('crawler.dsvdata.enabled', true),
            'crawler_dsvdata_state_ids'     => Setting::getJson('crawler.dsvdata.state_ids', [14]),
            'crawler_dsvdata_schedule_days' => Setting::getJson('crawler.dsvdata.schedule_days', [3]),
            'crawler_dsvdata_schedule_time' => Setting::getCached('crawler.dsvdata.schedule_time', '07:00'),
        ];

        $users = User::where('role', '!=', 'admin')
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        $dsvStates = self::DSV_STATES;

        return view('admin.settings.index', compact('settings', 'users', 'dsvStates'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'maintenance_mode'             => ['boolean'],
            'maintenance_message'          => ['nullable', 'string', 'max:1000'],
            'maintenance_bypass_users'     => ['nullable', 'array'],
            'maintenance_bypass_users.*'   => ['integer', 'exists:users,id'],

            'crawler_dsvdata_enabled'      => ['boolean'],
            'crawler_dsvdata_state_ids'    => ['nullable', 'array'],
            'crawler_dsvdata_state_ids.*'  => ['integer'],
            'crawler_dsvdata_schedule_days'    => ['nullable', 'array'],
            'crawler_dsvdata_schedule_days.*'  => ['integer', 'between:1,7'],
            'crawler_dsvdata_schedule_time'    => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        Setting::set('maintenance_mode',
            $request->boolean('maintenance_mode') ? '1' : '0');
        Setting::set('maintenance_message',
            $data['maintenance_message'] ?? 'Das Portal wird gerade gewartet. Bitte versuche es später erneut.');
        Setting::set('maintenance_bypass_users',
            json_encode($data['maintenance_bypass_users'] ?? []));

        Setting::set('crawler.dsvdata.enabled',
            $request->boolean('crawler_dsvdata_enabled') ? '1' : '0');
        Setting::set('crawler.dsvdata.state_ids',
            json_encode(array_map('intval', $data['crawler_dsvdata_state_ids'] ?? [14])));
        Setting::set('crawler.dsvdata.schedule_days',
            json_encode(array_map('intval', $data['crawler_dsvdata_schedule_days'] ?? [3])));
        Setting::set('crawler.dsvdata.schedule_time',
            $data['crawler_dsvdata_schedule_time'] ?? '07:00');

        Setting::clearCache();

        return redirect()->route('admin.settings.index')
            ->with('success', 'Einstellungen gespeichert.');
    }
}

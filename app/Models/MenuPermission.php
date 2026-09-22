<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MenuPermission extends Model
{
    protected $fillable = ['role', 'menu_key', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    // Ueber die Matrix steuerbare Menuepunkte.
    // Reine Admin-Bereiche (Protokoll, Crawler & Import-Log, WA-Punktetabellen,
    // DSGVO, Berechtigungs-Matrix, Einstellungen, volle Benutzerverwaltung)
    // stehen bewusst NICHT hier – sie sind per Rolle auf Admin beschraenkt und
    // sollen auch nicht versehentlich freigeschaltet werden koennen.
    const MENU_ITEMS = [
        'calendar'       => ['label' => 'Kalender',               'section' => 'general'],
        'users_lite'     => ['label' => 'Benutzerverwaltung',     'section' => 'general'],
        'training'       => ['label' => 'Trainingseinheiten',     'section' => 'trainer'],
        'training_groups'=> ['label' => 'Trainingsgruppen',       'section' => 'trainer'],
        'competitions'   => ['label' => 'Wettkämpfe',             'section' => 'trainer'],
        'records'        => ['label' => 'Rekorde',                'section' => 'trainer'],
        'goals'          => ['label' => 'Ziele',                  'section' => 'trainer'],
        'diary'          => ['label' => 'Einschätzungen',         'section' => 'trainer'],
        'motto'          => ['label' => 'Motto der Woche',        'section' => 'trainer'],
        'hall'           => ['label' => 'Hallenbelegung',         'section' => 'trainer'],
        'swimmer_times'  => ['label' => 'Meine Bestzeiten',       'section' => 'swimmer'],
        'swimmer_comps'  => ['label' => 'Meine Wettkämpfe',       'section' => 'swimmer'],
        'swimmer_goals'  => ['label' => 'Meine Ziele',            'section' => 'swimmer'],
        'swimmer_sessions'    => ['label' => 'Mein Training',     'section' => 'swimmer'],
        'swimmer_group_goals' => ['label' => 'Gruppenziele',      'section' => 'swimmer'],
        'swimmer_motto'       => ['label' => 'Motto der Woche',   'section' => 'swimmer'],
        'parent_area'    => ['label' => 'Meine Kinder',           'section' => 'parent'],
    ];

    const DEFAULT_PERMISSIONS = [
        'admin'        => ['calendar','users_lite','training','training_groups','competitions','records','goals','diary','motto','hall','swimmer_times','swimmer_comps','swimmer_goals','swimmer_sessions','swimmer_group_goals','swimmer_motto','parent_area'],
        'trainer'      => ['calendar','users_lite','training','training_groups','competitions','records','goals','diary','motto','hall'],
        'vorstand'     => ['calendar','users_lite','competitions','records'],
        'kampfrichter' => ['calendar','competitions'],
        'schwimmer'           => ['calendar','swimmer_times','swimmer_comps','swimmer_goals','swimmer_sessions','swimmer_group_goals','swimmer_motto'],
        'elternteil'          => ['calendar','parent_area'],
        'ernaehrungsberater'  => ['calendar'],
        'teamarzt'            => ['calendar'],
    ];

    public static function getForRole(string $role): array
    {
        return Cache::remember("menu_perm_{$role}", 600, function () use ($role) {
            return static::where('role', $role)->pluck('allowed', 'menu_key')->toArray();
        });
    }

    public static function can(string $role, string $menuKey): bool
    {
        if ($role === 'admin') return true;

        $perms = static::getForRole($role);

        // Ausdrueckliche Entscheidung aus der Matrix hat Vorrang.
        if (array_key_exists($menuKey, $perms)) {
            return (bool) $perms[$menuKey];
        }

        // Kein Eintrag: neu hinzugekommener Menuepunkt, fuer den noch niemand
        // gespeichert hat. Dann gilt die Voreinstellung der Rolle – sonst
        // wuerde jeder neue Schluessel alle Rollen aussperren, bis ein Admin
        // die Matrix einmal speichert.
        return in_array($menuKey, static::DEFAULT_PERMISSIONS[$role] ?? [], true);
    }

    public static function clearCache(): void
    {
        foreach (array_keys(User::ROLE_LABELS) as $role) {
            Cache::forget("menu_perm_{$role}");
        }
    }
}

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

    // Menu items configurable via the matrix
    // Admin-only items (logs, users_full, permissions) are NOT listed here — always visible to admin
    const MENU_ITEMS = [
        'dashboard'      => ['label' => 'Dashboard',              'section' => 'general'],
        'calendar'       => ['label' => 'Kalender',               'section' => 'general'],
        'users_lite'     => ['label' => 'Benutzerverwaltung',     'section' => 'general'],
        'training'       => ['label' => 'Trainingseinheiten',     'section' => 'trainer'],
        'training_groups'=> ['label' => 'Trainingsgruppen',       'section' => 'trainer'],
        'competitions'   => ['label' => 'Wettkämpfe',             'section' => 'trainer'],
        'records'        => ['label' => 'Rekorde',                'section' => 'trainer'],
        'goals'          => ['label' => 'Ziele',                  'section' => 'trainer'],
        'hall'           => ['label' => 'Hallenbelegung',         'section' => 'trainer'],
        'swimmer_times'  => ['label' => 'Meine Bestzeiten',       'section' => 'swimmer'],
        'swimmer_comps'  => ['label' => 'Meine Wettkämpfe',       'section' => 'swimmer'],
        'swimmer_goals'  => ['label' => 'Meine Ziele',            'section' => 'swimmer'],
        'parent_area'    => ['label' => 'Meine Kinder',           'section' => 'parent'],
    ];

    const DEFAULT_PERMISSIONS = [
        'admin'        => ['dashboard','calendar','users_lite','training','training_groups','competitions','records','goals','hall','swimmer_times','swimmer_comps','swimmer_goals','parent_area'],
        'trainer'      => ['dashboard','calendar','users_lite','training','training_groups','competitions','records','goals','hall'],
        'vorstand'     => ['dashboard','calendar','users_lite','competitions','records'],
        'kampfrichter' => ['dashboard','calendar','competitions'],
        'schwimmer'    => ['dashboard','calendar','swimmer_times','swimmer_comps','swimmer_goals'],
        'elternteil'   => ['dashboard','calendar','parent_area'],
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
        return $perms[$menuKey] ?? false;
    }

    public static function clearCache(): void
    {
        foreach (array_keys(User::ROLE_LABELS) as $role) {
            Cache::forget("menu_perm_{$role}");
        }
    }
}

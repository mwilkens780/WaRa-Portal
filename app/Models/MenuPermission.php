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

    // Ueber die Matrix steuerbare Bereiche und Rechte.
    // Reine Admin-Bereiche (Protokoll, Crawler & Import-Log, WA-Punktetabellen,
    // DSGVO, Berechtigungs-Matrix, Einstellungen, volle Benutzerverwaltung)
    // stehen bewusst NICHT hier – sie sind per Rolle auf Admin beschraenkt und
    // sollen auch nicht versehentlich freigeschaltet werden koennen.
    //
    // Abschnitt "scope": Reichweite innerhalb eines Bereichs (eigene Gruppen
    // vs. alle). Abschnitt "events": welche Terminarten jemand anlegt.
    const MENU_ITEMS = [
        'calendar'       => ['label' => 'Kalender',               'section' => 'general'],
        'users_lite'     => ['label' => 'Benutzerverwaltung (Mitglieder eigener Gruppen)', 'section' => 'general'],
        'training'       => ['label' => 'Trainingseinheiten (eigene Gruppen)', 'section' => 'trainer'],
        'training_groups'=> ['label' => 'Trainingsgruppen (eigene)', 'section' => 'trainer'],
        'competitions'   => ['label' => 'Wettkämpfe',             'section' => 'trainer'],
        // Ergebnisse, Definitionsdatei, Ausschreibung einlesen und Wettkämpfe per Import anlegen
        'competition_import' => ['label' => 'Wettkämpfe & Ergebnisse importieren', 'section' => 'trainer'],
        'records'        => ['label' => 'Rekorde',                'section' => 'trainer'],
        'goals'          => ['label' => 'Ziele',                  'section' => 'trainer'],
        'diary'          => ['label' => 'Einschätzungen',         'section' => 'trainer'],
        'motto'          => ['label' => 'Motto der Woche',        'section' => 'trainer'],
        'hall'           => ['label' => 'Hallenbelegung',         'section' => 'trainer'],
        'swimmer_times'  => ['label' => 'Meine Bestzeiten',       'section' => 'swimmer'],
        'swimmer_comps'  => ['label' => 'Meine Wettkämpfe',       'section' => 'swimmer'],
        'swimmer_goals'  => ['label' => 'Meine Ziele',            'section' => 'swimmer'],
        'swimmer_sessions'    => ['label' => 'Mein Training',     'section' => 'swimmer'],
        'swimmer_group_goals' => ['label' => 'Leistungskriterien', 'section' => 'swimmer'],
        'swimmer_motto'       => ['label' => 'Motto der Woche',   'section' => 'swimmer'],
        'parent_area'    => ['label' => 'Meine Kinder',           'section' => 'parent'],
        // Lesesicht fuer Mitglieder; Pflege bleibt unter "records" (Trainer-Bereich)
        'club_records'   => ['label' => 'Rekorde & Bestenlisten', 'section' => 'general'],
        // Kampfrichter
        'officials'      => ['label' => 'Kampfrichter & Lizenzen (alle pflegen)', 'section' => 'officials'],
        'officials_own'  => ['label' => 'Meine Qualifikationen (eigene pflegen)', 'section' => 'officials'],
        'official_requests' => ['label' => 'Kampfgericht anfragen, besetzen, melden (Obmann)', 'section' => 'officials'],
        // Reichweite
        'users_all'           => ['label' => 'Benutzer: alle Konten bearbeiten',                     'section' => 'scope'],
        'training_all'        => ['label' => 'Trainingseinheiten aller Gruppen verwalten',           'section' => 'scope'],
        'training_groups_all' => ['label' => 'Alle Trainingsgruppen & Kurse: Mitglieder und Trainer zuweisen', 'section' => 'scope'],
        // Schutz der Trainingspläne: sonst nur Trainer der Einheit/Gruppe (Voreinstellung: niemand außer Admin)
        'training_plans_all'  => ['label' => 'Trainingspläne aller Einheiten einsehen', 'section' => 'scope'],
        // Kalender und Abo: sonst nur Einträge der eigenen bzw. zugewiesenen Gruppen
        'calendar_all'        => ['label' => 'Kalender & Abo: Einheiten, Wettkämpfe, Termine und Einladungen aller Gruppen', 'section' => 'scope'],
        // Termine (Arten aus CalendarEvent::TYPES)
        'events_vereinstermin'    => ['label' => 'Vereinstermin anlegen',     'section' => 'events'],
        'events_ehrung'           => ['label' => 'Ehrung anlegen',            'section' => 'events'],
        'events_meldefrist'       => ['label' => 'Meldefrist anlegen',        'section' => 'events'],
        'events_vorstandssitzung' => ['label' => 'Vorstandssitzung anlegen und pflegen', 'section' => 'events'],
        'events_elternabend'      => ['label' => 'Elternabend anlegen',       'section' => 'events'],
        'events_team_event'       => ['label' => 'Team-Event anlegen',        'section' => 'events'],
        'events_sonstiges'        => ['label' => 'Sonstigen Termin anlegen',  'section' => 'events'],
        'events_manage'           => ['label' => 'Termine anderer bearbeiten und Einladungen verwalten', 'section' => 'events'],
        'events_all_groups'       => ['label' => 'Beliebige Gruppen einladen (sonst nur eigene)', 'section' => 'events'],
    ];

    const SECTIONS = [
        'general'   => 'Allgemein',
        'trainer'   => 'Trainer-Bereich',
        'scope'     => 'Reichweite (alle statt eigene)',
        'officials' => 'Kampfrichter',
        'events'    => 'Termine & Einladungen',
        'swimmer'   => 'Schwimmer',
        'parent'    => 'Eltern',
    ];

    const DEFAULT_PERMISSIONS = [
        'admin'        => ['calendar','users_lite','training','training_groups','competitions','records','goals','diary','motto','hall','swimmer_times','swimmer_comps','swimmer_goals','swimmer_sessions','swimmer_group_goals','swimmer_motto','parent_area','club_records'],
        'trainer'      => ['calendar','users_lite','training','training_groups','competitions','competition_import','records','goals','diary','motto','hall',
                           'events_vereinstermin','events_ehrung','events_meldefrist','events_elternabend','events_team_event','events_sonstiges','events_all_groups'],
        // Vorstand: Trainingseinheiten aller Gruppen (Martin, 07.10.2026), Obmann, Lizenzen
        'vorstand'     => ['calendar','calendar_all','users_lite','users_all','competitions','competition_import','records','training_all',
                           'officials','official_requests','events_vorstandssitzung','events_team_event','events_all_groups'],
        'kampfrichter' => ['calendar','competitions','competition_import','officials_own'],
        // Geschäftsstelle: Benutzer, Gruppen-/Kurszuweisung, Termine, Lizenzen – keine Trainingseinheiten
        'geschaeftsstelle' => ['calendar','users_lite','users_all','training_groups','training_groups_all','officials',
                           'events_vereinstermin','events_ehrung','events_meldefrist','events_elternabend','events_team_event','events_sonstiges',
                           'events_manage','events_all_groups'],
        'schwimmer'           => ['calendar','swimmer_times','swimmer_comps','swimmer_goals','swimmer_sessions','swimmer_group_goals','swimmer_motto','club_records','events_team_event'],
        'elternteil'          => ['calendar','parent_area','club_records'],
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

    /**
     * Darf dieser Benutzer eines der Rechte? Es zaehlen die Portal-Rolle und
     * alle Vereinsrollen (user_roles) – ein Trainer, der auch im Vorstand
     * ist, bekommt die Rechte beider Rollen.
     */
    public static function allows(User $user, string ...$menuKeys): bool
    {
        if ($user->role === 'admin') return true;

        $roles = array_unique(array_merge([(string) $user->role], $user->userRoles->pluck('role')->all()));
        foreach ($roles as $role) {
            if (!array_key_exists($role, User::ROLE_LABELS) || $role === 'admin') continue;
            foreach ($menuKeys as $key) {
                if (static::can($role, $key)) return true;
            }
        }
        return false;
    }

    public static function clearCache(): void
    {
        foreach (array_keys(User::ROLE_LABELS) as $role) {
            Cache::forget("menu_perm_{$role}");
        }
    }
}

<?php

namespace App\Support;

use App\Models\MenuPermission;
use App\Models\User;

/**
 * Menue des Portals: Seitenleiste und untere Navigation (mobil).
 *
 * Gruppiert nach Aufgabe, nicht nach Rolle (docs/frontend-audit.md, Kap. 6).
 * Sichtbar ist ein Eintrag nur, wenn die Route fuer die Rolle freigegeben ist -
 * dieselben Rollen und Matrix-Schluessel wie die Routen-Middleware. Wer hier
 * einen Eintrag ergaenzt, muss die Route genauso absichern.
 */
class Navigation
{
    /**
     * @return list<array{label: ?string, items: list<array{label: string, url: string, icon: string, active: bool}>}>
     */
    public static function sections(User $user): array
    {
        $role = $user->role;
        $can  = fn(string $key) => MenuPermission::can($role, $key);
        $is   = fn(string ...$roles) => in_array($role, $roles, true);

        $sections = [
            [null, [
                self::item('Dashboard', $user->homeUrl(), 'dashboard', ['admin.dashboard', 'trainer.dashboard', 'swimmer.dashboard', 'parent.dashboard'], $is('admin', 'trainer', 'schwimmer', 'elternteil')),
                self::item('Kalender', 'calendar.index', 'calendar', 'calendar.*', $can('calendar')),
                // Termine mit Einladung (Sitzungen, Elternabende, Team-Events), eigene und der Kinder
                self::item('Einladungen', 'invitations.index', 'inbox', 'invitations.*', $can('calendar')),
            ]],

            ['Training', [
                self::item('Trainingseinheiten', 'trainer.sessions.index', 'calendar', 'trainer.sessions.*', $is('trainer', 'admin') && $can('training')),
                self::item('Hallenbelegung', 'trainer.hall.index', 'building', 'trainer.hall.*', $is('trainer', 'admin') && $can('hall')),
                self::item('Trainingsgruppen', 'admin.training-groups.index', 'users', 'admin.training-groups.*', $is('trainer', 'admin') && $can('training_groups')),
                self::item('Ziele & Kriterien', 'trainer.goals.index', 'chart', 'trainer.goals.*', $is('trainer', 'admin') && $can('goals')),
                self::item('Einschätzungen', 'trainer.diary.overview', 'pie', 'trainer.diary.*', $is('trainer', 'admin') && $can('diary')),
                self::item('Motto der Woche', 'trainer.motto.index', 'bulb', 'trainer.motto.*', $is('trainer', 'admin') && $can('motto')),
            ]],

            ['Mein Bereich', [
                self::item('Mein Training', 'swimmer.sessions', 'bolt', ['swimmer.sessions', 'swimmer.session.*'], $is('schwimmer') && $can('swimmer_sessions')),
                self::item('Meine Bestzeiten', 'swimmer.times', 'clock', 'swimmer.times', $is('schwimmer') && $can('swimmer_times')),
                self::item('Meine Wettkämpfe', 'swimmer.competitions', 'check-circle', 'swimmer.competitions', $is('schwimmer') && $can('swimmer_comps')),
                self::item('Meine Ziele', 'swimmer.goals.index', 'chart', 'swimmer.goals.*', $is('schwimmer') && $can('swimmer_goals')),
                self::item('Leistungskriterien', 'swimmer.group-goals.index', 'badge', 'swimmer.group-goals.*', $is('schwimmer') && $can('swimmer_group_goals')),
                self::item('Motto der Woche', 'swimmer.motto.index', 'bulb', 'swimmer.motto.*', $is('schwimmer') && $can('swimmer_motto')),
            ]],
        ];

        // Eltern: je Kind ein eigener Abschnitt mit dessen Seiten
        if ($is('elternteil') && $can('parent_area')) {
            foreach ($user->children()->where('active', true)->orderBy('firstname')->get() as $child) {
                $sections[] = [$child->firstname, [
                    self::childItem('Training', 'parent.child.trainings', $child, 'bolt'),
                    self::childItem('Wettkämpfe', 'parent.child.competitions', $child, 'check-circle'),
                    self::childItem('Anmeldungen', 'parent.child.signups', $child, 'inbox'),
                    self::childItem('Zeiten', 'parent.child.times', $child, 'clock'),
                ]];
            }
        }

        $sections[] = ['Wettkampf', [
            self::item('Wettkämpfe', 'admin.competitions.index', 'check-circle', 'admin.competitions.*', $is('trainer', 'admin', 'vorstand', 'kampfrichter') && $can('competitions')),
            // Pflegen (Trainer/Vorstand) oder lesen (Mitglieder) - nie beides
            $is('trainer', 'admin', 'vorstand') && $can('records')
                ? self::item('Rekorde & Bestenlisten', 'admin.records.index', 'sparkles', 'admin.records.*', true)
                : self::item('Rekorde & Bestenlisten', 'records.public', 'sparkles', 'records.public', $can('club_records')),
        ]];

        $sections[] = ['Mitglieder', [
            $is('admin')
                ? self::item('Benutzer', 'admin.users.index', 'users', 'admin.users.*', true)
                : self::item('Benutzer', 'users-lite.index', 'users', 'users-lite.*', $can('users_lite')),
            self::item('Ernährungsberatung', 'nutrition.index', 'document', 'nutrition.*', $is('ernaehrungsberater', 'admin')),
            self::item('Sportmedizin', 'teamdoctor.index', 'heart', 'teamdoctor.*', $is('teamarzt', 'admin')),
        ]];

        $sections[] = ['Daten', [
            // Alle Datei-Importe; sichtbar, sobald die Rolle mindestens einen nutzen darf
            self::item('Import-Center', 'imports.index', 'upload', ['imports.*', 'trainer.dsv-import.*', 'admin.webclub-import.*'], !empty(ImportCatalog::for($user))),
            self::item('Crawler & Import-Log', 'admin.import-log.index', 'download', 'admin.import-log.*', $is('admin')),
            self::item('Korrekturen', 'admin.corrections.times.index', 'wrench', 'admin.corrections.*', $is('admin')),
            self::item('Datenprüfung Training', 'admin.training-audit', 'shield', 'admin.training-audit', $is('admin')),
        ]];

        $sections[] = ['System', [
            self::item('Einstellungen', 'admin.settings.index', 'settings', 'admin.settings.*', $is('admin')),
            self::item('Berechtigungen', 'admin.permissions.index', 'shield', 'admin.permissions.*', $is('admin')),
            self::item('Protokoll', 'admin.logs.index', 'clipboard', 'admin.logs.*', $is('admin')),
            self::item('Mail-Protokoll', 'admin.mail-log.index', 'mail', 'admin.mail-log.*', $is('admin')),
            self::item('WA-Punkte', 'admin.wa-scoring.index', 'chart', 'admin.wa-scoring.*', $is('admin')),
            self::item('DSGVO-Anfragen', 'admin.dsgvo.index', 'shield', 'admin.dsgvo.*', $is('admin')),
            self::item('UI-Bausteine', 'admin.ui', 'dashboard', 'admin.ui', $is('admin')),
        ]];

        // Leere Abschnitte und ausgeblendete Eintraege entfernen
        $out = [];
        foreach ($sections as [$label, $items]) {
            $items = array_values(array_filter($items));
            if ($items) $out[] = ['label' => $label, 'items' => $items];
        }
        return $out;
    }

    /** Konto-Menue (unten in der Seitenleiste) */
    public static function account(User $user): array
    {
        return array_values(array_filter([
            self::item('Mein Profil', 'profile.index', 'user', 'profile.*', true),
            self::item('Gesundheitsdaten', 'health.index', 'document', 'health.*', true),
            self::item('Support', 'support.create', 'lifebuoy', 'support.*', true),
        ]));
    }

    /**
     * Untere Navigation fuer Schwimmer und Eltern (mobil): hoechstens vier
     * Ziele, das fuenfte oeffnet das volle Menue. Leer = keine untere Leiste.
     *
     * @return list<array{label: string, url: string, icon: string, active: bool}>
     */
    public static function bottom(User $user): array
    {
        $role = $user->role;
        $can  = fn(string $key) => MenuPermission::can($role, $key);

        if ($role === 'schwimmer') {
            $items = [
                self::item('Start', 'swimmer.dashboard', 'home', 'swimmer.dashboard', true),
                self::item('Training', 'swimmer.sessions', 'bolt', ['swimmer.sessions', 'swimmer.session.*'], $can('swimmer_sessions')),
                self::item('Zeiten', 'swimmer.times', 'clock', 'swimmer.times', $can('swimmer_times')),
                self::item('Wettkämpfe', 'swimmer.competitions', 'check-circle', 'swimmer.competitions', $can('swimmer_comps')),
            ];
        } elseif ($role === 'elternteil' && $can('parent_area')) {
            $children = $user->children()->where('active', true)->get();
            // Ein Kind: direkt dessen Seiten. Mehrere: die Kinder stehen im Menue.
            $items = $children->count() === 1
                ? [
                    self::item('Start', 'parent.dashboard', 'home', 'parent.dashboard', true),
                    self::childItem('Training', 'parent.child.trainings', $children->first(), 'bolt'),
                    self::childItem('Zeiten', 'parent.child.times', $children->first(), 'clock'),
                    self::childItem('Wettkämpfe', 'parent.child.competitions', $children->first(), 'check-circle'),
                ]
                : [
                    self::item('Start', 'parent.dashboard', 'home', 'parent.dashboard', true),
                    self::item('Kalender', 'calendar.index', 'calendar', 'calendar.*', $can('calendar')),
                    self::item('Rekorde', 'records.public', 'sparkles', 'records.public', $can('club_records')),
                    self::item('Profil', 'profile.index', 'user', 'profile.*', true),
                ];
        } else {
            return [];
        }

        return array_values(array_filter($items));
    }

    /** @param string|string[] $activePattern */
    private static function item(string $label, string $routeOrUrl, string $icon, string|array $activePattern, bool $visible): ?array
    {
        if (!$visible) return null;
        $url = str_starts_with($routeOrUrl, 'http') || str_starts_with($routeOrUrl, '/')
            ? $routeOrUrl
            : route($routeOrUrl);

        return ['label' => $label, 'url' => $url, 'icon' => $icon, 'active' => request()->routeIs(...(array) $activePattern)];
    }

    private static function childItem(string $label, string $route, User $child, string $icon): array
    {
        return [
            'label'  => $label,
            'url'    => route($route, $child->id),
            'icon'   => $icon,
            'active' => request()->routeIs($route) && (int) request()->route('childId') === $child->id,
        ];
    }
}

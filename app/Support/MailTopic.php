<?php

namespace App\Support;

use App\Models\User;

/**
 * Welche Mails es gibt und wer sie bekommen kann.
 *
 * Grundregel ist Opt-in: Ohne eigene Einstellung bekommt niemand Mails ausser
 * denen zum eigenen Konto - die lassen sich nicht abbestellen, weil ohne sie
 * der Zugang nicht funktioniert (Willkommen, Passwort). Alles andere muss im
 * Profil einzeln eingeschaltet werden.
 */
final class MailTopic
{
    /** Kontomails: immer, nicht abwaehlbar */
    public const ACCOUNT = 'account';

    /**
     * Der Katalog. 'roles' schraenkt ein, wem das Thema im Profil ueberhaupt
     * angeboten wird - ein Schwimmer braucht keine Rueckantworten-Mail.
     *
     * @var array<string, array{label: string, description: string, mandatory?: bool, roles: list<string>, group: string}>
     */
    public const TOPICS = [
        self::ACCOUNT => [
            'group'       => 'Konto',
            'label'       => 'Konto und Zugang',
            'description' => 'Willkommensmail, Zugangsdaten und Passwortänderungen. Lässt sich nicht abschalten.',
            'mandatory'   => true,
            'roles'       => ['*'],
        ],

        // ── Für Sportlerinnen, Sportler und Eltern ───────────────────────────
        'competition_invitation' => [
            'group'       => 'Wettkämpfe',
            'label'       => 'Einladungen zu Wettkämpfen',
            'description' => 'Sobald eine Abfrage zu einem Wettkampf für dich geöffnet wird.',
            'roles'       => ['schwimmer', 'elternteil', 'kampfrichter'],
        ],
        'competition_reminder' => [
            'group'       => 'Wettkämpfe',
            'label'       => 'Erinnerungen',
            'description' => 'Erinnerung, wenn eine Rückmeldung noch aussteht oder ein Wettkampf bevorsteht.',
            'roles'       => ['schwimmer', 'elternteil', 'kampfrichter'],
        ],
        'own_records' => [
            'group'       => 'Leistungen',
            'label'       => 'Eigene Rekorde und Bestzeiten',
            'description' => 'Wenn du einen Vereinsrekord aufstellst oder eine neue Bestzeit geschwommen bist.',
            'roles'       => ['schwimmer', 'elternteil'],
        ],
        'own_goals' => [
            'group'       => 'Leistungen',
            'label'       => 'Ziele und Leistungskriterien',
            'description' => 'Wenn ein Trainer ein Ziel oder ein Leistungskriterium für dich bewertet.',
            'roles'       => ['schwimmer', 'elternteil'],
        ],

        // ── Für Trainer, Vorstand und Verwaltung ─────────────────────────────
        'signup_responses' => [
            'group'       => 'Trainerinfos',
            'label'       => 'Rückantworten auf Einladungen',
            'description' => 'Wenn jemand aus deinen Gruppen auf eine Wettkampf-Abfrage antwortet.',
            'roles'       => ['trainer', 'vorstand', 'admin'],
        ],
        'self_assessments' => [
            'group'       => 'Trainerinfos',
            'label'       => 'Selbsteinschätzungen',
            'description' => 'Wenn eine Sportlerin oder ein Sportler eine Selbsteinschätzung abgibt.',
            'roles'       => ['trainer', 'vorstand', 'admin'],
        ],
        'goal_submissions' => [
            'group'       => 'Trainerinfos',
            'label'       => 'Zielsetzungen',
            'description' => 'Wenn jemand aus deinen Gruppen ein Ziel einträgt oder ändert.',
            'roles'       => ['trainer', 'vorstand', 'admin'],
        ],
        'club_records' => [
            'group'       => 'Trainerinfos',
            'label'       => 'Neue Vereinsrekorde',
            'description' => 'Wenn im Verein ein neuer Rekord aufgestellt wird.',
            'roles'       => ['trainer', 'vorstand', 'admin'],
        ],
        'motto_reminder' => [
            'group'       => 'Trainerinfos',
            'label'       => 'Motto der Woche',
            'description' => 'Erinnerung, wenn für die kommende Woche noch kein Motto eingetragen ist.',
            'roles'       => ['trainer', 'vorstand', 'admin', 'schwimmer'],
        ],
    ];

    /** Themen, die dieser Rolle im Profil angeboten werden. */
    public static function forRole(?string $role): array
    {
        return array_filter(
            self::TOPICS,
            fn($topic) => in_array('*', $topic['roles'], true) || in_array($role, $topic['roles'], true)
        );
    }

    /** Themen gruppiert, fuer die Darstellung im Profil. */
    public static function groupedForRole(?string $role): array
    {
        $grouped = [];
        foreach (self::forRole($role) as $key => $topic) {
            $grouped[$topic['group']][$key] = $topic;
        }
        return $grouped;
    }

    public static function exists(string $topic): bool
    {
        return array_key_exists($topic, self::TOPICS);
    }

    public static function isMandatory(string $topic): bool
    {
        return (bool) (self::TOPICS[$topic]['mandatory'] ?? false);
    }

    public static function label(string $topic): string
    {
        return self::TOPICS[$topic]['label'] ?? $topic;
    }

    /**
     * Will dieser Benutzer diese Mail bekommen?
     *
     * Kontomails immer. Alles andere nur, wenn im Profil eingeschaltet - und
     * nur, wenn das Thema zur Rolle passt: wechselt jemand die Rolle, soll eine
     * alte Einstellung nicht unbemerkt weiterwirken.
     */
    public static function wants(User $user, string $topic): bool
    {
        if (!self::exists($topic))     return false;
        if (self::isMandatory($topic)) return true;
        if (!array_key_exists($topic, self::forRole($user->role))) return false;

        $prefs = $user->mail_preferences ?? [];

        return (bool) ($prefs[$topic] ?? false);
    }
}

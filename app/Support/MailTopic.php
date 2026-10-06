<?php

namespace App\Support;

use App\Models\User;

/**
 * Welche Mails es gibt und wer sie bekommen kann.
 *
 * Grundregel ist Opt-in: Ohne eigene Einstellung bekommt niemand Mails ausser
 * denen zum eigenen Konto - die lassen sich nicht abbestellen, weil ohne sie
 * der Zugang nicht funktioniert (Willkommen, Passwort). Alles andere muss im
 * Profil einzeln eingeschaltet werden - ausser Themen mit 'default' => true
 * (bewusste Ausnahme, z. B. Trainingsausfall): an, bis jemand es abwaehlt.
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
            'parent_label'       => 'Einladungen zu Wettkämpfen',
            'parent_description' => 'Sobald eine Abfrage zu einem Wettkampf für eines deiner Kinder geöffnet wird.',
        ],
        'competition_reminder' => [
            'group'       => 'Wettkämpfe',
            'label'       => 'Erinnerungen',
            'description' => 'Erinnerung, wenn eine Rückmeldung noch aussteht oder ein Wettkampf bevorsteht.',
            'roles'       => ['schwimmer', 'elternteil', 'kampfrichter'],
            'parent_label'       => 'Erinnerungen',
            'parent_description' => 'Erinnerung, wenn für eines deiner Kinder eine Rückmeldung noch aussteht.',
        ],
        // ── Absagen: voreingestellt an (Entscheidungen Martin 30.09. und 04.10.2026) ──
        'training_changes' => [
            'group'       => 'Absagen',
            'label'       => 'Trainingsausfall',
            'description' => 'Wenn ein Training, zu dem du gehörst, ausfällt.',
            'roles'       => ['schwimmer', 'elternteil'],
            // Bewusste Ausnahme vom Opt-in (Entscheidung Martin, 30.09.2026): ein
            // Ausfall betrifft jeden - abwaehlbar bleibt es trotzdem
            'default'     => true,
            'parent_label'       => 'Trainingsausfall',
            'parent_description' => 'Wenn ein Training eines deiner Kinder ausfällt.',
        ],
        'carpool_changes' => [
            'group'       => 'Absagen',
            'label'       => 'Fahrgemeinschaft fällt weg',
            'description' => 'Wenn eine Fahrgemeinschaft zu einem Wettkampf, bei der du mitfährst, abgesagt wird.',
            'roles'       => ['schwimmer', 'elternteil'],
            'default'     => true,
            'parent_label'       => 'Fahrgemeinschaft fällt weg',
            'parent_description' => 'Wenn eine Fahrgemeinschaft, bei der eines deiner Kinder mitfährt, abgesagt wird.',
        ],
        // ── Einladungen: voreingestellt an (Entscheidung Martin, 06.10.2026) ──
        'event_invitations' => [
            'group'       => 'Einladungen',
            'label'       => 'Einladungen zu Terminen',
            'description' => 'Einladungen zu Vorstandssitzungen, Elternabenden und Team-Events, Erinnerungen an deine Rückmeldung und neue Protokolle.',
            'roles'       => ['*'],
            'default'     => true,
            'parent_description' => 'Einladungen zu Elternabenden und Team-Events (auch für deine Kinder), Erinnerungen und neue Protokolle.',
        ],
        'official_requests' => [
            'group'       => 'Einladungen',
            'label'       => 'Kampfrichter-Anfragen',
            'description' => 'Wenn der Vorstand Kampfrichter für einen Wettkampf sucht und dich anfragt.',
            'roles'       => ['*'],
            'default'     => true,
        ],
        'own_records' => [
            'group'       => 'Leistungen',
            'label'       => 'Eigene Rekorde und Bestzeiten',
            'description' => 'Wenn du einen Vereinsrekord aufstellst oder eine neue Bestzeit geschwommen bist.',
            'roles'       => ['schwimmer', 'elternteil'],
            'parent_label'       => 'Rekorde deiner Kinder',
            'parent_description' => 'Wenn eines deiner Kinder einen Vereinsrekord aufstellt.',
        ],
        'own_goals' => [
            'group'       => 'Leistungen',
            'label'       => 'Ziele und Leistungskriterien',
            'description' => 'Wenn ein Trainer ein Ziel oder ein Leistungskriterium für dich bewertet.',
            'roles'       => ['schwimmer', 'elternteil'],
            'parent_label'       => 'Ziele und Leistungskriterien deiner Kinder',
            'parent_description' => 'Wenn ein Trainer ein Ziel oder Leistungskriterium eines deiner Kinder bewertet oder kommentiert.',
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
            // Eltern bekommen diese Mails zu Ereignissen ihrer Kinder - so benennen
            if ($role === 'elternteil' && isset($topic['parent_label'])) {
                $topic['label']       = $topic['parent_label'];
                $topic['description'] = $topic['parent_description'];
            }
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

        return self::chosen($user->mail_preferences ?? [], $topic);
    }

    /** Eigene Wahl - oder, solange keine getroffen ist, die Voreinstellung des Themas */
    public static function chosen(array $prefs, string $topic): bool
    {
        return (bool) ($prefs[$topic] ?? (self::TOPICS[$topic]['default'] ?? false));
    }
}

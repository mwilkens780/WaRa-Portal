<?php

namespace App\Support;

use App\Models\MenuPermission;
use App\Models\User;

/**
 * Alle Datei-Importe an einer Stelle (Import-Center, docs/frontend-audit.md 6).
 *
 * Vorher gab es elf Abläufe mit elf Einstiegen, zwei davon hießen
 * "WebClub-Import", einer war gar nicht verlinkt. Hier steht je Import: was
 * er tut, woher die Datei kommt, wer ihn nutzen darf und wo er beginnt.
 *
 * Eintraege mit 'upload' haben keine eigene Seite fuer Schritt 1 - das
 * Formular wird aus der Beschreibung erzeugt (imports/_upload), im
 * Import-Center und am Objekt (z. B. Rekordseite) gleich.
 *
 * Eintraege mit 'context' gehoeren zu einem Objekt (Wettkampf, Gruppe):
 * Die Kachel fuehrt zur Liste, der Import selbst steht am Objekt.
 */
final class ImportCatalog
{
    public static function all(): array
    {
        return [
            'members' => [
                'area'    => 'Mitglieder',
                'title'   => 'Mitglieder aus WebClub',
                'text'    => 'Legt neue Mitgliedskonten an und aktualisiert bestehende mit den Angaben aus WebClub. Leere Felder in der Datei löschen nichts.',
                'source'  => 'WebClub → Mitglieder → Export (CSV)',
                'formats' => '.csv, .txt',
                'icon'    => 'users',
                'route'   => 'admin.webclub-import.index',
                'roles'   => ['admin'],
            ],
            'results-file' => [
                'area'    => 'Wettkampf',
                'title'   => 'Ergebnisse aus DSV-/Lenex-Datei',
                'text'    => 'Übernimmt die Zeiten der zugeordneten Schwimmer. Gibt es die Veranstaltung schon, werden die Ergebnisse mit ihr zusammengeführt, sonst entsteht ein neuer Wettkampf. Bestzeiten werden erkannt.',
                'source'  => 'DSV, Swimrankings, WebClub, Ausrichter',
                'formats' => '.dsv7, .dsv8, .dsv8z, .lef, .lxf, .xml, .txt',
                'icon'    => 'check-circle',
                'route'   => 'trainer.dsv-import.index',
                'roles'   => ['trainer', 'admin'],
                'menu'    => 'competitions',
            ],
            'results-context' => [
                'area'    => 'Wettkampf',
                'title'   => 'Import zu einem bestimmten Wettkampf',
                'text'    => 'Im Wettkampf unter „Import“: Ergebnisse (DSV/Lenex oder WebClub-CSV) und die Definitionsdatei mit Strecken und Zeitplan.',
                'source'  => 'Datei zum jeweiligen Wettkampf',
                'formats' => '.dsv7, .dsv8, .dsv8z, .lef, .lxf, .xml, .csv',
                'icon'    => 'check-circle',
                'route'   => 'admin.competitions.index',
                'roles'   => ['trainer', 'vorstand', 'kampfrichter', 'admin'],
                'menu'    => 'competitions',
                'context' => 'Wettkampf wählen',
            ],
            'competition-lenex' => [
                'area'    => 'Wettkampf',
                'title'   => 'Wettkampf aus Ausschreibung anlegen',
                'text'    => 'Liest eine Ausschreibung (Lenex/DSV7/DSV8) und füllt damit das Formular für einen neuen Wettkampf mit Strecken vor.',
                'source'  => 'Ausschreibung des Ausrichters',
                'formats' => '.dsv7, .dsv8, .dsv8z, .lef, .lxf, .xml, .txt',
                'icon'    => 'plus',
                'route'   => 'admin.competitions.create',
                'roles'   => ['admin'],
            ],
            'events-webclub' => [
                'area'    => 'Wettkampf',
                'title'   => 'Veranstaltungen aus WebClub',
                'text'    => 'Übernimmt Wettkampftermine aus der WebClub-Terminliste als Wettkämpfe. Bereits vorhandene werden erkannt.',
                'source'  => 'WebClub → Termine → Export (CSV)',
                'formats' => '.csv, .txt',
                'icon'    => 'calendar',
                'route'   => 'admin.competitions.webclub-import.form',
                'roles'   => ['admin'],
            ],
            'records' => [
                'area'    => 'Rekorde',
                'title'   => 'Vereins- und Landesrekorde',
                'text'    => 'Liest eine Rekordliste ein und zeigt vor dem Übernehmen, was neu ist und was sich ändert.',
                'source'  => 'Rekordliste des Vereins oder Verbands',
                'formats' => '.xlsx, .xls, .csv, .pdf, .docx, .doc, .txt',
                'icon'    => 'sparkles',
                'roles'   => ['admin'],
                'back'    => ['admin.records.index', 'Zu den Rekorden'],
                'upload'  => [
                    'action' => 'admin.records.import.upload',
                    'name'   => 'record_file',
                    'label'  => 'Rekordliste',
                    'accept' => '.xlsx,.xls,.csv,.pdf,.docx,.doc,.txt',
                    'max_mb' => 20,
                    'fields' => [
                        ['name' => 'import_type', 'label' => 'Art der Rekorde', 'options' => [
                            'vereinsrekord' => 'Vereinsrekorde',
                            'landesrekord'  => 'Landesrekorde',
                        ]],
                    ],
                ],
            ],
            'bestlist' => [
                'area'    => 'Rekorde',
                'title'   => 'Ewige Bestenliste',
                'text'    => 'Übernimmt die historische Vereins-Bestenliste. Bahn und Geschlecht stehen in der Datei, die Plätze werden neu berechnet.',
                'source'  => 'Vereinsvorlage „Ewige Vereins-Bestenliste“ (Excel)',
                'formats' => '.xlsx',
                'icon'    => 'chart',
                'roles'   => ['admin'],
                'back'    => ['admin.records.index', 'Zu den Rekorden'],
                'upload'  => [
                    'action' => 'admin.bestlist.import.upload',
                    'name'   => 'bestlist_file',
                    'label'  => 'Bestenliste',
                    'accept' => '.xlsx',
                    'max_mb' => 20,
                    'hint'   => 'Kopfzeile mit Bahn und Geschlecht, darunter Blöcke je Strecke mit Platz, Name, Jahrgang (zweistellig), Zeit und Jahr.',
                ],
            ],
            'hall' => [
                'area'    => 'Training',
                'title'   => 'Hallenbelegungsplan',
                'text'    => 'Übernimmt die Vorlage „Hallenbelegung SuV“ als Belegungen und – bei zugeordneten Gruppen – als Trainingsserien. Bestehendes bleibt unverändert.',
                'source'  => 'Excel-Vorlage der Stadt',
                'formats' => '.xlsx',
                'icon'    => 'building',
                'route'   => 'trainer.hall.import.index',
                'roles'   => ['trainer', 'admin'],
                'menu'    => 'hall',
            ],
            'group-csv' => [
                'area'    => 'Training',
                'title'   => 'Mitglieder einer Trainingsgruppe',
                'text'    => 'In der Trainingsgruppe: Namensliste (CSV) mit der Gruppe abgleichen – fehlende Schwimmer hinzufügen, nicht mehr gelistete entfernen. Die Vorschau zeigt beides vorher.',
                'source'  => 'Liste der Gruppe (CSV)',
                'formats' => '.csv, .txt',
                'icon'    => 'users',
                'route'   => 'admin.training-groups.index',
                'roles'   => ['trainer', 'admin'],
                'menu'    => 'training_groups',
                'context' => 'Gruppe wählen',
            ],
        ];
    }

    /** Importe, die dieser Benutzer nutzen darf. */
    public static function for(User $user): array
    {
        return array_filter(self::all(), fn($i) => self::allows($user, $i));
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function allows(User $user, array $import): bool
    {
        // Mit Matrix-Schlüssel entscheidet allein die Matrix; ohne bleibt der
        // Import fest bei den genannten Rollen (Systemimporte: nur Admin)
        return empty($import['menu'])
            ? in_array($user->role, $import['roles'], true)
            : MenuPermission::allows($user, $import['menu']);
    }

    /** Wohin die Kachel fuehrt */
    public static function url(string $key, array $import): string
    {
        return isset($import['upload']) ? route('imports.show', $key) : route($import['route']);
    }
}

@extends('layouts.app')
@section('title', 'UI-Bausteine')
@section('page-title', 'UI-Bausteine')

{{--
    Musterseite aller x-ui-Bausteine (nur Admin). Nachschlagewerk beim Umbau
    der Seiten und Testflaeche fuer Tastatur/Screenreader. Regeln: docs/frontend-audit.md, Kap. 5.
--}}
@section('content')
<div class="space-y-6 max-w-4xl" x-data="{ tab: 'eins' }">

    <x-ui.page-header :back="route('admin.dashboard')" back-label="Dashboard" subtitle="Alle Bausteine mit ihren Zuständen">
        <x-slot:actions>
            <x-ui.button icon="plus">Primäraktion</x-ui.button>
        </x-slot:actions>
        <x-slot:menu>
            <x-ui.menu label="Weitere Aktionen">
                <x-ui.menu-item href="#">Exportieren</x-ui.menu-item>
                <x-ui.menu-item tone="danger" @click="$confirm({ title: 'Wirklich löschen?', confirmLabel: 'Löschen', danger: true })">Löschen …</x-ui.menu-item>
            </x-ui.menu>
        </x-slot:menu>
    </x-ui.page-header>

    <x-ui.card title="Buttons" meta="Eine Primäraktion je Bereich, Rot nur für Zerstörendes">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button>Primär</x-ui.button>
            <x-ui.button variant="secondary">Sekundär</x-ui.button>
            <x-ui.button variant="ghost">Ghost</x-ui.button>
            <x-ui.button variant="danger" icon="trash">Löschen</x-ui.button>
            <x-ui.button size="sm" variant="secondary" icon="download">Klein</x-ui.button>
            <x-ui.button disabled>Deaktiviert</x-ui.button>
            <x-ui.icon-button icon="pencil" label="Bearbeiten" />
            <x-ui.icon-button icon="trash" label="Löschen" tone="danger" />
        </div>
    </x-ui.card>

    <x-ui.card title="Rückmeldungen">
        <div class="space-y-3">
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" @click="$toast('Gespeichert.')">Toast</x-ui.button>
                <x-ui.button variant="secondary" @click="$toast('Eintrag gelöscht.', { action: { label: 'Rückgängig', run: () => $toast('Wiederhergestellt.') } })">Toast mit Rückgängig</x-ui.button>
                <x-ui.button variant="secondary" @click="$toast('Speichern fehlgeschlagen.', { type: 'error' })">Fehler-Toast</x-ui.button>
                <x-ui.button variant="secondary" @click="$confirm({ title: 'Einheit löschen?', text: 'Anwesenheit und Zeiten dieser Einheit gehen verloren.', confirmLabel: 'Löschen', danger: true }).then(ok => $toast(ok ? 'Bestätigt' : 'Abgebrochen', { type: 'info' }))">Bestätigen</x-ui.button>
                <x-ui.button variant="secondary" @click="$confirm({ title: 'Alle Benutzer löschen?', confirmLabel: 'Löschen', danger: true, requireText: 'LÖSCHEN' })">Mit Tipp-Bestätigung</x-ui.button>
                <x-ui.button variant="secondary" @click="$prompt({ title: 'Link einfügen', label: 'Adresse' }).then(v => v && $toast(v))">Eingabe</x-ui.button>
            </div>
            <x-ui.alert tone="info">Hinweis im Seitenfluss.</x-ui.alert>
            <x-ui.alert tone="success">Erfolgreich gespeichert.</x-ui.alert>
            <x-ui.alert tone="warning" dismissible>Warnung – schließbar.</x-ui.alert>
            <x-ui.alert tone="error">Fehler bleibt stehen, bis er behoben ist.</x-ui.alert>
        </div>
    </x-ui.card>

    <x-ui.card title="Badges">
        <div class="flex flex-wrap gap-2">
            <x-ui.badge>Neutral</x-ui.badge>
            <x-ui.badge tone="brand">Marke</x-ui.badge>
            <x-ui.badge tone="success">Zugesagt</x-ui.badge>
            <x-ui.badge tone="warning">Ausstehend</x-ui.badge>
            <x-ui.badge tone="danger">Abgesagt</x-ui.badge>
            <x-ui.badge tone="info">Info</x-ui.badge>
        </div>
    </x-ui.card>

    <x-ui.card title="Formular" meta="Label, Hilfe und Fehler sind mit dem Feld verknüpft">
        <form class="grid sm:grid-cols-2 gap-4" @submit.prevent="$toast('Nur ein Muster.')">
            <x-ui.field label="Vorname" name="demo_first" required />
            <x-ui.field label="Geburtsdatum" name="demo_date" type="date" hint="Für Altersklassen" />
            <x-ui.field label="Typ" name="demo_type" as="select">
                <option>Training</option><option>Wettkampf</option>
            </x-ui.field>
            <x-ui.field label="Notiz" name="demo_note" as="textarea" rows="2" class="sm:col-span-2" />
            <div class="sm:col-span-2"><x-ui.button type="submit">Speichern</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        <x-ui.tabs model="tab" :tabs="['eins' => 'Übersicht', 'zwei' => ['label' => 'Ergebnisse', 'count' => 12], 'drei' => 'Dokumente']" />
        <div class="p-5">
            <div x-show="tab === 'eins'" role="tabpanel" id="panel-eins" aria-labelledby="tab-eins">Reiter 1 – Pfeiltasten wechseln den Reiter.</div>
            <div x-show="tab === 'zwei'" role="tabpanel" id="panel-zwei" aria-labelledby="tab-zwei" x-cloak>Reiter 2</div>
            <div x-show="tab === 'drei'" role="tabpanel" id="panel-drei" aria-labelledby="tab-drei" x-cloak>
                <x-ui.empty-state icon="document" title="Noch keine Dokumente" text="Lade die Ausschreibung als PDF hoch.">
                    <x-ui.button icon="upload" variant="secondary">Hochladen</x-ui.button>
                </x-ui.empty-state>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Aufklappbar" collapsible storage-key="ui-demo" :open="false" meta="Zustand wird gemerkt">
        Inhalt der aufklappbaren Karte.
    </x-ui.card>

    <x-ui.card title="Dialog">
        <x-ui.button variant="secondary" @click="$dispatch('open-dialog', 'demo')">Dialog öffnen</x-ui.button>
    </x-ui.card>
</div>

<x-ui.dialog name="demo" title="Beispiel-Dialog" description="Mobil als Bottom-Sheet, Fokus bleibt im Dialog." guard>
    <div class="space-y-4">
        <x-ui.field label="Bezeichnung" name="demo_label" data-autofocus />
        <p class="text-sm text-gray-600">Nach einer Eingabe fragt der Dialog beim Schließen nach.</p>
    </div>
    <x-slot:footer>
        <x-ui.button variant="secondary" @click="$dispatch('close-dialog', 'demo')">Abbrechen</x-ui.button>
        <x-ui.button @click="$dispatch('close-dialog', 'demo'); $toast('Gespeichert.')">Speichern</x-ui.button>
    </x-slot:footer>
</x-ui.dialog>
@endsection

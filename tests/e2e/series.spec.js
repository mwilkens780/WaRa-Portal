/**
 * Trainingsserie: eine Seite fuer alles Regelmaessige, eine Belegung je Bahn.
 *
 * Regression 30.09.2026: "Serie bearbeiten" legte je Einheit eine eigene
 * wochentliche Belegung an; im Hallenplan lagen sie unsichtbar uebereinander.
 */
import { test, expect } from './fixtures.js';
import { authFile, ready, collectErrors } from './helpers.js';

test.use({ storageState: authFile('admin') });

async function openSeries(page) {
    // Serie über die Einheitenliste öffnen (E2eSeeder: "Frühtraining", wöchentlich)
    await page.goto('/trainer/training');
    await ready(page);
    await page.locator('details').filter({ hasText: 'Frühtraining' }).first().locator('summary').click();
    await page.getByRole('link', { name: 'Serie öffnen' }).first().click();
    await page.waitForURL(/\/training\/serie\/[0-9a-f-]+$/);
    await ready(page);
}

test('Serie mit Bahn speichern ergibt genau eine Belegung im Hallenplan', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'schreibt Daten - einmal genügt');
    const errors = collectErrors(page);

    await openSeries(page);
    for (let i = 0; i < 2; i++) {
        // Zweimal speichern: darf nichts verdoppeln
        await page.getByRole('checkbox', { name: /Bahn 2/ }).check();
        await page.getByRole('button', { name: 'Serie speichern' }).click();
        await page.waitForURL(/\/training\/serie\/[0-9a-f-]+$/);
        // Nach dem Speichern steht die Bahn weiter drin (vorher: "nicht gespeichert")
        await expect(page.getByRole('checkbox', { name: /Bahn 2/ })).toBeChecked();
    }

    await page.goto('/trainer/hall');
    await ready(page);
    // Belegungen mit Gruppe zeigen den Gruppennamen - daher ueber Bahn und Serienzeit (06:00) suchen
    const belegungen = page.locator('[data-booking-id][aria-label*="Bahn 2"][aria-label*="06:00"]');
    // Woche und Tag rendern dieselbe Belegung - eindeutige IDs zaehlen
    const ids = new Set(await belegungen.evaluateAll((els) => els.map((e) => e.getAttribute('data-booking-id'))));
    expect(ids.size, 'eine Belegung der Serie auf Bahn 2').toBe(1);
    expect(errors).toEqual([]);
});

test('Termin fällt aus und findet wieder statt', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'schreibt Daten - einmal genügt');
    const errors = collectErrors(page);

    await openSeries(page);
    await page.getByRole('tab', { name: /Termine/ }).click();
    await page.getByRole('button', { name: 'Fällt aus' }).first().click();

    const dialog = page.getByRole('dialog', { name: 'Termin fällt aus' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Grund (optional)').fill('Hallenschließung');
    await dialog.getByRole('button', { name: 'Fällt aus' }).click();

    // Nach dem Neuladen steht die Seite wieder auf "Termine".
    // Bleibender Zustand statt Erfolgsmeldung (die verschwindet nach ein paar Sekunden)
    const termineTab = page.getByRole('tab', { name: /Termine/ });
    await expect(termineTab).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByText('1 Termin(e) fallen aus')).toBeVisible();
    await expect(page.getByText('Hallenschließung')).toBeVisible();

    await page.getByRole('button', { name: 'Findet statt' }).first().click();
    await expect(page.getByText('1 Termin(e) fallen aus')).toHaveCount(0);
    expect(errors).toEqual([]);
});

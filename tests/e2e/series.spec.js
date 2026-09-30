/**
 * Trainingsserie + Hallenplan: eine Belegung je Bahn fuer die ganze Serie.
 *
 * Regression 30.09.2026: "Serie bearbeiten" legte je Einheit eine eigene
 * wochentliche Belegung an; im Hallenplan lagen sie unsichtbar uebereinander.
 */
import { test, expect } from './fixtures.js';
import { authFile, ready, collectErrors } from './helpers.js';

test.use({ storageState: authFile('admin') });

test('Serie mit Bahn speichern ergibt genau eine Belegung im Hallenplan', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'schreibt Daten - einmal genügt');
    const errors = collectErrors(page);

    // Serie über die Einheitenliste öffnen (E2eSeeder: "Frühtraining", wöchentlich)
    await page.goto('/trainer/training');
    await ready(page);
    await page.locator('details').filter({ hasText: 'Frühtraining' }).first().locator('summary').click();
    await page.getByRole('link', { name: 'Serie bearbeiten' }).first().click();
    await page.waitForURL(/serie\/.+\/bearbeiten/);
    await ready(page);

    const bahn = page.getByRole('checkbox', { name: /Bahn 2/ });
    await bahn.check();
    for (let i = 0; i < 2; i++) {
        // Zweimal speichern: darf nichts verdoppeln
        await page.getByRole('button', { name: /Zukünftige Einheiten aktualisieren/ }).click();
        await page.waitForURL(/\/trainer\/training\/\d+$/);
        await expect(page.getByText(/kommende Einheiten der Serie aktualisiert/)).toBeVisible();
        await expect(page.getByText('Gilt für die ganze Serie')).toBeVisible();
        if (i === 0) { await page.goBack(); await ready(page); }
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

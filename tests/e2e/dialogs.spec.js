/**
 * Dialoge: Tastatur, Fokus, Escape - und Speichern ohne Neuladen.
 */
import { test, expect } from './fixtures.js';
import { authFile, ready, collectErrors } from './helpers.js';

test.describe('Trainer', () => {
    test.use({ storageState: authFile('trainer') });

    test('Kalender: Termin öffnet Detail-Sheet per Tastatur', async ({ page }) => {
        const errors = collectErrors(page);
        await page.goto('/kalender?mode=year&view=week');
        await ready(page);
        const chip = page.locator('[x-show^="categories["]:visible').first();
        const label = await chip.getAttribute('aria-label');
        await chip.focus();
        await page.keyboard.press('Enter');
        const sheet = page.locator('[role="dialog"]').filter({ has: page.locator('[x-text="sheetDate"]') });
        await expect(sheet).toBeVisible();
        expect(await sheet.evaluate((d) => d.contains(document.activeElement)), 'Fokus im Sheet').toBe(true);
        await expect(sheet.getByRole('link', { name: /Öffnen|Zum/ }).first()).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
        expect(await page.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Fokus zurück').toBe(label);
        expect(errors).toEqual([]);
    });

    test('Hallenplan: Belegung per Tastatur bearbeiten, speichern ohne Neuladen', async ({ page }, testInfo) => {
        const errors = collectErrors(page);
        await page.goto('/trainer/hall');
        await ready(page);
        await page.evaluate(() => { window.__ohneReload = true; });
        const block = page.locator('[data-booking-id][aria-label^="Kurs Seepferdchen"]:visible, [data-booking-id][aria-label^="E2E geändert"]:visible').first();
        const id = await block.getAttribute('data-booking-id');
        await block.focus();
        await page.keyboard.press('Enter');
        const dlg = page.locator('div[role="dialog"][aria-labelledby="hall-dlg-title"]');
        await expect(dlg).toBeVisible();
        await expect(page.locator('#hall-label')).toBeFocused();

        // Fokus bleibt im Dialog
        for (let i = 0; i < 25; i++) await page.keyboard.press('Tab');
        expect(await dlg.evaluate((d) => d.contains(document.activeElement))).toBe(true);

        // Ungespeicherte Aenderung: Escape fragt nach
        const neu = `E2E geändert ${testInfo.project.name}`; // je Projekt anders, sonst keine Aenderung
        await page.fill('#hall-label', neu);
        await page.locator('#hall-label').press('Escape');
        const confirm = page.getByRole('alertdialog');
        await expect(confirm).toBeVisible();
        await confirm.getByRole('button', { name: 'Weiter bearbeiten' }).click();
        await expect(dlg).toBeVisible();

        await dlg.getByRole('button', { name: 'Speichern', exact: true }).click();
        await expect(dlg).toBeHidden();
        await expect(page.getByText('Belegung gespeichert')).toBeVisible();
        await expect(page.locator(`[data-booking-id="${id}"]`).first()).toHaveAttribute('aria-label', new RegExp('^' + neu));
        expect(await page.evaluate(() => window.__ohneReload), 'kein Neuladen').toBe(true);

        // Stand bleibt nach Neuladen erhalten (wirklich gespeichert)
        await page.reload({ waitUntil: 'domcontentloaded' });
        await ready(page);
        await expect(page.locator(`[data-booking-id="${id}"]`).first()).toHaveAttribute('aria-label', new RegExp('^' + neu));
        expect(errors).toEqual([]);
    });
});

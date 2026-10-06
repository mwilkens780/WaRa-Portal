/**
 * Termine mit Einladung (E2eSeeder): Elke sagt für Sina zum Trainingslager zu,
 * der Vorstand sieht seine Sitzung mit Agenda und Eingeladenen, ein Gast
 * antwortet über seinen persönlichen Link ohne Login.
 */
import { test, expect } from './fixtures.js';
import { authFile, ready, collectErrors, expectAccessible, layoutProblems } from './helpers.js';

const GAST = '/einladung/E2E-GAST-TOKEN-0123456789abcdef0123456789abcdef';

test.describe('Eltern', () => {
    test.use({ storageState: authFile('eltern') });

    test('sagt für das Kind zum Trainingslager zu', async ({ page }) => {
        const errors = collectErrors(page);
        await page.goto('/einladungen');
        await ready(page);
        const row = page.locator('tr', { hasText: 'E2E-Trainingslager' });
        await expect(row).toContainText('für Sina');
        await row.getByRole('link').click();
        await ready(page);

        await expect(page.getByRole('heading', { name: 'Rückmeldung für Sina' })).toBeVisible();
        await expectAccessible(page);
        await page.getByRole('button', { name: 'Zusagen' }).click();
        await ready(page);
        await expect(page.getByText('Rückmeldung gespeichert: Zugesagt.')).toBeVisible();
        expect(errors).toEqual([]);
    });
});

test.describe('Vorstand (Admin als Gast eingeladen)', () => {
    test.use({ storageState: authFile('admin') });

    test('Sitzung mit Agenda, Unterlagen und Eingeladenen', async ({ page }) => {
        const errors = collectErrors(page);
        await page.goto('/einladungen');
        await ready(page);
        await page.locator('tr', { hasText: 'E2E-Vorstandssitzung' }).getByRole('link').click();
        await ready(page);
        await expect(page.getByText('Bericht der Kasse')).toBeVisible();
        await expect(page.getByRole('link', { name: /Protokoll August/ })).toBeVisible();
        await expect(page.getByText('Gerd Gast')).toBeVisible();
        await expectAccessible(page);
        expect(errors).toEqual([]);
    });
});

test.describe('Gast ohne Konto', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('antwortet über den persönlichen Link', async ({ page }) => {
        // Gast-Seite ohne Portal-Skripte (layouts.legal): kein ready()
        await page.goto(GAST, { waitUntil: 'load' });
        await expect(page.getByRole('heading', { name: 'E2E-Vorstandssitzung' })).toBeVisible();
        expect(await layoutProblems(page)).toEqual([]);
        await expectAccessible(page);
        await page.getByLabel('Kommentar (optional)').fill('Ich bringe die Belege mit');
        await page.getByRole('button', { name: 'Zusagen' }).click();
        await page.waitForLoadState('load');
        await expect(page.getByText('Danke, deine Rückmeldung ist gespeichert: Zugesagt.')).toBeVisible();
    });
});

test.describe('Kampfrichter', () => {
    test.use({ storageState: authFile('kampfrichter') });

    test('gibt Verfügbarkeit und Wunschposition an', async ({ page }) => {
        const errors = collectErrors(page);
        await page.goto('/einladungen');
        await ready(page);
        await page.locator('tr', { hasText: 'E2E-Sprintpokal' }).getByRole('link').click();
        await ready(page);
        await expectAccessible(page);
        await page.getByLabel('Starter*in (STA)').check();
        await page.getByRole('button', { name: 'Rückmeldung speichern' }).click();
        await ready(page);
        await expect(page.getByText('Danke, deine Rückmeldung ist gespeichert.')).toBeVisible();
        await expect(page.getByLabel('Starter*in (STA)')).toBeChecked();
        // Frühere Antwort bleibt vorausgewählt (Befund: Neu-Speichern löschte die Verfügbarkeit)
        await expect(page.getByLabel('Ich kann', { exact: true }).first()).toBeChecked();
        expect(errors).toEqual([]);
    });
});

test.describe('Kampfgericht-Übersicht (Admin)', () => {
    test.use({ storageState: authFile('admin') });

    test('zeigt Rückmeldungen je Tag und Position', async ({ page }) => {
        const errors = collectErrors(page);
        await page.goto('/admin/wettkaempfe');
        await ready(page);
        await page.getByRole('link', { name: 'E2E-Sprintpokal' }).first().click();
        await ready(page);
        await page.getByRole('button', { name: /^Kampfgericht/ }).click();
        await expect(page.getByText('Kai Kampfrichter').first()).toBeVisible();
        await expect(page.getByText(/Zeitnehmer\*in \(1\)/)).toBeVisible();
        await expectAccessible(page);
        expect(errors).toEqual([]);
    });
});

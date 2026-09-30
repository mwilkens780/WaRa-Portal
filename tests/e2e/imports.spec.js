/**
 * Import-Assistent: Datei pruefen, Vorschau, Zusammenfuehren mit einer
 * vorhandenen Veranstaltung (E2eSeeder: "E2E-Sprintpokal" in 10 Tagen).
 */
import { test, expect } from './fixtures.js';
import { authFile, ready, collectErrors, expectAccessible } from './helpers.js';

const inTagen = (n) => {
    const d = new Date();
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
};

/** Minimale Lenex-Ergebnisdatei: ein Wettkampf, ein Vereinsschwimmer */
const lenex = (datum) => `<?xml version="1.0" encoding="UTF-8"?>
<LENEX version="3.0"><MEETS>
  <MEET name="Sprintpokal Kiel" city="Kiel" course="SCM" startdate="${datum}" enddate="${datum}">
    <SESSIONS><SESSION number="1" date="${datum}"><EVENTS>
      <EVENT eventid="1" number="1" gender="M"><SWIMSTYLE distance="50" relaycount="1" stroke="FREE"/></EVENT>
    </EVENTS></SESSION></SESSIONS>
    <CLUBS><CLUB name="SG Wasserratten Norderstedt" shortname="SGWN"><ATHLETES>
      <ATHLETE firstname="Ben" lastname="Bahn" birthdate="2011-01-01" gender="M">
        <RESULTS><RESULT eventid="1" swimtime="00:00:27.31" place="1"/></RESULTS>
      </ATHLETE>
    </ATHLETES></CLUB></CLUBS>
  </MEET>
</MEETS></LENEX>`;

test.describe('Admin', () => {
    test.use({ storageState: authFile('admin') });

    test('Datei-Feld prüft Typ und Größe vor dem Hochladen', async ({ page }) => {
        await page.goto('/trainer/dsv-import');
        await ready(page);
        const submit = page.locator('form:has(input[name=dsv_file]) button[type=submit]');
        await expect(submit).toBeDisabled();
        await page.locator('input[name=dsv_file]').setInputFiles({ name: 'bild.png', mimeType: 'image/png', buffer: Buffer.from('x') });
        await expect(page.locator('#file-dsv-file-error')).toContainText('Dieser Dateityp passt nicht');
        await expect(submit).toBeDisabled();
        await page.locator('input[name=dsv_file]').setInputFiles({ name: 'riesig.lef', mimeType: 'text/xml', buffer: Buffer.alloc(21 * 1024 * 1024) });
        await expect(page.locator('#file-dsv-file-error')).toContainText('höchstens 20 MB');
        await expect(submit).toBeDisabled();
    });

    test('DSV-Import führt mit vorhandener Veranstaltung zusammen', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', 'schreibt Ergebnisse - einmal genügt');
        const errors = collectErrors(page);
        const datei = { name: 'sprintpokal.lef', mimeType: 'text/xml', buffer: Buffer.from(lenex(inTagen(10))) };

        const einlesen = async () => {
            await page.goto('/trainer/dsv-import');
            await ready(page);
            await page.locator('input[name=dsv_file]').setInputFiles(datei);
            await page.locator('form:has(input[name=dsv_file]) button[type=submit]').click();
            await page.waitForURL(/dsv-import\/preview/);
            await ready(page);
        };

        await einlesen();
        await expect(page.locator('[aria-current="step"]')).toContainText('Prüfen');
        await expect(page.getByRole('heading', { name: 'Wohin importieren?' })).toBeVisible();
        await expect(page.getByLabel(/Zusammenführen mit „E2E-Sprintpokal“/)).toBeChecked();
        await expect(page.locator('#dsv-name-0')).toBeHidden();
        await expectAccessible(page);

        const uebernehmen = page.locator('form [x-data] button[type=submit]').last();
        await expect(uebernehmen).toHaveText('1 zugeordneten Schwimmer übernehmen');
        await uebernehmen.click();
        await page.waitForURL(/dsv-import$/);
        await expect(page.getByText('Mit vorhandenem Wettkampf zusammengeführt')).toBeVisible();
        await expect(page.getByText(/1 Ergebnis neu/)).toBeVisible();

        // Gleiche Datei noch einmal: nichts doppelt
        await einlesen();
        await page.locator('form [x-data] button[type=submit]').last().click();
        await page.waitForURL(/dsv-import$/);
        await expect(page.getByText(/0 Ergebnisse neu/)).toBeVisible();
        await expect(page.getByText(/1 schon vorhanden/)).toBeVisible();
        expect(errors).toEqual([]);
    });
});

test.describe('Eltern', () => {
    test.use({ storageState: authFile('eltern') });

    test('Kalender: Training des Kindes ist verlinkt', async ({ page }) => {
        await page.goto('/kalender?mode=year&view=week');
        await ready(page);
        // Seeder: kommende Einheiten heissen "Frühtraining" (in 2 und 7 Tagen); vergangene
        // haben bewusst keinen Link, weil die Trainingsliste der Eltern nur Kommendes zeigt
        const kommend = () => page.locator('[x-show^="categories["][aria-label^="Frühtraining"]:visible').first();
        let chip = kommend();
        if (!(await chip.count())) {
            await page.locator('a[href*="view=week"][href*="week="]').last().click();
            await ready(page);
            chip = kommend();
        }
        await chip.click();
        const link = page.getByRole('link', { name: 'Zum Training von Sina' });
        await expect(link).toBeVisible();
        await link.click();
        await expect(page).toHaveURL(/\/eltern\/kind\/\d+\/training#training-\d+/);
    });
});

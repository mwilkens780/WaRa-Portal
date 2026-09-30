/**
 * Einmal je Rolle ueber das echte Anmeldeformular einloggen und die Sitzung
 * speichern. Die Tests starten dann bereits angemeldet.
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import { ACCOUNTS, PASSWORD, authFile, assertTestTarget } from './helpers.js';

export default async function globalSetup(config) {
    const { baseURL, launchOptions } = config.projects[0].use;
    assertTestTarget(baseURL);
    fs.mkdirSync('tests/e2e/.auth', { recursive: true });

    const browser = await chromium.launch(launchOptions);
    for (const [role, email] of Object.entries(ACCOUNTS)) {
        const page = await browser.newPage({ baseURL });
        await page.goto('/login');
        await page.fill('#email', email);
        await page.fill('#password', PASSWORD);
        await Promise.all([
            // "commit" reicht: Die Sitzung steht, sobald die Weiterleitung da ist
            page.waitForURL((u) => !u.pathname.startsWith('/login'), { waitUntil: 'commit' }),
            page.click('form button[type=submit]'),
        ]);
        if (page.url().includes('passwort')) throw new Error(`${role}: Konto verlangt Passwortwechsel - E2eSeeder prüfen`);
        await page.context().storageState({ path: authFile(role) });
        await page.close();
    }
    await browser.close();
}

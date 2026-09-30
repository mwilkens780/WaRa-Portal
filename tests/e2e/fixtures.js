/**
 * Gemeinsames test-Objekt fuer alle Specs.
 *
 * E2E_LOCAL_ASSETS=1 (nur lokal unter Windows): statische Dateien aus public/
 * (Build, Bilder, Favicon) direkt von der Platte ausliefern. Der eingebaute
 * PHP-Server bricht dort unter Last Verbindungen ab; dann wartet die Seite
 * endlos auf "load" und Tests scheitern ohne echten Fehler.
 * In CI (Linux) nicht noetig.
 */
import { test as base, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const TYPES = {
    '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp',
    '.svg': 'image/svg+xml', '.ico': 'image/x-icon', '.json': 'application/json', '.webmanifest': 'application/manifest+json',
};

export const test = base.extend({
    context: async ({ context, baseURL }, use) => {
        if (process.env.E2E_LOCAL_ASSETS === '1') {
            const origin = new URL(baseURL).origin;
            await context.route((url) => url.origin === origin && TYPES[path.extname(url.pathname).toLowerCase()] !== undefined, async (route) => {
                const file = path.join('public', decodeURIComponent(new URL(route.request().url()).pathname));
                if (!fs.existsSync(file) || !fs.statSync(file).isFile()) return route.continue();
                await route.fulfill({ body: fs.readFileSync(file), contentType: TYPES[path.extname(file).toLowerCase()] });
            });
        }
        await use(context);
    },
    // Navigation wartet auf das DOM, nicht auf "load": Bereit ist eine Seite, wenn
    // Alpine laeuft (ready() in helpers.js). "load" blieb lokal gelegentlich ganz
    // aus, obwohl keine Anfrage mehr offen war.
    page: async ({ page }, use) => {
        const goto = page.goto.bind(page);
        const reload = page.reload.bind(page);
        page.goto = (url, opts = {}) => goto(url, { waitUntil: 'domcontentloaded', ...opts });
        page.reload = (opts = {}) => reload({ waitUntil: 'domcontentloaded', ...opts });
        await use(page);
    },
});

export { expect };

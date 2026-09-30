/**
 * Gemeinsame Helfer der Browsertests.
 */
import AxeBuilder from '@axe-core/playwright';
import { expect } from '@playwright/test';

export const PASSWORD = 'E2e-Test-2026'; // database/seeders/E2eSeeder.php
export const ACCOUNTS = {
    admin: 'admin@e2e.test',
    trainer: 'trainer@e2e.test',
    schwimmer: 'schwimmer@e2e.test',
    eltern: 'eltern@e2e.test',
};
export const authFile = (role) => `tests/e2e/.auth/${role}.json`;

/**
 * Schutz: Die Tests schreiben in die Datenbank. Nur gegen einen lokalen
 * Server mit ausdruecklicher Freigabe (E2E_ALLOW_WRITES=1, gesetzt in CI).
 */
export function assertTestTarget(baseURL) {
    const u = new URL(baseURL);
    if (!['127.0.0.1', 'localhost'].includes(u.hostname) || process.env.E2E_ALLOW_WRITES !== '1') {
        throw new Error(`E2E-Tests nur gegen eine lokale Test-Datenbank mit E2E_ALLOW_WRITES=1 (Ziel: ${baseURL})`);
    }
}

/** Seite ist fertig: Alpine gestartet, UI-Bausteine bereit */
export async function ready(page) {
    await page.waitForFunction(() => window.__uiConfirmReady === true, null, { timeout: 20_000 });
}

/** JS-Fehler der Seite sammeln (am Testende auf leer pruefen) */
export function collectErrors(page) {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    return errors;
}

/**
 * axe: kritische und ernste Verstoesse gegen WCAG 2.1 AA muessen 0 sein.
 * Fehlermeldung je Regel: "regel (n Knoten): Beispiel".
 */
export async function expectAccessible(page, { exclude = [], soft = false } = {}) {
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']);
    for (const sel of exclude) builder = builder.exclude(sel);
    const { violations } = await builder.analyze();
    const relevant = violations
        .filter((v) => ['serious', 'critical'].includes(v.impact))
        .map((v) => `${v.id} (${v.nodes.length}): ${v.nodes[0]?.html.slice(0, 120)}`);
    (soft ? expect.soft : expect)(relevant, `axe: kritische/ernste Verstöße auf ${new URL(page.url()).pathname}`).toEqual([]);
}

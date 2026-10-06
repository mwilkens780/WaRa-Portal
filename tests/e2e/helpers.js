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
    kampfrichter: 'kampfrichter@e2e.test',
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
 * Layout auf schmalen Bildschirmen (Befund 01.10.2026, iPhone):
 *  - nichts scrollt waagerecht, das nicht dafuer gedacht ist. Nicht nur das
 *    Dokument pruefen: Das Portal scrollt in einem Layout-Container - dort
 *    lief der Inhalt nach rechts, waehrend die Seite selbst 390 px breit blieb;
 *  - nichts ragt aus seiner Karte;
 *  - Eingabefelder gehen nicht ueber den Rand und ueberlappen sich nicht.
 * Gewollte Scrollbereiche (Tabellen, Hallenplan, Reiterleisten) sind ausgenommen:
 * Klasse overflow-(x-)auto / overflow-(x-)scroll oder overflow im style-Attribut.
 */
export async function layoutProblems(page) {
    return page.evaluate(() => {
        const out = [];
        const vw = document.documentElement.clientWidth;
        const intended = (e) => /\boverflow-(x-)?(auto|scroll|hidden)\b/.test(e.className?.baseVal ?? e.className ?? '') || /overflow/.test(e.getAttribute('style') ?? '');
        const clipped = (e, stop) => {
            for (let p = e.parentElement; p && p !== stop; p = p.parentElement) {
                if (['auto', 'scroll', 'hidden', 'clip'].includes(getComputedStyle(p).overflowX) && p !== document.body) return true;
            }
            return false;
        };
        const visible = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden' && !e.closest('.sr-only, thead'); };
        const name = (e) => `${e.tagName.toLowerCase()}${e.type ? '[' + e.type + ']' : ''}${e.className && typeof e.className === 'string' ? '.' + e.className.split(/\s+/).slice(0, 3).join('.') : ''} „${(e.textContent || e.name || '').trim().slice(0, 30)}“`;

        if (document.documentElement.scrollWidth > vw + 1) out.push(`Seite ${document.documentElement.scrollWidth}px breit statt ${vw}px`);
        for (const e of document.querySelectorAll('body *')) {
            const ox = getComputedStyle(e).overflowX;
            if (['auto', 'scroll'].includes(ox) && !intended(e) && e.scrollWidth > e.clientWidth + 1) {
                out.push(`scrollt waagerecht: ${name(e).slice(0, 60)} (${e.scrollWidth}px statt ${e.clientWidth}px)`);
            }
        }
        for (const card of document.querySelectorAll('main .rounded-xl')) {
            // Gewollt scrollende Flaechen (z. B. Umschalter mit overflow-x-auto) - Inhalt ragt nicht, er scrollt.
            // overflow-hidden dagegen schneidet ab: das bleibt ein Befund.
            if (['auto', 'scroll'].includes(getComputedStyle(card).overflowX)) continue;
            const cr = card.getBoundingClientRect();
            for (const e of card.querySelectorAll('a, button, input, select, textarea, p, span, td, th, h2, h3')) {
                if (!visible(e)) continue;
                if (e.getBoundingClientRect().right > cr.right + 2 && !clipped(e, card)) { out.push(`ragt aus der Karte: ${name(e)}`); break; }
            }
        }
        const fields = [...document.querySelectorAll('main input:not([type=hidden]):not([type=checkbox]):not([type=radio]), main select, main textarea')]
            .filter((e) => visible(e) && !clipped(e, document.body));
        for (const a of fields) {
            const ra = a.getBoundingClientRect();
            if (ra.right > vw + 1) out.push(`Feld über den Rand: ${name(a)}`);
            for (const b of fields) {
                const rb = b.getBoundingClientRect();
                if (a !== b && ra.left < rb.left && ra.right > rb.left + 2 && ra.top < rb.bottom - 2 && rb.top < ra.bottom - 2) out.push(`Felder überlappen: ${name(a)} / ${name(b)}`);
            }
        }
        return [...new Set(out)].slice(0, 8);
    });
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

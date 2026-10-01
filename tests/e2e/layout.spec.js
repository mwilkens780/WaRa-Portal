/**
 * Detail- und Formularseiten, die nicht im Menue stehen: Layout auf dem Handy
 * (und am Desktop). Befund 01.10.2026 auf dem iPhone: Datumsfelder ragten aus
 * "Benutzer bearbeiten", in der Einheit ueberlappten Datum/Uhrzeit/Ort, lange
 * Wettkampfnamen machten das Dashboard breiter als den Bildschirm.
 *
 * Die Ziele werden ueber Links auf Listenseiten gefunden - so bleiben die Tests
 * unabhaengig von IDs.
 */
import { test, expect } from './fixtures.js';
import { authFile, ready, collectErrors, layoutProblems } from './helpers.js';

const PAGES = {
    admin: [
        ['/admin/benutzer', /\/admin\/benutzer\/\d+\/bearbeiten$/],
        ['/admin/wettkaempfe', /\/admin\/wettkaempfe\/\d+$/],
        ['/trainer/training', /\/trainer\/training\/serie\/[0-9a-f-]+$/],
        ['/trainer/training', /\/trainer\/training\/\d+$/],
        '/trainer/training/neu',
        '/admin/wettkaempfe/neu',
        '/profil',
    ],
    schwimmer: [
        ['/schwimmer/dashboard', /\/schwimmer\/training\/\d+$/],
    ],
};

for (const [role, targets] of Object.entries(PAGES)) {
    test.describe(`Detailseiten ${role}`, () => {
        test.use({ storageState: authFile(role) });

        test('Layout ohne Überlauf und Überlappung', async ({ page, baseURL }) => {
            test.setTimeout(5 * 60_000);
            for (const t of targets) {
                let url = t;
                if (Array.isArray(t)) {
                    await page.goto(t[0]);
                    await ready(page);
                    const hrefs = await page.$$eval('main a[href]', (as) => as.map((a) => a.href));
                    url = hrefs.map((h) => new URL(h).pathname).find((p) => t[1].test(p));
                    expect.soft(url, `Link ${t[1]} auf ${t[0]}`).toBeTruthy();
                    if (!url) continue;
                }
                await test.step(url, async () => {
                    const errors = collectErrors(page);
                    const res = await page.goto(url);
                    expect.soft(res.status(), `HTTP-Status ${url}`).toBeLessThan(400);
                    await ready(page);
                    expect.soft(await layoutProblems(page), `Layout ${url}`).toEqual([]);
                    expect.soft(errors, `JS-Fehler ${url}`).toEqual([]);
                });
            }
        });
    });
}

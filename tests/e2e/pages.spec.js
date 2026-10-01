/**
 * Jede Seite im Menue jeder Rolle: laedt ohne Fehler, ohne JS-Fehler,
 * ohne waagerechtes Ueberlaufen und ohne kritische/ernste axe-Verstoesse.
 *
 * Die Seiten werden aus der Seitenleiste gesammelt - neue Menuepunkte sind
 * damit automatisch abgedeckt.
 */
import { test, expect } from './fixtures.js';
import { authFile, ready, collectErrors, expectAccessible, layoutProblems } from './helpers.js';

const ROLES = ['admin', 'trainer', 'schwimmer', 'eltern'];

// Seiten ohne Layout bzw. reine Datenausgaben
const SKIP = [/\/logout/, /\.(pdf|csv|ics)(\?|$)/, /download/, /export/];

for (const role of ROLES) {
    test.describe(`Menü ${role}`, () => {
        test.use({ storageState: authFile(role) });

        test('alle Menüseiten', async ({ page, baseURL }) => {
            test.setTimeout(15 * 60_000); // alle Menüseiten der Rolle in einem Test
            await page.goto('/');
            await ready(page);
            const links = await page.$$eval('nav[aria-label="Hauptnavigation"] a[href], #nav-account a[href], nav[aria-label="Schnellnavigation"] a[href]',
                (as) => [...new Set(as.map((a) => a.href))]);
            const urls = links.filter((u) => u.startsWith(baseURL) && !SKIP.some((re) => re.test(u)));
            expect(urls.length, 'Menü hat Einträge').toBeGreaterThan(2);

            for (const url of urls) {
                await test.step(url.replace(baseURL, ''), async () => {
                    // soft: alle Seiten pruefen, alle Befunde auf einmal melden
                    const errors = collectErrors(page);
                    const res = await page.goto(url);
                    expect.soft(res.status(), `HTTP-Status ${url}`).toBeLessThan(400);
                    await ready(page);
                    // waagerechtes Scrollen (auch im Layout-Container), Ueberstehendes, Felder
                    expect.soft(await layoutProblems(page), `Layout ${url}`).toEqual([]);
                    await expectAccessible(page, { soft: true });
                    expect.soft(errors, `JS-Fehler ${url}`).toEqual([]);
                });
            }
        });
    });
}

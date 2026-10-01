/**
 * Browsertests (Playwright + axe) - docs/frontend-audit.md, Phase 6.
 *
 * Laufen gegen eine eigene Test-Datenbank mit database/seeders/E2eSeeder,
 * NIE gegen echte Daten: Die Tests speichern, verschieben und importieren.
 *
 *   php artisan migrate:fresh --seed --seeder=E2eSeeder   (Test-DB!)
 *   php artisan serve --port=8766
 *   E2E_BASE_URL=http://127.0.0.1:8766 E2E_ALLOW_WRITES=1 npx playwright test
 *
 * In CI: .github/workflows/e2e.yml
 * Lokal ohne heruntergeladenen Browser: PW_CHROMIUM=<Pfad zu chrome.exe>
 */
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8766';
const launchOptions = process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {};

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.js',
    // Laravels Entwicklungsserver arbeitet Anfragen nacheinander ab
    workers: 1,
    fullyParallel: false,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    // Eine Wiederholung: lokal (Windows) bleibt eine Antwort gelegentlich haengen
    retries: process.env.CI || process.env.E2E_LOCAL_ASSETS === '1' ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
    use: {
        baseURL,
        locale: 'de-DE',
        timezoneId: 'Europe/Berlin',
        // Keine Uebergaenge: axe misst sonst Farben mitten in einer Ueberblendung
        reducedMotion: 'reduce',
        // Haengt eine Seite, soll der Fehler sie nennen - nicht erst das Testlimit greifen
        navigationTimeout: 30_000,
        actionTimeout: 15_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions,
    },
    projects: [
        { name: 'desktop', use: { viewport: { width: 1280, height: 800 } } },
        { name: 'mobil', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
        // Safari-Technik (WebKit) wie auf dem iPhone: Datumsfelder und Mindestbreiten
        // rechnet WebKit anders als Chromium (Befund 01.10.2026). Nur Seiten- und
        // Layoutpruefung. In CI immer, lokal mit E2E_WEBKIT=1 (unter Windows startet WebKit oft nicht).
        ...(process.env.CI || process.env.E2E_WEBKIT ? [{
            name: 'iphone',
            testMatch: /(pages|layout)\.spec\.js/,
            use: { ...devices['iPhone 13'], browserName: 'webkit', launchOptions: {} },
        }] : []),
    ],
});

'use strict';

/**
 * WebClub.app Playwright-Crawler
 *
 * Aufruf: node scripts/webclub-crawler.js /pfad/zur/config.json
 *
 * Config-Felder:
 *   base_url          – z.B. "https://meinverein.web-club.app"
 *   username          – Login-E-Mail
 *   password          – Login-Passwort
 *   lookback_days     – Wie viele Tage zurück nach Veranstaltungen suchen (Standard: 90)
 *   lookahead_days    – Wie viele Tage voraus suchen (Standard: 365)
 *   scrape_competitions – true/false
 *   scrape_persons    – true/false
 *   headless          – true = kein Browser-Fenster (Standard: true)
 *   timeout_ms        – Selektor-Timeout in ms (Standard: 15000)
 *   screenshot_on_error – Pfad-Prefix für Debug-Screenshots (optional)
 *
 * Ausgabe: JSON-Objekt auf stdout, Log-Meldungen auf stderr.
 * Exit-Code 0 = OK, 1 = fataler Fehler (Login fehlgeschlagen, etc.)
 */

const fs   = require('fs');
const path = require('path');

// Playwright aus globalem Node-Pfad laden (npm install -g playwright)
let playwright;
try {
    playwright = require('playwright');
} catch (e) {
    try {
        playwright = require('/opt/node22/lib/node_modules/playwright');
    } catch (e2) {
        die('Playwright nicht gefunden. Bitte "npm install -g playwright" ausführen.\n' + e2.message);
    }
}
const { chromium } = playwright;

// ── Config laden ─────────────────────────────────────────────────────────────

const configPath = process.argv[2];
if (!configPath) die('Kein Config-Pfad als Argument übergeben.');

let cfg;
try {
    cfg = JSON.parse(fs.readFileSync(configPath, 'utf8'));
} catch (e) {
    die('Config-Datei konnte nicht gelesen werden: ' + e.message);
}

const BASE_URL       = (cfg.base_url || '').replace(/\/$/, '');
const USERNAME       = cfg.username || '';
const PASSWORD       = cfg.password || '';
const LOOKBACK_DAYS  = parseInt(cfg.lookback_days  ?? 90,  10);
const LOOKAHEAD_DAYS = parseInt(cfg.lookahead_days ?? 365, 10);
const HEADLESS       = cfg.headless !== false;
const TIMEOUT_MS     = parseInt(cfg.timeout_ms ?? 15000, 10);
const DO_COMPETITIONS = cfg.scrape_competitions !== false;
const DO_PERSONS      = cfg.scrape_persons      !== false;
const SCREENSHOT_PREFIX = cfg.screenshot_on_error || null;

if (!BASE_URL) die('base_url ist nicht konfiguriert.');
if (!USERNAME || !PASSWORD) die('username und password müssen konfiguriert sein.');

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────

function log(msg) {
    process.stderr.write('[webclub-crawler] ' + msg + '\n');
}

function die(msg) {
    process.stderr.write('[webclub-crawler] FATAL: ' + msg + '\n');
    process.exit(1);
}

function isoDate(d) {
    if (!d) return null;
    // "dd.mm.yyyy" → "yyyy-mm-dd"
    const m = d.trim().match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
    if (m) return `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
    // Bereits ISO
    if (/^\d{4}-\d{2}-\d{2}$/.test(d.trim())) return d.trim();
    return null;
}

function parseDateRange(str) {
    if (!str) return { date: null, date_end: null };
    str = str.trim();
    // "dd.mm.yyyy - dd.mm.yyyy" oder "dd.mm.yyyy–dd.mm.yyyy"
    const range = str.match(/(\d{1,2}\.\d{1,2}\.\d{4})\s*[-–]\s*(\d{1,2}\.\d{1,2}\.\d{4})/);
    if (range) return { date: isoDate(range[1]), date_end: isoDate(range[2]) };
    const single = str.match(/(\d{1,2}\.\d{1,2}\.\d{4})/);
    if (single) return { date: isoDate(single[1]), date_end: null };
    return { date: null, date_end: null };
}

async function screenshot(page, label) {
    if (!SCREENSHOT_PREFIX) return;
    try {
        const file = SCREENSHOT_PREFIX + '_' + label + '_' + Date.now() + '.png';
        await page.screenshot({ path: file, fullPage: true });
        log('Screenshot gespeichert: ' + file);
    } catch (_) {}
}

async function safeText(locator) {
    try { return (await locator.first().textContent({ timeout: 3000 }))?.trim() || null; } catch (_) { return null; }
}

async function safeAttr(locator, attr) {
    try { return (await locator.first().getAttribute(attr, { timeout: 3000 }))?.trim() || null; } catch (_) { return null; }
}

// ── Login ────────────────────────────────────────────────────────────────────

async function login(page) {
    log('Öffne Startseite: ' + BASE_URL);
    await page.goto(BASE_URL, { waitUntil: 'load' });
    await page.waitForTimeout(2000);

    // Login-Trigger-Button suchen und klicken (öffnet das Login-Modal/Popup)
    const loginTrigger = page.locator([
        'button:has-text("Anmelden")',
        'button:has-text("Login")',
        'button:has-text("Einloggen")',
        'button:has-text("Sign in")',
        'a:has-text("Anmelden")',
        'a:has-text("Login")',
        'a:has-text("Einloggen")',
        '[data-action*="login"]',
        '[class*="login"]',
        '[href*="login"]',
    ].join(', ')).first();

    const triggerVisible = await loginTrigger.isVisible().catch(() => false);
    if (triggerVisible) {
        log('Login-Button gefunden – klicke zum Öffnen des Modals');
        await loginTrigger.click();
        await page.waitForTimeout(1000);
    } else {
        log('Kein expliziter Login-Button gefunden – Passwortfeld wird direkt erwartet');
    }

    // Auf Passwortfeld warten (im Modal oder auf der Seite)
    try {
        await page.locator('input[type="password"]').waitFor({ state: 'visible', timeout: TIMEOUT_MS });
    } catch (e) {
        await screenshot(page, 'login_modal_not_found');
        const bodyText = await page.locator('body').innerText().catch(() => '(nicht lesbar)');
        log('Seiteninhalt (erste 500 Zeichen): ' + bodyText.slice(0, 500));
        die('Login-Formular/Modal nicht gefunden. URL: ' + page.url());
    }

    // Felder befüllen
    const usernameField = page.locator([
        'input[name="username"]',
        'input[name="login"]',
        'input[name="email"]',
        'input[autocomplete="username"]',
        'input[autocomplete="email"]',
        'input[type="email"]',
        'input[type="text"]',
    ].join(', ')).first();

    const passwordField = page.locator('input[type="password"]').first();

    await usernameField.fill(USERNAME);
    await passwordField.fill(PASSWORD);

    // Submit
    const submitBtn = page.locator(
        'button[type="submit"], input[type="submit"], ' +
        'button:has-text("Anmelden"), button:has-text("Einloggen"), ' +
        'button:has-text("Login"), button:has-text("Sign in")'
    ).first();

    try {
        await submitBtn.click({ timeout: TIMEOUT_MS });
    } catch (_) {
        await page.keyboard.press('Enter');
    }

    await page.waitForLoadState('load');
    await page.waitForTimeout(2000);

    // Login prüfen: kein Passwortfeld mehr sichtbar?
    const stillLoginPage = await page.locator('input[type="password"]').isVisible().catch(() => false);
    if (stillLoginPage) {
        await screenshot(page, 'login_failed');
        die('Login fehlgeschlagen – Anmeldedaten prüfen. URL: ' + page.url());
    }

    log('Login erfolgreich. URL: ' + page.url());
}

// ── Hilfsfunktion: relative URL auflösen ─────────────────────────────────────

function resolveUrl(href) {
    if (!href) return null;
    try { return new URL(href, BASE_URL + '/').href; } catch (_) { return null; }
}

// ── Hilfsfunktion: Navigation über Dropdown-Menü ─────────────────────────────

async function navigateViaDropdownMenu(page, topLabelRegex, subLabelRegex) {
    try {
        // Oberstes Menü-Element finden und hovern
        const topItem = page.locator('a, span, li')
            .filter({ hasText: topLabelRegex }).first();
        if (await topItem.count() === 0) return false;

        await topItem.hover();
        await page.waitForTimeout(600);

        // Untermenü-Eintrag klicken
        const subItem = page.locator('a').filter({ hasText: subLabelRegex }).first();
        if (await subItem.count() === 0) {
            await topItem.click();
            await page.waitForTimeout(600);
        }
        await page.locator('a').filter({ hasText: subLabelRegex }).first().click({ timeout: TIMEOUT_MS });
        await page.waitForLoadState('load');
        await page.waitForTimeout(800);
        log('Navigiert via Dropdown-Menü: ' + page.url());
        return true;
    } catch (_) {
        return false;
    }
}

// ── AJAX-Wartefunktion ────────────────────────────────────────────────────────
// WebClub hat mehrere Spinner auf der Seite. Der Tabellen-Spinner steckt in
// einem <td colspan="N"> – das ist der eindeutige Marker für den Datentabellen-AJAX.
// Wir warten bis KEIN solcher Spinner mehr sichtbar ist.

async function waitForAjaxContent(page, timeoutMs = 20000) {
    const disappeared = await page.waitForFunction(() => {
        const cells = document.querySelectorAll('td[colspan]');
        for (const cell of cells) {
            if (parseInt(cell.getAttribute('colspan') || '0') >= 4 &&
                cell.querySelector('img[src*="spinner"]')) {
                return false;
            }
        }
        return true;
    }, { timeout: timeoutMs }).then(() => true).catch(() => false);

    if (!disappeared) {
        log('WARNUNG: Tabellen-AJAX nach ' + timeoutMs + ' ms noch nicht fertig.');
    }
    await page.waitForTimeout(300);
}

// Parst die WebClub-JSON-Veranstaltungsliste: {"list":[{"id":"...","d":"...","n":"...","o":"..."}]}
function parseCompetitionListJson(body, dateFrom, dateTo) {
    try {
        const data = JSON.parse(body);
        if (!data || data.error || !Array.isArray(data.list)) return null;

        const links = [];
        for (const item of data.list) {
            if (!item.id || !item.d) continue;
            const { date, date_end } = parseDateRange(item.d);
            if (!date) continue;
            const d = new Date(date);
            if (d < dateFrom || d > dateTo) continue;
            links.push({
                url:          BASE_URL + '/ver.php?id=' + item.id,
                name:         item.n  || null,
                date,
                date_end:     date_end || null,
                location:     item.o  || null,
                meldeschluss: item.md ? isoDate(item.md) : null,
            });
        }
        return links;
    } catch (e) {
        log('JSON-Parse-Fehler: ' + e.message);
        return null;
    }
}

// Parst Veranstaltungs-Links direkt aus erfasstem HTML (XHR-Response-Body)
async function parseEventLinksFromHtml(page, html, dateFrom, dateTo) {
    const fromMs  = dateFrom.getTime();
    const toMs    = dateTo.getTime();
    const baseUrl = BASE_URL;

    return await page.evaluate(({ html, fromMs, toMs, baseUrl }) => {
        const div = document.createElement('div');
        div.innerHTML = html;

        const links = [];
        const seen  = new Set();

        for (const row of div.querySelectorAll('tr')) {
            const tds = Array.from(row.querySelectorAll('td'));
            if (tds.length < 2) continue;
            if (row.querySelector('img[src*="spinner"]')) continue;

            // Datum suchen
            let dateStr = null, dateMs = null;
            for (const td of tds) {
                const m = td.textContent.match(/(\d{1,2})\.(\d{1,2})\.(\d{4})/);
                if (m) {
                    dateStr = `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
                    dateMs  = new Date(dateStr).getTime();
                    break;
                }
            }
            if (!dateStr) continue; // Nur Zeilen mit Datum sind Competition-Zeilen
            if (dateMs < fromMs || dateMs > toMs) continue;

            // URL aus <a href>
            let url = null, name = null;
            for (const a of row.querySelectorAll('a[href]')) {
                const href = a.getAttribute('href');
                if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('mailto:')) continue;
                try { url = new URL(href, baseUrl + '/').href; } catch (_) {}
                if (url) { name = a.textContent.trim() || null; break; }
            }

            // URL aus onclick auf <tr>
            if (!url) {
                const onclick = row.getAttribute('onclick') || '';
                const m = onclick.match(/['"]([^'"]+)['"]/);
                if (m) try { url = new URL(m[1], baseUrl + '/').href; } catch (_) {}
            }

            if (!url || seen.has(url)) continue;
            seen.add(url);

            if (!name) {
                for (const td of tds) {
                    const t = td.textContent.trim();
                    if (t.length > 5 && !/^\d{1,2}\.\d{1,2}/.test(t)) { name = t; break; }
                }
            }
            links.push({ url, name, date: dateStr, date_end: null });
        }
        return links;
    }, { html, fromMs, toMs, baseUrl });
}

// ── Veranstaltungen (Competitions) ───────────────────────────────────────────

async function scrapeCompetitions(page) {
    log('Navigiere zu Veranstaltungen…');

    const competitions = [];
    const errors       = [];

    // XHR-Responses abfangen und vollständig loggen
    let capturedCompHtml = null;
    const captureXhr = async (res) => {
        try {
            const rt = res.request().resourceType();
            if (!['xhr', 'fetch'].includes(rt)) return;
            const status = res.status();
            log(`XHR < ${status} ${res.url().replace(BASE_URL, '***')}`);
            if (status < 200 || status >= 300) return;
            const body = await res.text();
            // WebClub-spezifisches JSON-Format: {"list":[{"id":"...","d":"...","n":"..."}]}
            const isWebClubList = body.includes('"list"') && body.includes('"id"') && body.includes('"d"') && body.includes('"n"');
            // Alternativ: HTML mit Datumszeilen
            const hasHtmlRows = body.includes('<tr') && /\d{1,2}\.\d{1,2}\.\d{4}/.test(body);
            if (isWebClubList || hasHtmlRows) {
                log(`XHR: Veranstaltungsdaten erfasst (${body.length} bytes, ${isWebClubList ? 'JSON' : 'HTML'})`);
                capturedCompHtml = body;
            }
        } catch (_) {}
    };
    page.on('response', captureXhr);

    // 1. Direkte URLs probieren – klassische PHP-Apps nutzen .php-Dateinamen
    const candidates = [
        BASE_URL + '/verc.php',          // WebClub klassisch (aus Screenshot bekannt)
        BASE_URL + '/veranstaltungen',
        BASE_URL + '/events',
        BASE_URL + '/competition',
        BASE_URL + '/competitions',
        BASE_URL + '/wettkampf',
        BASE_URL + '/vc.php',
        BASE_URL + '/meet.php',
    ];

    let navigated = false;
    for (const url of candidates) {
        try {
            await page.goto(url, { waitUntil: 'load', timeout: 15000 });

            // Seite geladen – globale JS-Funktionen introspektieren (einmalig bei verc.php)
            if (url.includes('verc.php')) {
                const jsFns = await page.evaluate(() =>
                    Object.keys(window).filter(k =>
                        typeof window[k] === 'function' &&
                        /load|init|list|verc|search|filter|ajax/i.test(k)
                    )
                );
                if (jsFns.length > 0) log('JS-Funktionen auf Seite: ' + jsFns.join(', '));

                // Falls es eine Suchen/Filtern-Schaltfläche gibt: klicken (manche WebClub-Instanzen)
                const searchBtn = page.locator(
                    'input[type="submit"], button[type="submit"], ' +
                    'button, input[type="button"]'
                ).filter({ hasText: /suchen|laden|anzeigen|filter|start|go/i }).first();
                if (await searchBtn.count() > 0) {
                    log('Suchen-Button gefunden – klicke zum Auslösen des AJAX');
                    await searchBtn.click({ timeout: 3000 }).catch(() => {});
                    await page.waitForTimeout(2000);
                }
            }

            await waitForAjaxContent(page, 20000);

            // "Echte" Competition-Zeilen = Datumsmuster + mind. 3 Spalten (kein Nav-Tabellen-Match)
            const realRows = await page.evaluate(() => {
                if (document.querySelector('td[colspan] img[src*="spinner"]')) return 0;
                return Array.from(document.querySelectorAll('table tr'))
                    .filter(tr => {
                        const tds = tr.querySelectorAll('td');
                        if (tds.length < 3) return false;
                        if (tr.querySelector('img[src*="spinner"]')) return false;
                        return /\d{1,2}\.\d{1,2}\.\d{4}/.test(tr.textContent);
                    }).length;
            });

            const currentUrl = page.url();
            const xhrNote = capturedCompHtml ? ' [XHR-Daten erfasst]' : '';
            log(`Kandidat ${url} → ${realRows} Competition-Zeilen im DOM, URL: ${currentUrl}${xhrNote}`);

            if (realRows > 0 || capturedCompHtml) {
                navigated = true;
                log('Veranstaltungsseite gefunden: ' + url);
                break;
            }
        } catch (e) {
            log(`Kandidat ${url} fehlgeschlagen: ${e.message}`);
        }
    }

    // 2. Fallback: Menü "Veranstaltungen" → "Veranstaltungen"
    if (!navigated) {
        log('Direkte URL-Kandidaten erfolglos – versuche Dropdown-Menü');
        navigated = await navigateViaDropdownMenu(page, /veranstaltung/i, /veranstaltung/i);
        if (navigated) {
            await waitForAjaxContent(page, 25000);
            const rowCount = await page.locator('table tr').count();
            const xhrNote = capturedCompHtml ? ' [XHR-Daten erfasst]' : '';
            log(`Nach Dropdown-Navigation: ${rowCount} table-rows, URL: ${page.url()}${xhrNote}`);
        }
    }

    page.off('response', captureXhr);

    // Screenshot der Listenseite – immer, für Debugging
    await screenshot(page, 'competitions_list');

    if (!navigated && !capturedCompHtml) {
        const msg = 'Veranstaltungsseite nicht gefunden (alle Kandidaten und Menü-Navigation fehlgeschlagen)';
        errors.push({ type: 'navigation', message: msg });
        log('WARNUNG: ' + msg);
        return { competitions, errors };
    }

    // Datumsbereich
    const today    = new Date();
    const dateFrom = new Date(today); dateFrom.setDate(today.getDate() - LOOKBACK_DAYS);
    const dateTo   = new Date(today); dateTo.setDate(today.getDate() + LOOKAHEAD_DAYS);

    const eventLinks = await collectEventLinks(page, dateFrom, dateTo, capturedCompHtml);
    log(`${eventLinks.length} Veranstaltungslinks gefunden.`);

    // ── Detail-Daten via verc_choose laden (bleibt auf verc.php) ─────────────
    // WebClub ist session-basiert: ver.php?id=X ignoriert den Parameter und zeigt
    // immer die zuletzt im Session-Kontext gewählte Veranstaltung. Daher rufen wir
    // verc_choose(id) auf der Listenseite auf, um die richtige AJAX-Antwort zu triggern.

    const detailMap = new Map(); // verID (string) → data-Objekt aus 1789B-Response

    const captureDetailXhr = async (res) => {
        try {
            if (!['xhr', 'fetch'].includes(res.request().resourceType())) return;
            if (res.status() < 200 || res.status() >= 300) return;
            const body = await res.text();
            if (!body.includes('"verID"') || !body.includes('"verNAME"')) return;
            let parsed;
            try { parsed = JSON.parse(body); } catch (_) { return; }
            if (parsed?.data?.verID) {
                const id = String(parsed.data.verID);
                if (!detailMap.has(id)) {
                    detailMap.set(id, parsed.data);
                    // Erste Begegnung vollständig loggen (für Feldname-Analyse)
                    log(`Detail erfasst id=${id} (${body.length}B): ${body.slice(0, 1800)}`);
                }
            }
        } catch (_) {}
    };
    page.on('response', captureDetailXhr);

    for (const link of eventLinks) {
        const id = extractIdFromUrl(link.url);
        if (!detailMap.has(id)) {
            log(`Lade Detail via verc_choose(${id})…`);
            await page.evaluate((numId) => {
                try {
                    if (typeof verc_choose === 'function') { verc_choose(numId); return; }
                    if (typeof verc_get   === 'function') { verc_get(numId);    return; }
                } catch (_) {}
            }, parseInt(id, 10)).catch(() => {});
            await page.waitForTimeout(3000);
        }
        if (detailMap.has(id)) {
            log(`Detail für id=${id} vorhanden`);
        } else {
            log(`Detail für id=${id} nicht erhalten`);
        }
    }

    page.off('response', captureDetailXhr);
    log(`Details: ${detailMap.size} von ${eventLinks.length} geladen`);

    // ── Competition-Objekte bauen ─────────────────────────────────────────────
    for (const link of eventLinks) {
        const id = extractIdFromUrl(link.url);
        const d  = detailMap.get(id);
        competitions.push({
            webclub_id:   id,
            webclub_url:  link.url,
            name:         d?.verNAME       || link.name,
            date:         isoDate(d?.verVON)          || link.date,
            date_end:     isoDate(d?.verBIS)          || link.date_end,
            location:     d?.verORT        || link.location     || null,
            organizer:    d?.verAUSRICHTER || null,
            meldeschluss: isoDate(d?.verMELDD || d?.verMELD) || link.meldeschluss || null,
            description:  d?.verBESCH      || d?.verBEM        || null,
            course:       null,
            type:         null,
            entries:      [],
            results:      [],
        });
    }

    return { competitions, errors };
}

// Extrahiert eine URL aus einem onclick-Attribut-String
// Erkennt: location.href='...', document.location='...', window.location='...',
//           openUrl('...'), go('...'), navigate('...')
function extractUrlFromOnclick(onclick) {
    if (!onclick) return null;
    const m = onclick.match(
        /(?:(?:window\.|document\.)?location(?:\.href)?\s*=|openUrl|go|navigate|showPage|gotoPage|openPage)\s*\(?\s*['"]([^'"]+)['"]/i
    );
    return m ? m[1] : null;
}

async function collectEventLinks(page, dateFrom, dateTo, capturedHtml = null) {
    const links = [];
    const seen  = new Set();

    if (capturedHtml) {
        // Primärstrategie 1: WebClub JSON-Veranstaltungsliste {"list":[{id,d,n,...}]}
        if (capturedHtml.trimStart().startsWith('{') && capturedHtml.includes('"list"')) {
            log('collectEventLinks: verarbeite WebClub-JSON-Veranstaltungsliste…');
            const jsonLinks = parseCompetitionListJson(capturedHtml, dateFrom, dateTo);
            if (jsonLinks && jsonLinks.length > 0) {
                log(`collectEventLinks: ${jsonLinks.length} Veranstaltungen aus JSON extrahiert`);
                return jsonLinks;
            }
            log('collectEventLinks: JSON geparst – keine Treffer im Datumsbereich');
        }

        // Primärstrategie 2: HTML mit <tr>-Datenzeilen
        log('collectEventLinks: verarbeite XHR-HTML-Response…');
        const xhrLinks = await parseEventLinksFromHtml(page, capturedHtml, dateFrom, dateTo);
        if (xhrLinks.length > 0) {
            log(`collectEventLinks: ${xhrLinks.length} Links aus XHR-HTML extrahiert`);
            return xhrLinks;
        }
        log('collectEventLinks: XHR-Daten enthielten keine verwertbaren Links – weiter mit DOM');
    }

    // Fallback: DOM-Scraping (wartet auf AJAX-Spinner)
    await waitForAjaxContent(page);

    // Alle table-Rows (mit oder ohne explizites <tbody>)
    const rows  = page.locator('table tr');
    const count = await rows.count();
    log(`collectEventLinks: ${count} table-rows auf ${page.url()}`);

    // Debug: erste Daten-Zeile als HTML loggen
    for (let i = 0; i < count; i++) {
        const tdCount = await rows.nth(i).locator('td').count();
        if (tdCount > 0) {
            const sample = await rows.nth(i).innerHTML().catch(() => '');
            log(`DEBUG erste Datenzeile (${tdCount} td): ${sample.slice(0, 400)}`);
            break;
        }
    }

    for (let i = 0; i < count; i++) {
        const row = rows.nth(i);

        // Header-Zeilen überspringen
        const tdCount = await row.locator('td').count();
        if (tdCount === 0) continue;

        const cells = row.locator('td');

        // Datum: prüfe alle Zellen auf ein Datumsmuster
        let dateText = null;
        for (let c = 0; c < Math.min(tdCount, 5); c++) {
            const txt = await safeText(cells.nth(c));
            if (txt && /\d{1,2}\.\d{1,2}\.\d{4}/.test(txt)) { dateText = txt; break; }
        }

        // Datumsfilter
        if (dateText) {
            const { date } = parseDateRange(dateText);
            if (date) {
                const d = new Date(date);
                if (d < dateFrom || d > dateTo) continue;
            }
        }

        // 1. Versuch: <a href="..."> in der Zeile (ohne javascript: / #)
        let foundUrl  = null;
        let foundName = null;

        const anchors = row.locator('a');
        const aCount  = await anchors.count();
        for (let a = 0; a < aCount; a++) {
            const href = await safeAttr(anchors.nth(a), 'href');
            if (href && href !== '#' && !href.startsWith('javascript:') && !href.startsWith('mailto:')) {
                foundUrl  = resolveUrl(href);
                foundName = await safeText(anchors.nth(a));
                break;
            }
            // 2. Versuch: onclick auf dem Anker-Tag
            const aOnclick = await safeAttr(anchors.nth(a), 'onclick');
            const fromAnchorOnclick = extractUrlFromOnclick(aOnclick);
            if (fromAnchorOnclick) {
                foundUrl  = resolveUrl(fromAnchorOnclick);
                foundName = await safeText(anchors.nth(a));
                break;
            }
        }

        // 3. Versuch: onclick auf der <tr> selbst
        if (!foundUrl) {
            const trOnclick = await safeAttr(row, 'onclick');
            const fromTrOnclick = extractUrlFromOnclick(trOnclick);
            if (fromTrOnclick) {
                foundUrl = resolveUrl(fromTrOnclick);
                // Name aus erster nicht-leerer Zelle mit Text
                for (let c = 0; c < tdCount; c++) {
                    const t = await safeText(cells.nth(c));
                    if (t && t.length > 3 && !/^\d{1,2}\.\d{1,2}/.test(t)) { foundName = t; break; }
                }
            }
        }

        // 4. Versuch: data-href / data-url auf tr oder td
        if (!foundUrl) {
            for (const attr of ['data-href', 'data-url', 'data-link', 'data-target']) {
                const val = await safeAttr(row, attr) || await safeAttr(cells.first(), attr);
                if (val) { foundUrl = resolveUrl(val); break; }
            }
        }

        if (!foundUrl) continue;
        if (seen.has(foundUrl)) continue;
        seen.add(foundUrl);

        const { date, date_end } = parseDateRange(dateText || '');
        links.push({ url: foundUrl, name: foundName, date, date_end });
    }

    log(`collectEventLinks: ${links.length} Links nach Tabellen-Scan`);
    return links;
}

async function scrapeCompetitionDetail(page, link) {
    log('Lade Veranstaltung: ' + link.url);

    // XHR-Responses auf der Detailseite abfangen
    const detailBodies = [];
    const captureDetail = async (res) => {
        try {
            if (!['xhr', 'fetch'].includes(res.request().resourceType())) return;
            if (res.status() < 200 || res.status() >= 300) return;
            const body = await res.text();
            if (body.length < 10) return;
            detailBodies.push(body);
            log(`XHR-Detail (${body.length}B): ${body.slice(0, 250).replace(/[\r\n]+/g, ' ')}`);
        } catch (_) {}
    };
    page.on('response', captureDetail);

    await page.goto(link.url, { waitUntil: 'load' });
    await waitForAjaxContent(page);

    page.off('response', captureDetail);

    // Debug: alle sichtbaren Tabs loggen
    const tabTexts = await page.evaluate(() =>
        Array.from(document.querySelectorAll('[role="tab"], .nav-link, .tab-link, a[class*="tab"], li[class*="tab"] a'))
            .map(el => el.textContent.trim()).filter(t => t.length > 0)
    );
    if (tabTexts.length > 0) log('Tabs gefunden: ' + tabTexts.join(' | '));

    await screenshot(page, 'competition_detail');

    // Basisfelder: aus dem Listen-JSON vorbelegt, Detail-Scraping kann überschreiben
    const comp = {
        webclub_id:   extractIdFromUrl(link.url),
        webclub_url:  link.url,
        name:         link.name,
        date:         link.date,
        date_end:     link.date_end,
        location:     link.location     || null,
        course:       null,
        organizer:    null,
        meldeschluss: link.meldeschluss || null,
        description:  null,
        type:         null,
        entries:      [],
        results:      [],
    };

    // ── Tab: Allgemeines / Ausschreibung / Organisation ──────────────────────
    await activateTab(page, /ausschreibung|organisation|allgemein|info|übersicht|detail/i);

    if (!comp.name) {
        comp.name = await safeText(page.locator('h1, h2, .page-title').first());
    }

    const fields = await extractLabelValuePairs(page);
    comp.location     = pickField(fields, /ort|location|veranstaltungsort|austragungsort/i);
    comp.organizer    = pickField(fields, /veranstalter|organizer|ausrichter/i);
    comp.meldeschluss = isoDate(pickField(fields, /meldeschluss|anmeldeschluss|deadline/i));
    comp.description  = pickField(fields, /beschreibung|description|bemerkung|hinweis/i);

    const courseRaw = pickField(fields, /bahn|course|strecke|pool/i);
    if (courseRaw) {
        if (/lang/i.test(courseRaw)) comp.course = 'Langbahn';
        else if (/kurz/i.test(courseRaw)) comp.course = 'Kurzbahn';
        else comp.course = courseRaw;
    }

    const dateRaw = pickField(fields, /datum|date|termin/i);
    if (dateRaw && !comp.date) {
        const { date, date_end } = parseDateRange(dateRaw);
        comp.date     = date;
        comp.date_end = date_end;
    }

    // ── Tab: Meldungen ───────────────────────────────────────────────────────
    const hasMeldungen = await activateTab(page, /meldung|anmeldung|einzel|entry/i);
    if (hasMeldungen) {
        comp.entries = await scrapeEntries(page);
        log(`  ${comp.entries.length} Meldungen gelesen`);
    }

    // ── Tab: Ergebnisse ──────────────────────────────────────────────────────
    const hasErgebnisse = await activateTab(page, /ergebnis|result|auswertung/i);
    if (hasErgebnisse) {
        comp.results = await scrapeResults(page);
        log(`  ${comp.results.length} Ergebnisse gelesen`);
    }

    return comp;
}

async function activateTab(page, labelRegex) {
    // Suche nach Tab-ähnlichen Elementen: echte ARIA-Tabs, Nav-Links, Anker in Tab-Leisten
    const candidates = [
        page.getByRole('tab', { name: labelRegex }),
        page.locator('[role="tab"]').filter({ hasText: labelRegex }),
        page.locator('.nav-link, .tab-link, .nav-item a').filter({ hasText: labelRegex }),
        page.locator('a[class*="tab"], li[class*="tab"] a').filter({ hasText: labelRegex }),
        page.locator('a').filter({ hasText: labelRegex }),
    ];

    let tab = null;
    for (const candidate of candidates) {
        if (await candidate.count() > 0) { tab = candidate.first(); break; }
    }
    if (!tab) return false;

    try {
        await tab.click({ timeout: 5000 });
        // Tab-Inhalte werden in WebClub per AJAX geladen
        await waitForAjaxContent(page);
        return true;
    } catch (_) {
        return false;
    }
}

async function extractLabelValuePairs(page) {
    const pairs = {};

    // Strategie 1: dt/dd Paare
    const dts = page.locator('dt');
    const ddsAll = page.locator('dd');
    const dtCount = await dts.count();
    for (let i = 0; i < dtCount; i++) {
        const label = (await safeText(dts.nth(i))) || '';
        const value = (await safeText(ddsAll.nth(i))) || '';
        if (label) pairs[label.toLowerCase()] = value;
    }

    // Strategie 2: label/span oder th/td Paare in Formularen/Tabellen
    const rows = page.locator('tr, .field-row, .form-row, [class*="field"]');
    const rowCount = await rows.count();
    for (let i = 0; i < rowCount; i++) {
        const row   = rows.nth(i);
        const cells = row.locator('th, td, label, span, div');
        const cnt   = await cells.count();
        if (cnt >= 2) {
            const label = (await safeText(cells.nth(0))) || '';
            const value = (await safeText(cells.nth(1))) || '';
            if (label && label.length < 60) pairs[label.toLowerCase()] = value;
        }
    }

    return pairs;
}

function pickField(fields, regex) {
    for (const key of Object.keys(fields)) {
        if (regex.test(key)) return fields[key] || null;
    }
    return null;
}

async function scrapeEntries(page) {
    const entries = [];
    const rows = page.locator('table tbody tr');
    const count = await rows.count();

    for (let i = 0; i < count; i++) {
        const cells = rows.nth(i).locator('td');
        const cnt   = await cells.count();
        if (cnt < 3) continue;

        // Typische Spalten: Name, Jahrgang, M/W, Strecke/WkNr, Zeit
        const entry = {
            athlete_name:      (await safeText(cells.nth(0))) || null,
            birth_year:        (await safeText(cells.nth(1))) || null,
            gender:            normalizeGender((await safeText(cells.nth(2))) || ''),
            event_label:       (await safeText(cells.nth(3))) || null,
            entry_time_str:    (await safeText(cells.nth(cnt > 5 ? 4 : cnt - 1))) || null,
            webclub_person_id: null,
        };

        // Versuche webclub_person_id aus Link zu extrahieren
        const anchor = rows.nth(i).locator('a').first();
        const href   = await safeAttr(anchor, 'href');
        if (href) entry.webclub_person_id = extractIdFromUrl(href);

        if (entry.athlete_name) entries.push(entry);
    }

    return entries;
}

async function scrapeResults(page) {
    const results = [];
    const rows = page.locator('table tbody tr');
    const count = await rows.count();

    for (let i = 0; i < count; i++) {
        const cells = rows.nth(i).locator('td');
        const cnt   = await cells.count();
        if (cnt < 4) continue;

        const result = {
            placement:         parseInt((await safeText(cells.nth(0))) || '0', 10) || null,
            athlete_name:      (await safeText(cells.nth(1))) || null,
            birth_year:        (await safeText(cells.nth(2))) || null,
            gender:            normalizeGender((await safeText(cells.nth(3))) || ''),
            event_label:       cnt > 5 ? ((await safeText(cells.nth(4))) || null) : null,
            time_str:          (await safeText(cells.nth(cnt - 2))) || (await safeText(cells.nth(cnt - 1))) || null,
            time_ms:           null,
            webclub_person_id: null,
        };

        const anchor = rows.nth(i).locator('a').first();
        const href   = await safeAttr(anchor, 'href');
        if (href) result.webclub_person_id = extractIdFromUrl(href);

        result.time_ms = parseTimeMs(result.time_str);

        if (result.athlete_name && result.time_ms) results.push(result);
    }

    return results;
}

// ── Personen ─────────────────────────────────────────────────────────────────

async function scrapePersons(page) {
    log('Navigiere zu Personen/Mitgliedern…');

    const persons = [];
    const errors  = [];

    // 1. Direkte PHP-URLs probieren
    const candidates = [
        BASE_URL + '/pers.php',          // WebClub klassisch (häufig)
        BASE_URL + '/person.php',
        BASE_URL + '/mitglieder.php',
        BASE_URL + '/personen',
        BASE_URL + '/mitglieder',
        BASE_URL + '/members',
        BASE_URL + '/persons',
        BASE_URL + '/athletes',
        BASE_URL + '/schwimmer',
    ];

    let navigated = false;
    for (const url of candidates) {
        try {
            await page.goto(url, { waitUntil: 'load', timeout: 10000 });
            await waitForAjaxContent(page);
            const rowCount = await page.locator('table tr').count();
            if (rowCount > 1) { navigated = true; log('Personenseite: ' + url); break; }
        } catch (_) {}
    }

    // 2. Fallback: Menü-Navigation
    if (!navigated) {
        navigated = await navigateViaDropdownMenu(page, /personen|mitglieder|members/i, /personen|mitglieder|members/i);
    }

    if (!navigated) {
        const msg = 'Personenseite nicht gefunden (alle Kandidaten und Menü fehlgeschlagen)';
        errors.push({ type: 'navigation', message: msg });
        log('WARNUNG: ' + msg);
        return { persons, errors };
    }

    // Alle Personen-Links sammeln (paginiert) — onclick und data-href eingeschlossen
    const personLinks = [];
    const seen = new Set();

    do {
        const tableRows = page.locator('table tr');
        const trCnt = await tableRows.count();
        for (let i = 0; i < trCnt; i++) {
            const row = tableRows.nth(i);
            if (await row.locator('td').count() === 0) continue; // Skip header rows

            let personUrl = null;

            // href auf Anker
            const anchors = row.locator('a');
            const aCnt = await anchors.count();
            for (let a = 0; a < aCnt; a++) {
                const href = await safeAttr(anchors.nth(a), 'href');
                if (href && href !== '#' && !href.startsWith('javascript:') && !href.startsWith('mailto:')) {
                    personUrl = resolveUrl(href); break;
                }
                const aOnclick = extractUrlFromOnclick(await safeAttr(anchors.nth(a), 'onclick'));
                if (aOnclick) { personUrl = resolveUrl(aOnclick); break; }
            }
            // onclick auf tr
            if (!personUrl) {
                const trUrl = extractUrlFromOnclick(await safeAttr(row, 'onclick'));
                if (trUrl) personUrl = resolveUrl(trUrl);
            }

            if (personUrl && !seen.has(personUrl)) {
                seen.add(personUrl);
                personLinks.push(personUrl);
            }
        }
    } while (await navigateToNextPage(page));

    log(`${personLinks.length} Personen-Links gefunden.`);

    // Personen aus der Listen-Tabelle direkt extrahieren (effizienter als jede Detailseite)
    const rows = page.locator('table tr');
    const rowCount = await rows.count();
    if (rowCount > 0) {
        // Header-Spalten ermitteln (erste Zeile mit th-Zellen)
        const headers = [];
        const thCells = page.locator('table thead th, table thead td');
        const thCount = await thCells.count();
        for (let i = 0; i < thCount; i++) {
            headers.push(((await safeText(thCells.nth(i))) || '').toLowerCase());
        }

        for (let i = 0; i < rowCount; i++) {
            const row   = rows.nth(i);
            const cells = row.locator('td');
            const cnt   = await cells.count();
            if (cnt < 2) continue;

            const person = { webclub_person_id: null };

            // ID aus Link
            const anchor = row.locator('a').first();
            const href   = await safeAttr(anchor, 'href');
            if (href) person.webclub_person_id = extractIdFromUrl(href);

            // Werte aus Zellen in bekannte Felder mappen
            for (let j = 0; j < Math.min(cnt, headers.length); j++) {
                const h = headers[j];
                const v = (await safeText(cells.nth(j))) || null;
                if (!v) continue;

                if (/^name$|nachname|last.?name/i.test(h))   person.lastname  = v;
                else if (/vorname|first.?name/i.test(h))      person.firstname = v;
                else if (/^name$/.test(h) && !person.lastname) {
                    // "Name" = "Vorname Nachname"
                    const parts = v.split(' ');
                    person.firstname = parts.slice(0, -1).join(' ') || null;
                    person.lastname  = parts[parts.length - 1] || v;
                }
                else if (/geburt|birthday|born/i.test(h)) person.birth_date = isoDate(v);
                else if (/geschlecht|gender|sex/i.test(h)) person.gender = normalizeGender(v);
                else if (/dsv.?id|dsv/i.test(h))           person.dsv_id = v;
                else if (/mitglied|member.?nr|membership/i.test(h)) person.membership_number = v;
                else if (/mail|email/i.test(h))             person.email = v;
                else if (/telefon|phone|mobil/i.test(h))    person.phone = v;
                else if (/gruppe|group|training/i.test(h))  person.training_group = v;
            }

            if (person.lastname || person.firstname) persons.push(person);
        }
    }

    // Falls Listentabelle zu wenig Daten liefert: Detailseiten besuchen (max. 200)
    if (persons.length === 0 && personLinks.length > 0) {
        for (const url of personLinks.slice(0, 200)) {
            try {
                const p = await scrapePersonDetail(page, url);
                if (p) persons.push(p);
            } catch (e) {
                errors.push({ type: 'person', url, message: e.message });
            }
        }
    }

    return { persons, errors };
}

async function scrapePersonDetail(page, url) {
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(500);

    const person = {
        webclub_person_id: extractIdFromUrl(url),
        webclub_url: url,
    };

    const fields = await extractLabelValuePairs(page);

    person.lastname          = pickField(fields, /nachname|last.?name/i);
    person.firstname         = pickField(fields, /vorname|first.?name/i);
    person.birth_date        = isoDate(pickField(fields, /geburt|birthday|born/i));
    person.gender            = normalizeGender(pickField(fields, /geschlecht|gender|sex/i) || '');
    person.dsv_id            = pickField(fields, /dsv.?id/i);
    person.membership_number = pickField(fields, /mitglied|membership/i);
    person.email             = pickField(fields, /^e-?mail|^mail/i);
    person.phone             = pickField(fields, /telefon|phone|festnetz/i);
    person.mobile            = pickField(fields, /mobil|handy|mobile/i);
    person.street            = pickField(fields, /strasse|straße|street|adresse/i);
    person.postal_code       = pickField(fields, /plz|postleitzahl|zip/i);
    person.city              = pickField(fields, /^ort$|^stadt|^city/i);
    person.country           = pickField(fields, /land|country/i);
    person.training_group    = pickField(fields, /gruppe|group|training/i);
    person.active            = true; // Default; Austreten würde separate Logik erfordern

    // Falls Name noch nicht aus Formular: aus Seitenüberschrift
    if (!person.lastname) {
        const heading = await safeText(page.locator('h1, h2, .page-title').first());
        if (heading) {
            const parts = heading.split(/\s+/);
            person.firstname = parts.slice(0, -1).join(' ') || null;
            person.lastname  = parts[parts.length - 1] || heading;
        }
    }

    return (person.lastname || person.firstname) ? person : null;
}

async function navigateToNextPage(page) {
    try {
        const next = page.getByRole('link', { name: /nächste|weiter|next|›|»/i })
            .or(page.locator('[rel="next"], .pagination a.next, [aria-label*="next"]'))
            .first();
        const exists  = await next.count() > 0;
        const enabled = exists && !(await next.isDisabled().catch(() => true));
        if (!enabled) return false;
        await next.click();
        await page.waitForLoadState('load');
        await page.waitForTimeout(500);
        return true;
    } catch (_) {
        return false;
    }
}

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────

function extractIdFromUrl(url) {
    if (!url) return null;
    // ?id=123, ?nr=123, ?vid=123, ?verid=123, etc.
    const qParam = url.match(/[?&](?:id|nr|vid|verid|no|num|f)=(\d+)/i);
    if (qParam) return qParam[1];
    // /path/123 am Ende
    const pathNum = url.match(/\/(\d+)\/?(?:[?#].*)?$/);
    if (pathNum) return pathNum[1];
    // Kein ID gefunden – URL-Hash als Fallback-Schlüssel
    let hash = 0;
    for (let i = 0; i < url.length; i++) { hash = (hash * 31 + url.charCodeAt(i)) >>> 0; }
    return 'url-' + hash.toString(16);
}

function normalizeGender(val) {
    if (!val) return null;
    val = val.toLowerCase().trim();
    if (['m', 'männlich', 'male', 'man', 'herr', 'junge'].includes(val)) return 'M';
    if (['w', 'f', 'weiblich', 'female', 'woman', 'frau', 'mädchen'].includes(val)) return 'F';
    return null;
}

function parseTimeMs(str) {
    if (!str) return null;
    str = str.trim().replace(',', '.');
    // "m:ss.hh" oder "ss.hh" oder "m:ss,hh"
    const full = str.match(/^(\d+):(\d{2})[.,](\d{2})$/);
    if (full) {
        return (parseInt(full[1]) * 60 + parseInt(full[2])) * 1000 + parseInt(full[3]) * 10;
    }
    const short = str.match(/^(\d+)[.,](\d{2})$/);
    if (short) {
        return parseInt(short[1]) * 1000 + parseInt(short[2]) * 10;
    }
    return null;
}

// ── Main ─────────────────────────────────────────────────────────────────────

(async () => {
    const result = {
        competitions: [],
        persons:      [],
        errors:       [],
    };

    let browser;
    try {
        process.env.PLAYWRIGHT_BROWSERS_PATH = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';

        browser = await chromium.launch({
            headless: HEADLESS,
            executablePath: process.env.CHROMIUM_EXECUTABLE || undefined,
        });

        const page = await browser.newPage();
        page.setDefaultTimeout(TIMEOUT_MS);

        // Diagnose: XHR-Requests, JS-Fehler, fehlgeschlagene Requests
        page.on('request', req => {
            if (['xhr', 'fetch'].includes(req.resourceType())) {
                log(`XHR > ${req.method()} ${req.url().replace(BASE_URL, '***')}`);
            }
        });
        page.on('requestfailed', req => {
            log(`Request FAILED: [${req.resourceType()}] ${req.url().replace(BASE_URL, '***')} – ${req.failure()?.errorText || 'unknown'}`);
        });
        page.on('console', msg => {
            if (msg.type() === 'error') log(`JS-Fehler: ${msg.text()}`);
        });
        page.on('pageerror', err => {
            log(`Page-Error: ${err.message}`);
        });

        await login(page);

        if (DO_COMPETITIONS) {
            const { competitions, errors } = await scrapeCompetitions(page);
            result.competitions = competitions;
            result.errors.push(...errors);
        }

        if (DO_PERSONS) {
            const { persons, errors } = await scrapePersons(page);
            result.persons = persons;
            result.errors.push(...errors);
        }

        log(`Fertig: ${result.competitions.length} Veranstaltungen, ${result.persons.length} Personen, ${result.errors.length} Fehler.`);
    } catch (e) {
        result.errors.push({ type: 'fatal', message: e.message });
        log('FATAL: ' + e.message);
        if (browser) await browser.close().catch(() => {});
        process.stdout.write(JSON.stringify(result));
        process.exit(1);
    }

    await browser.close().catch(() => {});
    process.stdout.write(JSON.stringify(result));
    process.exit(0);
})();

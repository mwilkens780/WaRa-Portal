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
    log('Öffne Login-Seite: ' + BASE_URL);
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });

    // Login-Formular finden – versuche gängige Selektoren
    const usernameField =
        page.getByLabel(/e-?mail|benutzername|username/i).first()
        || page.locator('input[type="email"], input[name*="email"], input[name*="user"]').first();

    const passwordField =
        page.getByLabel(/passwort|password/i).first()
        || page.locator('input[type="password"]').first();

    try {
        await usernameField.waitFor({ state: 'visible', timeout: TIMEOUT_MS });
    } catch (e) {
        // Eventuell wird auf /login weitergeleitet
        try {
            await page.goto(BASE_URL + '/login', { waitUntil: 'networkidle' });
            await usernameField.waitFor({ state: 'visible', timeout: TIMEOUT_MS });
        } catch (e2) {
            await screenshot(page, 'login_not_found');
            die('Login-Formular nicht gefunden. URL: ' + page.url());
        }
    }

    await usernameField.fill(USERNAME);
    await passwordField.fill(PASSWORD);

    // Submit: suche Button oder Enter
    const submitBtn =
        page.getByRole('button', { name: /anmelden|einloggen|login|sign in/i }).first()
        || page.locator('button[type="submit"], input[type="submit"]').first();

    try {
        await submitBtn.click({ timeout: TIMEOUT_MS });
    } catch (_) {
        await page.keyboard.press('Enter');
    }

    await page.waitForLoadState('networkidle');

    // Login prüfen: kein Passwortfeld mehr sichtbar?
    const stillLoginPage = await page.locator('input[type="password"]').isVisible().catch(() => false);
    if (stillLoginPage) {
        await screenshot(page, 'login_failed');
        die('Login fehlgeschlagen – Anmeldedaten prüfen. URL: ' + page.url());
    }

    log('Login erfolgreich. URL: ' + page.url());
}

// ── Veranstaltungen (Competitions) ───────────────────────────────────────────

async function scrapeCompetitions(page) {
    log('Navigiere zu Veranstaltungen…');

    const competitions = [];
    const errors       = [];

    // Navigation zu Veranstaltungen
    try {
        // Versuche direkten Seitenaufruf via typische URL-Muster
        const candidates = [
            BASE_URL + '/veranstaltungen',
            BASE_URL + '/events',
            BASE_URL + '/competition',
            BASE_URL + '/competitions',
            BASE_URL + '/wettkampf',
        ];

        let navigated = false;
        for (const url of candidates) {
            await page.goto(url, { waitUntil: 'networkidle', timeout: 10000 }).catch(() => {});
            // Prüfe ob Seite Veranstaltungs-Inhalte hat
            const hasContent = await page.locator('table, .event-list, .competition-list, [class*="veranstaltung"], [class*="event"]').count();
            if (hasContent > 0) { navigated = true; log('Veranstaltungsseite: ' + url); break; }
        }

        if (!navigated) {
            // Versuche Navigation über Menü
            const navLink = page.getByRole('link', { name: /veranstaltung|wettkampf|event|competition/i }).first();
            await navLink.click({ timeout: TIMEOUT_MS });
            await page.waitForLoadState('networkidle');
            log('Navigiert via Menü: ' + page.url());
        }
    } catch (e) {
        errors.push({ type: 'navigation', message: 'Veranstaltungsseite nicht gefunden: ' + e.message });
        log('WARNUNG: ' + errors[errors.length - 1].message);
        return { competitions, errors };
    }

    // Datumsbereich
    const today     = new Date();
    const dateFrom  = new Date(today); dateFrom.setDate(today.getDate() - LOOKBACK_DAYS);
    const dateTo    = new Date(today); dateTo.setDate(today.getDate() + LOOKAHEAD_DAYS);

    // Eventliste scrapen – suche Zeilen in Tabellen oder Listen
    const eventLinks = await collectEventLinks(page, dateFrom, dateTo);
    log(`${eventLinks.length} Veranstaltungslinks gefunden.`);

    for (const link of eventLinks) {
        try {
            const comp = await scrapeCompetitionDetail(page, link);
            if (comp) competitions.push(comp);
        } catch (e) {
            errors.push({ type: 'competition', url: link.url, message: e.message });
            log('FEHLER bei ' + link.url + ': ' + e.message);
        }
    }

    return { competitions, errors };
}

async function collectEventLinks(page, dateFrom, dateTo) {
    const links = [];
    const seen  = new Set();

    // Suche Tabellen-Zeilen oder Listen-Einträge mit Links
    const rows = page.locator('table tbody tr, .event-item, .competition-item, [class*="event-row"], [class*="veranstaltung-row"]');
    const count = await rows.count();

    for (let i = 0; i < count; i++) {
        const row = rows.nth(i);

        // Datum aus Zeile extrahieren
        const dateText = await safeText(row.locator('[class*="date"], [class*="datum"], td:first-child').first())
            || await safeText(row.locator('td').nth(0));

        if (dateText) {
            const { date } = parseDateRange(dateText);
            if (date) {
                const d = new Date(date);
                if (d < dateFrom || d > dateTo) continue;
            }
        }

        // Link aus Zeile extrahieren
        const anchor = row.locator('a').first();
        const href   = await safeAttr(anchor, 'href');
        if (!href || seen.has(href)) continue;
        seen.add(href);

        const url = href.startsWith('http') ? href : BASE_URL + href;
        const name = await safeText(anchor);
        const { date, date_end } = parseDateRange(dateText || '');

        links.push({ url, name, date, date_end });
    }

    // Falls keine Tabellen-Zeilen: suche alle Links auf der Seite, die nach Events aussehen
    if (links.length === 0) {
        const allLinks = page.locator('a[href*="veranstaltung"], a[href*="event"], a[href*="competition"]');
        const allCount = await allLinks.count();
        for (let i = 0; i < allCount; i++) {
            const href = await safeAttr(allLinks.nth(i), 'href');
            if (!href || seen.has(href)) continue;
            seen.add(href);
            const url = href.startsWith('http') ? href : BASE_URL + href;
            links.push({ url, name: null, date: null, date_end: null });
        }
    }

    return links;
}

async function scrapeCompetitionDetail(page, link) {
    log('Lade Veranstaltung: ' + link.url);
    await page.goto(link.url, { waitUntil: 'networkidle' });

    const comp = {
        webclub_id: extractIdFromUrl(link.url),
        webclub_url: link.url,
        name: link.name,
        date: link.date,
        date_end: link.date_end,
        location: null,
        course: null,
        organizer: null,
        meldeschluss: null,
        description: null,
        type: null,
        entries: [],
        results: [],
    };

    // ── Tab: Organisation ────────────────────────────────────────────────────
    await activateTab(page, /organisation|allgemein|info|übersicht/i);

    // Name aus Seitenüberschrift falls noch nicht bekannt
    if (!comp.name) {
        comp.name = await safeText(page.locator('h1, h2, .page-title, .competition-name').first());
    }

    // Felder per Label/Wert-Paare auslesen
    const fields = await extractLabelValuePairs(page);

    comp.location     = comp.location     || pickField(fields, /ort|location|veranstaltungsort|austragungsort/i);
    comp.organizer    = comp.organizer    || pickField(fields, /veranstalter|organizer|ausrichter|organisator/i);
    comp.meldeschluss = comp.meldeschluss || isoDate(pickField(fields, /meldeschluss|anmeldeschluss|deadline|einsendeschluss/i));
    comp.description  = comp.description  || pickField(fields, /beschreibung|description|bemerkung|hinweis/i);

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
    const hasMeldungen = await activateTab(page, /meldung|anmeldung|entry|einzel/i);
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
    const tab = page.getByRole('tab', { name: labelRegex }).or(
        page.locator('[role="tab"], .tab, .nav-tab, .nav-link, [class*="tab"]')
            .filter({ hasText: labelRegex })
    ).first();

    const exists = await tab.count() > 0;
    if (!exists) return false;

    try {
        await tab.click({ timeout: 5000 });
        await page.waitForLoadState('networkidle');
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

    // Navigation zu Personen
    try {
        const candidates = [
            BASE_URL + '/personen',
            BASE_URL + '/mitglieder',
            BASE_URL + '/members',
            BASE_URL + '/persons',
            BASE_URL + '/athletes',
            BASE_URL + '/schwimmer',
        ];

        let navigated = false;
        for (const url of candidates) {
            await page.goto(url, { waitUntil: 'networkidle', timeout: 10000 }).catch(() => {});
            const hasContent = await page.locator('table tbody tr, .person-list, .member-list').count();
            if (hasContent > 0) { navigated = true; log('Personenseite: ' + url); break; }
        }

        if (!navigated) {
            const navLink = page.getByRole('link', { name: /personen|mitglieder|members|athletes|schwimmer/i }).first();
            await navLink.click({ timeout: TIMEOUT_MS });
            await page.waitForLoadState('networkidle');
            log('Navigiert via Menü: ' + page.url());
        }
    } catch (e) {
        errors.push({ type: 'navigation', message: 'Personenseite nicht gefunden: ' + e.message });
        log('WARNUNG: ' + errors[errors.length - 1].message);
        return { persons, errors };
    }

    // Alle Personen-Links sammeln (paginiert)
    const personLinks = [];
    const seen = new Set();

    do {
        const anchors = page.locator('table tbody tr a, .person-item a, .member-item a').first().locator('xpath=../ancestor::tr//a').or(
            page.locator('table tbody tr a, a[href*="person"], a[href*="mitglied"], a[href*="member"]')
        );
        const cnt = await anchors.count();
        for (let i = 0; i < cnt; i++) {
            const href = await safeAttr(anchors.nth(i), 'href');
            if (!href || seen.has(href)) continue;
            // Nur Detailseiten (nicht Listen-Filter)
            if (!href.includes('/person') && !href.includes('/mitglied') && !href.includes('/member') &&
                !href.includes('/athlete') && !href.includes('/schwimmer') && !/\/\d+/.test(href)) continue;
            seen.add(href);
            personLinks.push(href.startsWith('http') ? href : BASE_URL + href);
        }
    } while (await navigateToNextPage(page));

    log(`${personLinks.length} Personen-Links gefunden.`);

    // Personen aus der Listen-Tabelle direkt extrahieren (effizienter als jede Detailseite)
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();
    if (rowCount > 0) {
        // Header-Spalten ermitteln
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
    await page.goto(url, { waitUntil: 'networkidle' });

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
        await page.waitForLoadState('networkidle');
        return true;
    } catch (_) {
        return false;
    }
}

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────

function extractIdFromUrl(url) {
    if (!url) return null;
    // /path/123, /path/123/, /path?id=123
    const m = url.match(/\/(\d+)\/?(?:[?#].*)?$/) || url.match(/[?&]id=(\d+)/);
    return m ? m[1] : null;
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

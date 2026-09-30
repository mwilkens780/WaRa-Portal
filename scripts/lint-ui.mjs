#!/usr/bin/env node
/**
 * UI-Regeln pruefen (docs/design-system.md, Abschnitt 14).
 *
 *   npm run lint:ui              pruefen; scheitert bei NEUEN Verstoessen
 *   npm run lint:ui -- --update  Ausgangsliste nach einer Bereinigung neu schreiben
 *   npm run lint:ui -- --all     alle Verstoesse (auch Altlasten) mit Zeile ausgeben
 *
 * Altlasten stehen je Datei und Regel in tests/ui-lint-baseline.json und
 * duerfen nur weniger werden ("Ratsche"). Neue Dateien muessen sauber sein.
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = process.cwd();
const BASELINE = path.join(ROOT, 'tests', 'ui-lint-baseline.json');
const args = new Set(process.argv.slice(2));

// ── Regeln ──────────────────────────────────────────────────────────────────
// Jede Regel liefert fuer eine Datei eine Liste von { line, hint }.

const lineOf = (s, i) => s.slice(0, i).split('\n').length;
const classOf = (attrs) => (attrs.match(/(?<![:\w@-])class="([^"]*)"/) || [])[1] || '';
const ERLAUBTE_KNOPF_FLAECHEN = /^(primary|primary-dark|accent|accent-dark)$/;

const RULES = {
    'knopf-farbe': {
        text: 'Knopf mit eigener Farbe – x-ui.button (primary | secondary | danger) benutzen',
        check(s) {
            const out = [];
            for (const m of s.matchAll(/<(button|a)\b([^>]*?)>/g)) {
                const cls = classOf(m[2]);
                if (!cls || cls.includes('{{') || !/(?<![\w:-])text-white\b/.test(cls)) continue;
                const bg = (cls.match(/(?<![\w:/-])bg-((?:[a-z]+-\d{2,3})|primary(?:-dark)?|accent(?:-dark)?)(?![\w/-])/) || [])[1];
                if (bg && !ERLAUBTE_KNOPF_FLAECHEN.test(bg)) out.push({ line: lineOf(s, m.index), hint: `bg-${bg}` });
            }
            return out;
        },
    },
    'native-dialoge': {
        text: 'alert()/confirm()/prompt() – $confirm, $prompt, $toast oder data-confirm benutzen',
        check(s, file) {
            if (file.endsWith(path.join('js', 'ui', 'confirm.js'))) return [];
            const out = [];
            for (const m of s.matchAll(/(?<![\w.$])(?:window\.)?(alert|confirm|prompt)\s*\(/g)) {
                const zeile = s.slice(s.lastIndexOf('\n', m.index) + 1, s.indexOf('\n', m.index));
                if (/^\s*(\*|\/\/|\{\{--)/.test(zeile)) continue; // Kommentar
                out.push({ line: lineOf(s, m.index), hint: m[0].trim() });
            }
            return out;
        },
    },
    'text-zu-blass': {
        text: 'text-gray-200/300 als Textfarbe (1,5:1) – für Text mindestens text-gray-600',
        check(s) {
            const out = [];
            for (const m of s.matchAll(/<([a-z][\w-]*)\b([^>]*?)>/g)) {
                if (['svg', 'path', 'hr', 'circle', 'line', 'rect', 'polyline'].includes(m[1])) continue;
                const cls = classOf(m[2]);
                if (/(?<![\w:-])text-gray-(200|300)(?![\w/-])/.test(cls)) out.push({ line: lineOf(s, m.index), hint: `<${m[1]}>` });
            }
            return out;
        },
    },
    'text-transparent': {
        text: 'halbtransparente Textfarbe senkt den Kontrast – volle Farbe benutzen',
        check(s) {
            return [...s.matchAll(/(?<![\w:-])text-(?:[a-z]+(?:-\d{2,3})?)\/\d{1,3}(?![\w-])/g)]
                .filter((m) => !/^text-(xs|sm|base|lg|xl)/.test(m[0]))
                .map((m) => ({ line: lineOf(s, m.index), hint: m[0] }));
        },
    },
    'schrift-winzig': {
        text: 'Schrift unter 10 px – Mindestgröße ist 12 px (text-xs), in dichten Rastern 10 px',
        check(s) {
            return [...s.matchAll(/(?<![\w:-])text-\[(\d+(?:\.\d+)?)px\]/g)]
                .filter((m) => parseFloat(m[1]) < 10)
                .map((m) => ({ line: lineOf(s, m.index), hint: m[0] }));
        },
    },
    'x-tag-in-skript': {
        text: '<x-…> in einem Kommentar innerhalb von <script> – Blade kompiliert es (Fehler 500)',
        check(s) {
            const out = [];
            for (const blk of s.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)) {
                const start = blk.index + blk[0].indexOf(blk[1]);
                for (const m of blk[1].matchAll(/(\/\/[^\n]*|\/\*[\s\S]*?\*\/)/g)) {
                    if (/<x-[\w.-]+/.test(m[0])) out.push({ line: lineOf(s, start + m.index), hint: m[0].match(/<x-[\w.-]+/)[0] });
                }
            }
            return out;
        },
    },
    'anfuehrung-im-attribut': {
        text: '\\" in einem HTML-Attribut beendet das Attribut – innen einfache Anführungszeichen',
        check(s) {
            return [...s.matchAll(/\s(?:on\w+|@[\w.:-]+|x-[\w.:-]+|:[\w.-]+)="[^"\n]*\\"/g)]
                .map((m) => ({ line: lineOf(s, m.index), hint: m[0].trim().slice(0, 40) }));
        },
    },
    'tailwind-zusammengesetzt': {
        text: 'zusammengesetzte Tailwind-Klasse – der Build findet sie nicht; ausschreiben',
        check(s) {
            return [...s.matchAll(/(?<![\w-])(?:bg|text|border|ring|from|to)-\{\{/g)]
                .map((m) => ({ line: lineOf(s, m.index), hint: m[0] }));
        },
    },
    'feld-ohne-beschriftung': {
        text: 'Eingabefeld ohne Beschriftung – x-ui.field, <label for> oder aria-label',
        check(s) {
            const out = [];
            for (const m of s.matchAll(/<(input|select|textarea)\b([^>]*?)\/?>/g)) {
                const a = m[2];
                if (/\btype\s*=\s*"(hidden|submit|button|reset|image)"/.test(a)) continue;
                if (/\b(id|:id|aria-label|:aria-label|aria-labelledby)\s*=/.test(a)) continue;
                // im <label> eingeschlossen?
                const vorher = s.slice(0, m.index);
                if (vorher.lastIndexOf('<label') > vorher.lastIndexOf('</label>')) continue;
                out.push({ line: lineOf(s, m.index), hint: `<${m[1]} ${(a.match(/name="[^"]*"/) || [''])[0]}>` });
            }
            return out;
        },
    },
    'eigenes-overlay': {
        text: 'eigenes Vollbild-Overlay – x-ui.dialog benutzen (Fokus, Escape, Bottom-Sheet)',
        check(s, file) {
            if (file.includes(path.join('components', 'ui'))) return [];
            return [...s.matchAll(/class="[^"]*\bfixed inset-0\b[^"]*"/g)].map((m) => ({ line: lineOf(s, m.index), hint: 'fixed inset-0' }));
        },
    },
    'roh-html': {
        text: '{!! !!} gibt ungefiltert aus – für Nutzertext x-ui.rich-text; sonst bewusst prüfen',
        check(s) {
            return [...s.matchAll(/\{!!\s*([^!]*?)\s*!!\}/g)].map((m) => ({ line: lineOf(s, m.index), hint: m[1].slice(0, 40) }));
        },
    },
    'neu-laden': {
        text: 'window.location.reload() nach dem Speichern – Ergebnis in die Seite übernehmen + $toast',
        check(s) {
            return [...s.matchAll(/location\.reload\(\)/g)].map((m) => ({ line: lineOf(s, m.index), hint: 'reload()' }));
        },
    },
};

// ── Dateien ─────────────────────────────────────────────────────────────────
function walk(dir, out = []) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) walk(p, out);
        else if (p.endsWith('.blade.php') || (p.endsWith('.js') && p.includes(path.join('resources', 'js')))) out.push(p);
    }
    return out;
}
const files = [...walk(path.join(ROOT, 'resources', 'views')), ...walk(path.join(ROOT, 'resources', 'js'))]
    .filter((f) => !f.includes(path.join('views', 'vendor')) && !f.includes(path.join('views', 'emails')));

const found = {};
for (const f of files) {
    const rel = path.relative(ROOT, f).split(path.sep).join('/');
    const s = fs.readFileSync(f, 'utf8').replace(/\r\n/g, '\n');
    for (const [rule, def] of Object.entries(RULES)) {
        if (rule !== 'native-dialoge' && rule !== 'neu-laden' && f.endsWith('.js')) continue;
        const hits = def.check(s, f);
        if (hits.length) (found[rel] ??= {})[rule] = hits;
    }
}

const counts = Object.fromEntries(Object.entries(found).map(([f, r]) => [f, Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v.length]))]));

if (args.has('--update')) {
    const sorted = Object.fromEntries(Object.keys(counts).sort().map((k) => [k, counts[k]]));
    fs.writeFileSync(BASELINE, JSON.stringify(sorted, null, 2) + '\n');
    const total = Object.values(counts).reduce((a, r) => a + Object.values(r).reduce((x, y) => x + y, 0), 0);
    console.log(`Ausgangsliste geschrieben: ${total} Altlasten in ${Object.keys(counts).length} Dateien.`);
    process.exit(0);
}

const base = fs.existsSync(BASELINE) ? JSON.parse(fs.readFileSync(BASELINE, 'utf8')) : {};
const neu = [], weniger = [];
for (const [file, rules] of Object.entries(found)) {
    for (const [rule, hits] of Object.entries(rules)) {
        const erlaubt = base[file]?.[rule] ?? 0;
        if (hits.length > erlaubt) neu.push({ file, rule, hits, erlaubt });
    }
}
for (const [file, rules] of Object.entries(base)) {
    for (const [rule, n] of Object.entries(rules)) {
        if ((counts[file]?.[rule] ?? 0) < n) weniger.push(`${file}: ${rule} ${n} → ${counts[file]?.[rule] ?? 0}`);
    }
}

// Uebersicht je Regel
const perRule = {};
for (const r of Object.values(counts)) for (const [k, v] of Object.entries(r)) perRule[k] = (perRule[k] || 0) + v;
console.log('UI-Regeln (docs/design-system.md) – Altlasten gesamt:');
for (const k of Object.keys(RULES)) console.log(`  ${k.padEnd(26)} ${String(perRule[k] || 0).padStart(4)}`);

if (args.has('--all')) {
    for (const [file, rules] of Object.entries(found)) for (const [rule, hits] of Object.entries(rules)) for (const h of hits) console.log(`${file}:${h.line}  [${rule}] ${h.hint}`);
}

if (weniger.length) {
    console.log(`\nWeniger Altlasten als erfasst (${weniger.length}) – bitte "npm run lint:ui -- --update" und die Ausgangsliste mit committen:`);
    weniger.slice(0, 20).forEach((w) => console.log('  ' + w));
}

if (neu.length) {
    console.error(`\n✗ ${neu.length} neue Verstöße:`);
    for (const { file, rule, hits, erlaubt } of neu) {
        console.error(`\n  ${file}  [${rule}]  ${hits.length} statt höchstens ${erlaubt}`);
        console.error(`    → ${RULES[rule].text}`);
        hits.slice(0, 8).forEach((h) => console.error(`    Zeile ${h.line}: ${h.hint}`));
    }
    console.error('\nSiehe docs/design-system.md. Nur wenn ein Fund wirklich gewollt ist: Ausgangsliste mit --update anpassen und begründen.');
    process.exit(1);
}
console.log('\n✓ Keine neuen Verstöße.');

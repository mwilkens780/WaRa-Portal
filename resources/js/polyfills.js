/**
 * Polyfills fuer iOS/iPadOS 15.0–15.3 (Untergrenze, docs/frontend-audit.md 8.1).
 *
 * Vite rechnet nur Syntax herunter, keine fehlenden Funktionen. Tiptap ruft
 * Array.prototype.findLast (bei jeder Eingabe) und .at() auf - beides gibt es
 * in Safari erst ab 15.4. Ohne diese Zeilen waere der Editor dort tot.
 *
 * Neue Funktionen aus Abhaengigkeiten: nach `npm run build` die Bundles auf
 * .at( / findLast / structuredClone pruefen (Suche in public/build/assets).
 */
if (!Array.prototype.at) {
    Object.defineProperty(Array.prototype, 'at', {
        configurable: true,
        writable: true,
        value(index) {
            const n = Math.trunc(index) || 0;
            const i = n < 0 ? this.length + n : n;
            return i < 0 || i >= this.length ? undefined : this[i];
        },
    });
}

for (const [name, fromEnd] of [['findLast', false], ['findLastIndex', true]]) {
    if (!Array.prototype[name]) {
        Object.defineProperty(Array.prototype, name, {
            configurable: true,
            writable: true,
            value(predicate, thisArg) {
                for (let i = this.length - 1; i >= 0; i--) {
                    if (predicate.call(thisArg, this[i], i, this)) return fromEnd ? i : this[i];
                }
                return fromEnd ? -1 : undefined;
            },
        });
    }
}

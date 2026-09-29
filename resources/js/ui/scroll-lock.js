/**
 * Seite hinter Dialogen festhalten.
 *
 * overflow:hidden reicht auf iOS < 16 nicht - die Seite scrollt dort trotzdem
 * mit. Deshalb wird der Body fixiert und die Scrollposition gemerkt.
 * Zaehlt mit: Zwei offene Dialoge sperren einmal und geben erst frei, wenn
 * beide zu sind.
 */
let locks = 0;
let savedY = 0;

export function lockScroll() {
    if (locks++ > 0) return;
    savedY = window.scrollY;
    const b = document.body.style;
    b.position = 'fixed';
    b.top = `-${savedY}px`;
    b.left = '0';
    b.right = '0';
    b.width = '100%';
}

export function unlockScroll() {
    if (locks === 0 || --locks > 0) return;
    const b = document.body.style;
    b.position = b.top = b.left = b.right = b.width = '';
    window.scrollTo(0, savedY);
}

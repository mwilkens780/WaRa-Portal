/**
 * Waagerecht scrollbare Bereiche (breite Tabellen auf dem Handy) per
 * Tastatur erreichbar machen - WCAG 2.1.1, axe "scrollable-region-focusable".
 *
 * Nur Bereiche, die wirklich ueberlaufen und nichts Fokussierbares enthalten
 * (sonst gelangt man ueber die Links/Knoepfe darin ohnehin hinein). Sie
 * bekommen tabindex=0 und einen Namen; mit den Pfeiltasten scrollt der
 * Browser dann selbst.
 */
const FOCUSABLE = 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])';

function markScrollRegions(root = document) {
    root.querySelectorAll('.overflow-x-auto, .overflow-auto').forEach((el) => {
        if (el.hasAttribute('data-scroll-region')) return;
        if (el.scrollWidth <= el.clientWidth + 1) return;
        if (el.querySelector(FOCUSABLE)) return;
        el.setAttribute('tabindex', '0');
        el.setAttribute('data-scroll-region', '');
        if (!el.hasAttribute('role')) el.setAttribute('role', 'region');
        if (!el.hasAttribute('aria-label') && !el.hasAttribute('aria-labelledby')) {
            el.setAttribute('aria-label', el.querySelector('table') ? 'Tabelle, waagerecht scrollbar' : 'Waagerecht scrollbarer Bereich');
        }
    });
}

let timer;
const later = () => { clearTimeout(timer); timer = setTimeout(() => markScrollRegions(), 200); };

document.addEventListener('DOMContentLoaded', () => markScrollRegions());
document.addEventListener('alpine:initialized', later);
window.addEventListener('load', () => markScrollRegions());
window.addEventListener('resize', later);
// Reiter/Aufklappen zeigen Tabellen erst spaeter - danach erneut pruefen
document.addEventListener('click', later);

window.uiMarkScrollRegions = markScrollRegions;

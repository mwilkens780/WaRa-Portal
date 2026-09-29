/**
 * Bestaetigen statt window.confirm().
 *
 * Das native confirm() ist nicht gestaltbar, wird auf iOS teils unterdrueckt
 * und sagt nichts ueber die Folgen. Hier:
 *
 *   JS:     if (await $confirm({ title, text, confirmLabel, danger })) { ... }
 *   Blade:  <form ... data-confirm="Rekord löschen?" data-confirm-text="..." data-confirm-danger>
 *           <a href=".." data-confirm="...">  bzw. <button data-confirm="...">
 *
 * Der Dialog selbst steht einmal im Layout (x-ui.confirm-dialog).
 */
import { lockScroll, unlockScroll } from './scroll-lock';

export function registerConfirm(Alpine) {
    Alpine.store('confirm', {
        open: false,
        title: '',
        text: '',
        confirmLabel: 'OK',
        cancelLabel: 'Abbrechen',
        danger: false,
        _resolve: null,

        ask({ title = 'Bist du sicher?', text = '', confirmLabel = 'Bestätigen', cancelLabel = 'Abbrechen', danger = false } = {}) {
            // Eine offene Frage wird als "Abbrechen" beendet, bevor die naechste kommt
            if (this._resolve) this._resolve(false);
            else {
                lockScroll();
                // Fokus danach dorthin zurueck, wo er war (z. B. ins Feld eines offenen Dialogs)
                this._returnTo = document.activeElement;
            }
            Object.assign(this, { title, text, confirmLabel, cancelLabel, danger, open: true });
            return new Promise((resolve) => { this._resolve = resolve; });
        },

        answer(result) {
            if (!this.open) return;
            this.open = false;
            unlockScroll();
            const back = this._returnTo;
            this._returnTo = null;
            setTimeout(() => { if (back && document.contains(back)) back.focus?.(); }, 30);
            const r = this._resolve;
            this._resolve = null;
            r?.(result);
        },
    });

    window.confirmDialog = (opts) => Alpine.store('confirm').ask(typeof opts === 'string' ? { title: opts } : opts);
    Alpine.magic('confirm', () => window.confirmDialog);

    // Deklarativ: data-confirm an Formularen, Buttons und Links
    const optsFrom = (el) => ({
        title: el.dataset.confirm,
        text: el.dataset.confirmText || '',
        confirmLabel: el.dataset.confirmLabel || (el.hasAttribute('data-confirm-danger') ? 'Löschen' : 'Bestätigen'),
        danger: el.hasAttribute('data-confirm-danger'),
    });

    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form._confirmed) return;
        e.preventDefault();
        const submitter = e.submitter;
        if (await window.confirmDialog(optsFrom(form))) {
            form._confirmed = true;
            // requestSubmit fehlt auf iOS < 16 - dann klassisch absenden
            if (form.requestSubmit) form.requestSubmit(submitter || undefined);
            else form.submit();
            form._confirmed = false;
        }
    }, true);

    document.addEventListener('click', async (e) => {
        const el = e.target.closest('a[data-confirm], button[data-confirm]');
        if (!el || el._confirmed) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!(await window.confirmDialog(optsFrom(el)))) return;
        el._confirmed = true;
        el.click();
        el._confirmed = false;
    }, true);
}

/**
 * Alpine-Baustein hinter <x-ui.dialog>.
 *
 * - Oeffnen:   $dispatch('open-dialog', 'name')  oder  window.openDialog('name')
 * - Schliessen: Escape (nur der oberste Dialog), Hintergrund, Schliessen-Knopf,
 *              $dispatch('close-dialog', 'name')
 * - Fokus:     x-trap haelt ihn im Dialog und gibt ihn danach an den Ausloeser
 *              zurueck. Erstes Ziel: [data-autofocus], sonst erstes Feld.
 * - guard:     Bei geaenderten Eingaben vor dem Schliessen nachfragen.
 *
 * Bewusst kein natives <dialog>: iOS 15.0–15.3 kennt es nicht
 * (docs/frontend-audit.md, 8.1).
 */
import { lockScroll, unlockScroll } from './scroll-lock';

export default function uiDialog({ name, show = false, guard = false } = {}) {
    return {
        name,
        open: false,
        dirty: false,

        init() {
            if (show) this.$nextTick(() => this.show());
            this.$watch('open', (isOpen) => (isOpen ? lockScroll() : unlockScroll()));
        },

        destroy() {
            if (this.open) unlockScroll();
        },

        show() {
            this.dirty = false;
            this.open = true;
            // x-trap setzt den Fokus aufs erste Element (oft "Schliessen") -
            // ein markiertes Feld hat Vorrang
            setTimeout(() => this.$root.querySelector('[data-autofocus]')?.focus(), 60);
        },

        async close(force = false) {
            if (!this.open) return;
            if (guard && this.dirty && !force) {
                const leave = await window.confirmDialog({
                    title: 'Änderungen verwerfen?',
                    text: 'Du hast Eingaben gemacht, die noch nicht gespeichert sind.',
                    confirmLabel: 'Verwerfen',
                    cancelLabel: 'Weiter bearbeiten',
                    danger: true,
                });
                if (!leave) return;
            }
            this.open = false;
        },

        // Aenderungen im Dialog merken (fuer guard)
        markDirty() {
            this.dirty = true;
        },

        onOpenEvent(e) {
            if (e.detail === this.name) this.show();
        },

        onCloseEvent(e) {
            if (e.detail === this.name) this.close(true);
        },
    };
}

window.openDialog = (name) => window.dispatchEvent(new CustomEvent('open-dialog', { detail: name }));
window.closeDialog = (name) => window.dispatchEvent(new CustomEvent('close-dialog', { detail: name }));

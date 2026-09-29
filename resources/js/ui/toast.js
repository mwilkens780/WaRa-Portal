/**
 * Kurze Rueckmeldungen ("Gespeichert"), angekuendigt fuer Screenreader.
 *
 *   JS:   $toast('Gespeichert')  /  $toast('Fehler', { type: 'error' })
 *   Mit Rueckgaengig:  $toast('Block gelöscht', { action: { label: 'Rückgängig', run: () => ... } })
 *
 * Fehler bleiben stehen, bis man sie schliesst; alles andere verschwindet
 * nach einigen Sekunden (mit Aktion etwas laenger).
 */
export function registerToast(Alpine) {
    let nextId = 1;

    Alpine.store('toasts', {
        items: [],

        push(message, { type = 'success', timeout = null, action = null } = {}) {
            const id = nextId++;
            this.items.push({ id, message, type, action });
            const ms = timeout ?? (type === 'error' ? 0 : action ? 8000 : 5000);
            if (ms > 0) setTimeout(() => this.dismiss(id), ms);
            return id;
        },

        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },

        runAction(t) {
            t.action?.run?.();
            this.dismiss(t.id);
        },
    });

    window.toast = (message, opts) => Alpine.store('toasts').push(message, opts);
    Alpine.magic('toast', () => window.toast);
}

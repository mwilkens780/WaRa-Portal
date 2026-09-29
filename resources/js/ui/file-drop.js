/**
 * Alpine-Baustein hinter <x-ui.file-drop>.
 *
 * - Datei per Klick/Tastatur (natives Feld, nur optisch versteckt) oder per
 *   Ziehen und Ablegen waehlen
 * - Dateityp und -groesse schon im Browser pruefen - mit denselben Grenzen
 *   wie der Server, damit niemand 20 MB hochlaedt, um dann "falsches Format"
 *   zu lesen
 * - Ein ungueltige Datei sperrt das Absenden (setCustomValidity), die
 *   Meldung steht am Feld
 */
export default function uiFileDrop({ accept = '', maxMb = 0 } = {}) {
    const endungen = accept
        .split(',')
        .map((e) => e.trim().toLowerCase())
        .filter((e) => e.startsWith('.'));

    return {
        file: null,
        error: '',
        over: false,

        get sizeLabel() {
            if (!this.file) return '';
            const kb = this.file.size / 1024;
            return kb < 1024 ? `${Math.max(1, Math.round(kb))} KB` : `${(kb / 1024).toFixed(1).replace('.', ',')} MB`;
        },

        choose() {
            this.$refs.input.click();
        },

        onChange() {
            this.take(this.$refs.input.files[0] ?? null);
        },

        onDrop(e) {
            this.over = false;
            const f = e.dataTransfer?.files?.[0];
            if (!f) return;
            // Abgelegte Datei ins echte Feld uebernehmen, damit das Formular sie sendet
            try {
                const dt = new DataTransfer();
                dt.items.add(f);
                this.$refs.input.files = dt.files;
            } catch (err) {
                this.error = 'Ablegen wird von diesem Browser nicht unterstützt. Bitte „Datei auswählen“ nutzen.';
                return;
            }
            this.take(f);
        },

        clear() {
            this.$refs.input.value = '';
            this.take(null);
            this.$nextTick(() => this.$refs.input.focus());
        },

        take(f) {
            this.file = f;
            this.error = '';
            if (f) {
                const name = f.name.toLowerCase();
                if (endungen.length && !endungen.some((e) => name.endsWith(e))) {
                    this.error = `Dieser Dateityp passt nicht. Erlaubt: ${endungen.join(', ')}`;
                } else if (maxMb && f.size > maxMb * 1024 * 1024) {
                    this.error = `Die Datei ist ${this.sizeLabel} groß – erlaubt sind höchstens ${maxMb} MB.`;
                }
            }
            this.$refs.input.setCustomValidity(this.error);
            this.$dispatch('file-change', { file: f, valid: !!f && !this.error });
        },
    };
}

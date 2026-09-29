/**
 * Alpine-Baustein fuer formatierten Text (Tiptap).
 *
 * Blade: <x-ui.rich-text-editor name="..." :value="..." />
 *
 * Tiptap wird erst geladen, wenn ein Editor auf der Seite ist (eigener
 * Chunk). Die Editor-Instanz lebt bewusst ausserhalb des Alpine-Zustands:
 * Alpine wuerde sie in einen Proxy packen, und das vertraegt ProseMirror nicht.
 *
 * Der Editor erzeugt nur, was App\Support\RichText serverseitig erlaubt:
 * Absaetze, Ueberschriften 2/3, fett/kursiv/unterstrichen, Listen, Zitat,
 * Links. Der Server bereinigt trotzdem immer - der Editor ist keine Grenze.
 *
 * Von aussen:  Alpine.$data(el).getHTML(), .setHTML(html), .appendText(text)
 * Ereignis:    'rich-text-change' mit detail.html bei jeder Aenderung
 */
export default function richTextEditor({ value = '', placeholder = '' } = {}) {
    let editor = null;

    return {
        ready: false,
        active: {},
        html: value,

        async init() {
            const [{ Editor }, { default: StarterKit }, { Placeholder }] = await Promise.all([
                import('@tiptap/core'),
                import('@tiptap/starter-kit'),
                import('@tiptap/extensions'),
            ]);

            editor = new Editor({
                element: this.$refs.editor,
                content: value || '',
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3] },
                        code: false,
                        codeBlock: false,
                        horizontalRule: false,
                        strike: false,
                        link: {
                            openOnClick: false,
                            autolink: true,
                            protocols: ['http', 'https', 'mailto'],
                            defaultProtocol: 'https',
                        },
                    }),
                    Placeholder.configure({ placeholder }),
                ],
                editorProps: {
                    attributes: {
                        class: 'rich-text min-h-[16rem] px-4 py-3 focus:outline-none',
                        role: 'textbox',
                        'aria-multiline': 'true',
                        ...(this.$el.dataset.label ? { 'aria-label': this.$el.dataset.label } : {}),
                    },
                },
                onUpdate: () => this.sync(),
                onSelectionUpdate: () => this.refreshActive(),
                onTransaction: () => this.refreshActive(),
            });

            this.sync();
            this.ready = true;
        },

        destroy() {
            editor?.destroy();
            editor = null;
        },

        sync() {
            this.html = editor.isEmpty ? '' : editor.getHTML();
            this.refreshActive();
            this.$dispatch('rich-text-change', { html: this.html });
        },

        refreshActive() {
            if (!editor) return;
            this.active = {
                bold: editor.isActive('bold'),
                italic: editor.isActive('italic'),
                underline: editor.isActive('underline'),
                h2: editor.isActive('heading', { level: 2 }),
                h3: editor.isActive('heading', { level: 3 }),
                bulletList: editor.isActive('bulletList'),
                orderedList: editor.isActive('orderedList'),
                blockquote: editor.isActive('blockquote'),
                link: editor.isActive('link'),
            };
        },

        // ── Werkzeugleiste ────────────────────────────────────────────
        run(command) {
            if (!editor) return;
            const chain = editor.chain().focus();
            ({
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                underline: () => chain.toggleUnderline(),
                h2: () => chain.toggleHeading({ level: 2 }),
                h3: () => chain.toggleHeading({ level: 3 }),
                bulletList: () => chain.toggleBulletList(),
                orderedList: () => chain.toggleOrderedList(),
                blockquote: () => chain.toggleBlockquote(),
                clear: () => chain.unsetAllMarks().clearNodes(),
                undo: () => chain.undo(),
                redo: () => chain.redo(),
            })[command]().run();
        },

        async toggleLink() {
            if (!editor) return;
            if (editor.isActive('link')) {
                editor.chain().focus().unsetLink().run();
                return;
            }
            const url = await window.promptDialog({ title: 'Link einfügen', label: 'Adresse (https://… oder mailto:…)', value: 'https://' });
            if (!url || url === 'https://') return;
            if (!/^(https?:\/\/|mailto:)/i.test(url)) return;
            editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        },

        // ── API fuer umgebende Komponenten ───────────────────────────
        getHTML() {
            return editor && !editor.isEmpty ? editor.getHTML() : '';
        },

        setHTML(html) {
            editor?.commands.setContent(html || '');
            this.sync();
        },

        /** Klartext (z. B. KI-Vorschlag) als Absaetze anhaengen - immer escaped */
        appendText(text) {
            if (!editor || !text) return;
            const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const html = String(text).trim().split(/\n{2,}/)
                .map(p => '<p>' + esc(p.trim()).replace(/\n/g, '<br>') + '</p>')
                .join('');
            editor.chain().focus('end').insertContent(html).run();
        },
    };
}

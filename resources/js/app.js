/**
 * Einstieg fuer alle Seiten.
 *
 * Alpine kommt aus dem Build statt vom CDN (feste Version ueber package.json).
 * Das Focus-Plugin (x-trap) ist die Grundlage der Dialoge - bewusst statt
 * nativem <dialog>, weil iOS 15 unterstuetzt wird.
 *
 * Views definieren ihre Komponenten teils noch als globale Funktionen
 * (function hallApp() ...) oder ueber 'alpine:init'. Beides funktioniert
 * weiter: Dieses Modul laeuft erst nach dem Parsen der Seite, also nachdem
 * alle Inline-Skripte ausgefuehrt sind - wie vorher das defer-Skript.
 *
 * Gemeinsame Bausteine (docs/frontend-audit.md, 5.2):
 *   $confirm(...)  statt window.confirm()      ui/confirm.js
 *   $toast(...)    kurze Rueckmeldungen          ui/toast.js
 *   api(...)       JSON-Requests mit Fehlertext  ui/api.js
 *   uiDialog       hinter <x-ui.dialog>          ui/dialog.js
 */
import './polyfills';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import './ui/api';
import './ui/scroll-lock';
import './ui/scroll-regions';
import uiDialog from './ui/dialog';
import uiFileDrop from './ui/file-drop';
import { registerConfirm } from './ui/confirm';
import { registerToast } from './ui/toast';
import richTextEditor from './components/rich-text-editor';

Alpine.plugin(focus);

registerConfirm(Alpine);
registerToast(Alpine);
Alpine.data('uiDialog', uiDialog);
Alpine.data('uiFileDrop', uiFileDrop);
// Tiptap selbst laedt erst bei Bedarf nach
Alpine.data('richTextEditor', richTextEditor);

window.Alpine = Alpine;
Alpine.start();

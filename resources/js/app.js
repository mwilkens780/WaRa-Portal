/**
 * Einstieg fuer alle Seiten.
 *
 * Alpine kommt aus dem Build statt vom CDN (feste Version ueber package.json).
 * Das Focus-Plugin (x-trap) ist die Grundlage fuer barrierefreie Dialoge ab
 * Phase 2 - bewusst statt nativem <dialog>, weil iOS 15 unterstuetzt wird.
 *
 * Views definieren ihre Komponenten noch als globale Funktionen
 * (function hallApp() ...) oder ueber 'alpine:init'. Beides funktioniert
 * weiter: Dieses Modul laeuft erst nach dem Parsen der Seite, also nachdem
 * alle Inline-Skripte ausgefuehrt sind - wie vorher das defer-Skript.
 */
import './polyfills';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import richTextEditor from './components/rich-text-editor';

Alpine.plugin(focus);

// Wiederverwendbare Bausteine (Tiptap selbst laedt erst bei Bedarf nach)
Alpine.data('richTextEditor', richTextEditor);

window.Alpine = Alpine;
Alpine.start();

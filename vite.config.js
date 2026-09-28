import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Seitenspezifisches (Tiptap) laedt app.js per dynamischem Import nach
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // Aeltere iPhones/iPads sind im Einsatz: Untergrenze iOS/iPadOS 15
        // (docs/frontend-audit.md, Kap. 8.1). Safari 14 als Ziel laesst Luft.
        target: ['es2020', 'safari14'],
        cssTarget: ['safari14'],
    },
});

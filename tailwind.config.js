/**
 * Tailwind-Build fuer das Portal (ersetzt das Play-CDN).
 *
 * WICHTIG: Der Build erzeugt nur Klassen, die er in den Dateien unter
 * `content` als ganzes Wort findet. Klassen nie zusammensetzen
 * ("bg-{{ $farbe }}-50"), sondern immer ausschreiben - sonst fehlen sie
 * in Produktion ohne jede Fehlermeldung.
 *
 * Die Farbwerte entsprechen 1:1 der frueheren CDN-Konfiguration. Die
 * Bereinigung der Skala (Kontrast, gleichmaessige Abstufung) folgt in
 * Phase 2 zusammen mit den Bausteinen, siehe docs/frontend-audit.md.
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        // Klassen, die Controller und Models als Strings liefern (Farben, Status-Badges)
        './app/**/*.php',
        './config/**/*.php',
        // Laravels Seiten-Navigation ($items->links()) kommt aus vendor/
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php', // Rueckfall; eigene Kopie unter resources/views/vendor/pagination
    ],
    theme: {
        extend: {
            colors: {
                // gray-400 war mit 2,5:1 der haeufigste Kontrastfehler (Nebentexte).
                // Jetzt 4,6:1 auf Weiss und Seitenhintergrund (WCAG AA).
                // Fuer rein Dekoratives (Rahmen, Trenner) gray-200/300 nehmen.
                gray: { 400: '#6c7381' },
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#1B5EAB',
                    700: '#1d4ed8',
                    800: '#0D3F7A',
                    900: '#1e3a5f',
                    DEFAULT: '#1B5EAB',
                    dark: '#0D3F7A',
                },
                accent: {
                    DEFAULT: '#C0392B',
                    dark: '#992d22',
                    light: '#e74c3c',
                },
            },
        },
    },
    plugins: [],
};

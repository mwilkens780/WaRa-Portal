<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Formatierter Text aus dem Editor (Wettkampf-Auswertung u. a.).
 *
 * Gespeichert wird HTML - so geht es ohne Umwandlung in Druckansicht, PDF und
 * Mail. Was der Browser schickt, ist aber Nutzereingabe: ohne Bereinigung
 * koennte jedes Konto mit Schreibrecht Skript in die Seiten anderer bringen.
 * Deshalb laeuft jeder Text beim Speichern UND bei der Ausgabe hier durch.
 *
 * Erlaubt ist nur, was der Editor erzeugen soll: Absaetze, Ueberschriften,
 * Hervorhebungen, Listen, Zitate, Links. Keine Stile, Klassen, Bilder oder
 * Skripte.
 */
class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Aeltere Eintraege sind reiner Text: Zeilenumbrueche als Absaetze erhalten
        if ($html === strip_tags($html)) {
            $html = collect(preg_split("/\R{2,}/", trim($html)))
                ->map(fn($absatz) => '<p>' . nl2br(e($absatz), false) . '</p>')
                ->implode('');
        }

        return self::sanitizer()->sanitize($html);
    }

    /** Leer, auch wenn nur leere Absaetze uebrig sind (Editor liefert "<p><br></p>") */
    public static function isEmpty(?string $html): bool
    {
        return trim(strip_tags(self::sanitize($html))) === '';
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(200_000);

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'h4',
                  'ul', 'ol', 'li', 'blockquote'] as $tag) {
            $config = $config->allowElement($tag);
        }
        $config = $config->allowElement('a', ['href']);

        // Huellen ohne eigene Bedeutung (Quill: Farben, Schriften): Tag weg, Text bleibt
        foreach (['span', 'div', 'font', 'sub', 'sup', 'code', 'pre', 'section', 'article'] as $tag) {
            $config = $config->blockElement($tag);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}

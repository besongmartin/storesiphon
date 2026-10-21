<?php

namespace App\Domain\Extraction\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Text and HTML hygiene for extracted content.
 *
 * sanitize() keeps a small allow-list of formatting tags and safe http(s) links; everything else is
 * unwrapped (text kept) or dropped (script/style/iframe/forms). The result is safe to render.
 */
final class Html
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'ul' => [], 'ol' => [], 'li' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'blockquote' => [], 'table' => [], 'thead' => [],
        'tbody' => [], 'tr' => [], 'th' => [], 'td' => [], 'a' => ['href'], 'span' => [], 'sub' => [], 'sup' => [],
    ];

    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'template', 'noscript'];

    /** Decode entities and collapse whitespace for single-line values (names, SKUs, terms). */
    public static function decode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }

    /** Readable plain text from HTML: block elements become line breaks. */
    public static function toText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<\s*(br|/p|/li|/h[1-6]|/tr|/div)\b[^>]*>#i', "\n", $html) ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? '';
        $text = preg_replace("/ *\n[ \n]*/", "\n", $text) ?? '';

        return trim($text) === '' ? null : trim($text);
    }

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ss-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('ss-root');
        if (! $root) {
            return self::toText($html);
        }
        self::clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = trim(preg_replace('/\n{3,}/', "\n\n", $out) ?? '');

        return $out === '' ? null : $out;
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, self::DROP, true)) {
                    $node->removeChild($child);

                    continue;
                }
                self::clean($child);
                if (! array_key_exists($tag, self::ALLOWED)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    if (! in_array($attr->name, self::ALLOWED[$tag], true)) {
                        $child->removeAttribute($attr->name);
                    }
                }
                if ($tag === 'a') {
                    $href = trim($child->getAttribute('href'));
                    if (! preg_match('#^https?://#i', $href)) {
                        $child->removeAttribute('href');
                    } else {
                        $child->setAttribute('rel', 'nofollow noopener');
                    }
                }
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }
}

<?php

namespace App\Domain\Extraction\Parsing;

use XMLReader;

/**
 * Streaming sitemap reader. External entities and network access are disabled (no XXE).
 */
final class SitemapParser
{
    /**
     * @return array{type: 'index'|'urlset'|'unknown', locs: list<string>}
     */
    public static function parse(string $xml, int $limit = 50000): array
    {
        $xml = ltrim($xml, "\xEF\xBB\xBF \t\r\n");
        if ($xml === '' || ! str_starts_with($xml, '<')) {
            return ['type' => 'unknown', 'locs' => []];
        }
        // Refuse DTDs outright; sitemaps never need them.
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return ['type' => 'unknown', 'locs' => []];
        }

        $reader = new XMLReader;
        $previous = libxml_use_internal_errors(true);
        if (! $reader->XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT)) {
            libxml_use_internal_errors($previous);

            return ['type' => 'unknown', 'locs' => []];
        }

        $type = 'unknown';
        $locs = [];
        while (@$reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            $name = $reader->localName;
            if ($type === 'unknown' && ($name === 'sitemapindex' || $name === 'urlset')) {
                $type = $name === 'sitemapindex' ? 'index' : 'urlset';
            } elseif ($name === 'loc') {
                $loc = trim($reader->readString());
                if ($loc !== '' && preg_match('#^https?://#i', $loc)) {
                    $locs[] = html_entity_decode($loc, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if (count($locs) >= $limit) {
                        break;
                    }
                }
            }
        }
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return ['type' => $type, 'locs' => array_values(array_unique($locs))];
    }
}

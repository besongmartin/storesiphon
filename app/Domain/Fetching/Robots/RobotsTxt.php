<?php

namespace App\Domain\Fetching\Robots;

/**
 * Parsed robots.txt rules for one user agent (RFC 9309 semantics).
 *
 * Group selection: the group whose user-agent token matches ours (case-insensitive product token),
 * otherwise the "*" group. Matching: the longest matching path pattern wins; on a tie Allow wins.
 * Patterns support "*" (any sequence) and a trailing "$" (end of URL).
 */
final class RobotsTxt
{
    /**
     * @param  list<array{allow: bool, pattern: string}>  $rules
     */
    private function __construct(
        private readonly array $rules,
        public readonly ?float $crawlDelay,
        public readonly bool $disallowAll = false,
        /** @var list<string> */
        public readonly array $sitemaps = [],
        /** True when this "disallow all" comes from robots.txt being unreachable, not from the owner's rules. */
        public readonly bool $unreachable = false,
    ) {}

    public static function allowAll(): self
    {
        return new self([], null);
    }

    public static function disallowAll(bool $unreachable = false): self
    {
        return new self([], null, true, [], $unreachable);
    }

    public static function parse(string $content, string $agent): self
    {
        $agent = strtolower($agent);
        $groups = [];          // list of ['agents' => [...], 'rules' => [...], 'delay' => ?float]
        $sitemaps = [];
        $current = null;
        $lastWasAgent = false;

        // Only the first 500 KiB are considered (RFC 9309 §2.5).
        $content = substr($content, 0, 512 * 1024);

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'sitemap') {
                $sitemaps[] = $value;

                continue;
            }

            if ($field === 'user-agent') {
                if (! $lastWasAgent) {
                    $groups[] = ['agents' => [], 'rules' => [], 'delay' => null];
                    $current = array_key_last($groups);
                }
                $groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }

            $lastWasAgent = false;
            if ($current === null) {
                continue;
            }

            if ($field === 'allow' || $field === 'disallow') {
                if ($value === '' && $field === 'disallow') {
                    continue; // "Disallow:" with no value allows everything
                }
                $groups[$current]['rules'][] = ['allow' => $field === 'allow', 'pattern' => $value];
            } elseif ($field === 'crawl-delay' && is_numeric($value) && (float) $value >= 0) {
                $groups[$current]['delay'] = (float) $value;
            }
        }

        $chosen = self::selectGroups($groups, $agent);
        $rules = array_merge(...array_map(fn ($g) => $g['rules'], $chosen ?: [['rules' => []]]));
        $delays = array_filter(array_map(fn ($g) => $g['delay'], $chosen), fn ($d) => $d !== null);

        return new self(array_values($rules), $delays ? max($delays) : null, false, array_values(array_unique($sitemaps)));
    }

    /** @return list<array{agents: list<string>, rules: list<array{allow: bool, pattern: string}>, delay: ?float}> */
    private static function selectGroups(array $groups, string $agent): array
    {
        $specific = array_values(array_filter($groups, function ($g) use ($agent) {
            foreach ($g['agents'] as $a) {
                if ($a !== '*' && $a !== '' && str_starts_with($agent, $a)) {
                    return true;
                }
            }

            return false;
        }));
        if ($specific) {
            return $specific;
        }

        return array_values(array_filter($groups, fn ($g) => in_array('*', $g['agents'], true)));
    }

    public function isAllowed(string $pathAndQuery): bool
    {
        if ($this->disallowAll) {
            return false;
        }
        if ($pathAndQuery === '' || $pathAndQuery[0] !== '/') {
            $pathAndQuery = '/'.$pathAndQuery;
        }
        if (strcasecmp($pathAndQuery, '/robots.txt') === 0) {
            return true;
        }

        $best = null;
        foreach ($this->rules as $rule) {
            $length = self::matchLength($rule['pattern'], $pathAndQuery);
            if ($length === null) {
                continue;
            }
            if ($best === null || $length > $best['length'] || ($length === $best['length'] && $rule['allow'])) {
                $best = ['length' => $length, 'allow' => $rule['allow']];
            }
        }

        return $best === null || $best['allow'];
    }

    /** Length of the pattern if it matches the path (used for longest-match precedence), else null. */
    private static function matchLength(string $pattern, string $path): ?int
    {
        $anchored = str_ends_with($pattern, '$');
        $body = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.implode('.*', array_map(fn ($p) => preg_quote(self::decode($p), '#'), explode('*', $body))).($anchored ? '$' : '').'#';

        return preg_match($regex, self::decode($path)) ? strlen($pattern) : null;
    }

    /** Compare in a canonical form: decode unreserved percent-escapes only. */
    private static function decode(string $value): string
    {
        return preg_replace_callback('/%([0-9A-Fa-f]{2})/', function ($m) {
            $char = chr(hexdec($m[1]));

            return preg_match('/[A-Za-z0-9\-._~]/', $char) ? $char : '%'.strtoupper($m[1]);
        }, $value);
    }
}

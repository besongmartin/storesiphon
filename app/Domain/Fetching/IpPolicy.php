<?php

namespace App\Domain\Fetching;

/**
 * Decides whether an IP address is a public, routable unicast address that StoreSiphon may connect to.
 *
 * Everything else (loopback, private, link-local incl. cloud metadata 169.254.169.254, CGNAT, multicast,
 * reserved, documentation and benchmarking ranges, IPv6 ULA/link-local) is refused. IPv6 forms that embed
 * an IPv4 address (mapped, compatible, NAT64, 6to4) are judged by the embedded IPv4 address.
 */
final class IpPolicy
{
    /** @var list<string> */
    private const BLOCKED_V4 = [
        '0.0.0.0/8',          // "this network"
        '10.0.0.0/8',         // private
        '100.64.0.0/10',      // carrier-grade NAT
        '127.0.0.0/8',        // loopback
        '169.254.0.0/16',     // link-local, cloud metadata
        '172.16.0.0/12',      // private
        '192.0.0.0/24',       // IETF protocol assignments
        '192.0.2.0/24',       // TEST-NET-1
        '192.88.99.0/24',     // 6to4 relay anycast
        '192.168.0.0/16',     // private
        '198.18.0.0/15',      // benchmarking
        '198.51.100.0/24',    // TEST-NET-2
        '203.0.113.0/24',     // TEST-NET-3
        '224.0.0.0/4',        // multicast
        '240.0.0.0/4',        // reserved + broadcast
    ];

    /** @var list<string> */
    private const BLOCKED_V6 = [
        '::/128',             // unspecified
        '::1/128',            // loopback
        '100::/64',           // discard
        '2001::/23',          // IETF protocol assignments (incl. Teredo 2001::/32)
        '2001:db8::/32',      // documentation
        'fc00::/7',           // unique local
        'fe80::/10',          // link-local
        'fec0::/10',          // site-local (deprecated)
        'ff00::/8',           // multicast
    ];

    public static function isPublic(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            return ! self::matchesAny($packed, self::BLOCKED_V4);
        }

        $embedded = self::embeddedIpv4($packed);
        if ($embedded !== null) {
            return self::isPublic($embedded);
        }

        // Only global unicast (2000::/3) is acceptable for IPv6.
        if ((ord($packed[0]) & 0xE0) !== 0x20) {
            return false;
        }

        return ! self::matchesAny($packed, self::BLOCKED_V6);
    }

    /**
     * IPv4 address carried inside an IPv6 address, if any:
     * ::ffff:a.b.c.d (mapped), ::a.b.c.d (compatible), 64:ff9b::/96 (NAT64), 2002:AABB:CCDD::/48 (6to4).
     */
    private static function embeddedIpv4(string $packed): ?string
    {
        $prefix10 = substr($packed, 0, 10);
        $last4 = substr($packed, 12, 4);

        if ($prefix10 === str_repeat("\0", 10) && in_array(substr($packed, 10, 2), ["\xff\xff", "\0\0"], true)) {
            // ::ffff:x.x.x.x or ::x.x.x.x (but not :: and ::1, which are handled as IPv6 below)
            if (substr($packed, 10, 2) === "\0\0" && substr($last4, 0, 3) === "\0\0\0") {
                return null;
            }

            return inet_ntop($last4) ?: null;
        }

        if (substr($packed, 0, 12) === "\x00\x64\xff\x9b".str_repeat("\0", 8)) {
            return inet_ntop($last4) ?: null;
        }

        if (substr($packed, 0, 2) === "\x20\x02") {
            return inet_ntop(substr($packed, 2, 4)) ?: null;
        }

        return null;
    }

    /** @param list<string> $cidrs */
    private static function matchesAny(string $packed, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $netPacked = inet_pton($net);
            if ($netPacked === false || strlen($netPacked) !== strlen($packed)) {
                continue;
            }
            if (self::prefixEquals($packed, $netPacked, (int) $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function prefixEquals(string $a, string $b, int $bits): bool
    {
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;

        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }
}

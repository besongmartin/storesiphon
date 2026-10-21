<?php

namespace App\Domain\Fetching\Dns;

/**
 * Resolves through the operating system's resolver. Follows CNAMEs (dns_get_record returns the final
 * A/AAAA records), and falls back to gethostbynamel() where AAAA lookups are unsupported.
 */
final class SystemDnsResolver implements DnsResolver
{
    public function resolve(string $host): array
    {
        $ips = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }

        if ($ips === []) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        return array_values(array_unique($ips));
    }
}

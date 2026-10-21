<?php

namespace App\Domain\Fetching\Dns;

interface DnsResolver
{
    /**
     * Resolve a hostname to all of its A and AAAA addresses.
     *
     * @return list<string> IP addresses; empty when the name does not resolve
     */
    public function resolve(string $host): array;
}

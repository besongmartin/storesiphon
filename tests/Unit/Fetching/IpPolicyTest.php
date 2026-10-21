<?php

use App\Domain\Fetching\IpPolicy;

it('refuses non-public addresses', function (string $ip) {
    expect(IpPolicy::isPublic($ip))->toBeFalse();
})->with([
    'loopback' => '127.0.0.1',
    'loopback high' => '127.255.255.254',
    'this network' => '0.0.0.0',
    'private 10/8' => '10.1.2.3',
    'private 172.16/12' => '172.31.255.255',
    'private 192.168/16' => '192.168.0.10',
    'cloud metadata' => '169.254.169.254',
    'CGNAT' => '100.64.12.1',
    'TEST-NET-1' => '192.0.2.10',
    'benchmarking' => '198.18.0.1',
    'multicast' => '224.0.0.1',
    'broadcast' => '255.255.255.255',
    'v6 unspecified' => '::',
    'v6 loopback' => '::1',
    'v6 unique local' => 'fd12:3456::1',
    'v6 link local' => 'fe80::1',
    'v6 multicast' => 'ff02::1',
    'v6 documentation' => '2001:db8::1',
    'v4-mapped loopback' => '::ffff:127.0.0.1',
    'v4-mapped metadata' => '::ffff:169.254.169.254',
    'v4-compatible private' => '::10.0.0.1',
    'NAT64 metadata' => '64:ff9b::a9fe:a9fe',
    '6to4 loopback' => '2002:7f00:1::1',
    'garbage' => 'not-an-ip',
    'empty' => '',
]);

it('allows public unicast addresses', function (string $ip) {
    expect(IpPolicy::isPublic($ip))->toBeTrue();
})->with([
    '93.184.216.34',
    '8.8.8.8',
    '172.15.255.255',
    '172.32.0.1',
    '100.128.0.1',
    '2606:4700:4700::1111',
    '2a00:1450:4001:80b::200e',
    '::ffff:8.8.8.8',
]);

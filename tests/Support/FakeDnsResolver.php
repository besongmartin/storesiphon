<?php

namespace Tests\Support;

use App\Domain\Fetching\Dns\DnsResolver;

/**
 * Test double: map hostnames to fixed answers. Answers can be a list per call to simulate
 * DNS rebinding (first lookup public, later lookups private).
 */
final class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, list<list<string>>> */
    private array $answers = [];

    /** @var list<string> */
    public array $lookups = [];

    /** @param list<string> ...$answerSets one set per successive lookup; the last set repeats */
    public function set(string $host, array ...$answerSets): self
    {
        $this->answers[strtolower($host)] = $answerSets;

        return $this;
    }

    public function resolve(string $host): array
    {
        $host = strtolower($host);
        $this->lookups[] = $host;
        $sets = $this->answers[$host] ?? [[]];
        $answer = count($sets) > 1 ? array_shift($sets) : $sets[0];
        $this->answers[$host] = $sets;

        return $answer;
    }
}

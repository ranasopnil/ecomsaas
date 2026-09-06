<?php

namespace App\Services\Domains;

/**
 * Asks the internet where a hostname points.
 *
 * Kept behind its own small class so tests can answer for it without going
 * anywhere near a real name server.
 */
class DnsResolver
{
    /**
     * @return array<int, string>
     */
    public function addressesFor(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? null,
            $records,
        )));
    }
}

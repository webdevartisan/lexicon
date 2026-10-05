<?php

declare(strict_types=1);

namespace App\Services\Traffic;

/**
 * Whether an IP address belongs to a hosting provider, from the DB-IP Lite ASN
 * file. People read from homes, offices and phones; a page view from a server
 * farm is a script, whatever its user agent says.
 */
class NetworkLookup extends MmdbLookup
{
    /**
     * @param  list<string>  $hostingNetworks  Lower-case substrings of hosting providers' network names
     */
    public function __construct(string $databasePath, private array $hostingNetworks)
    {
        parent::__construct($databasePath);
    }

    public function isHosting(string $ip): bool
    {
        $organisation = $this->record($ip)['autonomous_system_organization'] ?? null;

        if (!is_string($organisation) || $organisation === '') {
            return false;
        }

        $organisation = strtolower($organisation);

        foreach ($this->hostingNetworks as $network) {
            if (str_contains($organisation, $network)) {
                return true;
            }
        }

        return false;
    }
}

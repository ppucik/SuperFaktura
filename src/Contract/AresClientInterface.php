<?php

declare(strict_types=1);

namespace SuperFaktura\Contract;

use SuperFaktura\Exception\AresConnectionException;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\AresNotFoundException;

/**
 * Contract for fetching raw company data from ARES API.
 * Depend on this interface, not on the concrete AresClient implementation.
 */
interface AresClientInterface
{
    /**
     * Fetch raw JSON data from ARES for a given IČO.
     *
     * @throws AresNotFoundException   If the company is not found (HTTP 404)
     * @throws AresConnectionException If the request fails due to network/timeout
     * @throws AresException           For any other unexpected API error
     */
    public function fetchByIco(string $ico): array;
}

<?php

declare(strict_types=1);

namespace SuperFaktura\Contract;

use SuperFaktura\DTO\CompanyData;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\InvalidIcoException;

/**
 * Contract for looking up companies in the ARES register.
 * Depend on this interface instead of the concrete AresService,
 * which makes mocking trivial in integration tests.
 */
interface AresServiceInterface
{
    /**
     * Look up a single company by IČO.
     *
     * @throws InvalidIcoException If the IČO format/checksum is invalid
     * @throws AresException       If ARES cannot be reached or returns an error
     */
    public function getByIco(string $ico): CompanyData;

    /**
     * Look up multiple companies at once.
     * Returns a keyed array [ico => CompanyData|AresException].
     * Failed lookups are stored as exceptions — they never interrupt the batch.
     *
     * @param  string[]                              $icos
     * @return array<string, CompanyData|AresException>
     */
    public function getByIcoMultiple(array $icos): array;
}

<?php

declare(strict_types=1);

namespace SuperFaktura;

use SuperFaktura\Contract\AresClientInterface;
use SuperFaktura\DTO\CompanyData;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\InvalidIcoException;

/**
 * Main entry point of the ARES library.
 *
 * Orchestrates validation → HTTP fetching → DTO mapping.
 * Dependencies are injected (Dependency Inversion Principle),
 * making the service fully testable with mocks.
 *
 * Usage:
 *   $service = AresService::create();
 *   $company = $service->getByIco('01569651');
 *   echo $company->name;
 */
class AresService
{
    public function __construct(
        private readonly IcoValidator        $validator,
        private readonly AresClientInterface $client,
    ) {}

    /**
     * Convenience factory with default dependencies.
     */
    public static function create(int $timeoutSeconds = 10): self
    {
        return new self(
            validator: new IcoValidator(),
            client: new AresClient(timeout: $timeoutSeconds),
        );
    }

    /**
     * Look up a company by IČO and return a structured DTO.
     *
     * @throws InvalidIcoException  If the IČO format/checksum is invalid
     * @throws AresException        If ARES cannot be reached or returns an error
     */
    public function getByIco(string $ico): CompanyData
    {
        $normalizedIco = $this->validator->validate($ico);
        $rawData       = $this->client->fetchByIco($normalizedIco);

        return CompanyData::fromAresResponse($rawData);
    }

    /**
     * Look up multiple companies at once.
     * Returns a keyed array [ico => CompanyData].
     * Failed lookups are stored as exceptions under their IČO key.
     *
     * @param  string[]                           $icos
     * @return array<string, CompanyData|AresException>
     */
    public function getByIcoMultiple(array $icos): array
    {
        $results = [];

        foreach ($icos as $ico) {
            try {
                $results[$ico] = $this->getByIco($ico);
            } catch (AresException $e) {
                $results[$ico] = $e;
            }
        }

        return $results;
    }
}

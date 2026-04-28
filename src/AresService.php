<?php

declare(strict_types=1);

namespace SuperFaktura;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SuperFaktura\Cache\NullCache;
use SuperFaktura\Contract\AresClientInterface;
use SuperFaktura\Contract\CacheInterface;
use SuperFaktura\DTO\CompanyData;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\InvalidIcoException;

/**
 * Main entry point of the ARES library.
 *
 * Orchestrates validation → cache → HTTP fetching → DTO mapping.
 * All dependencies are injected (Dependency Inversion Principle).
 *
 * Usage (zero config):
 *   $service = AresService::create();
 *   $company = $service->getByIco('01569651');
 *
 * Usage (with cache + logger):
 *   $service = AresService::create(
 *       cache:  new InMemoryCache(),
 *       logger: $myPsrLogger,
 *   );
 */
class AresService
{
    public function __construct(
        private readonly IcoValidator        $validator,
        private readonly AresClientInterface $client,
    ) {}

    /**
     * Convenience factory — wires up the full decorator stack:
     * AresClient → RetryableAresClient (cache + retry + logging)
     */
    public static function create(
        int              $timeoutSeconds = 10,
        ?CacheInterface  $cache          = null,
        ?LoggerInterface $logger         = null,
    ): self {
        $inner = new AresClient(timeout: $timeoutSeconds);

        $retryable = new RetryableAresClient(
            inner: $inner,
            cache: $cache  ?? new NullCache(),
            logger: $logger ?? new NullLogger(),
        );

        return new self(
            validator: new IcoValidator(),
            client: $retryable,
        );
    }

    /**
     * Look up a company by IČO and return a structured DTO.
     *
     * @throws InvalidIcoException If the IČO format/checksum is invalid
     * @throws AresException       If ARES cannot be reached or returns an error
     */
    public function getByIco(string $ico): CompanyData
    {
        $normalizedIco = $this->validator->validate($ico);
        $rawData       = $this->client->fetchByIco($normalizedIco);

        return CompanyData::fromAresResponse($rawData);
    }

    /**
     * Look up multiple companies at once.
     * Returns a keyed array [ico => CompanyData|AresException].
     * Failed lookups are stored as exceptions — they never interrupt the batch.
     *
     * @param  string[]                              $icos
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

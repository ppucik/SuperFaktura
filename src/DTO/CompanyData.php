<?php

declare(strict_types=1);

namespace SuperFaktura\DTO;

/**
 * Immutable Data Transfer Object representing a company from ARES.
 * Using readonly properties ensures immutability (no accidental mutation).
 */
final readonly class CompanyData
{
    public function __construct(
        public string  $ico,
        public string  $name,
        public ?string $dic,
        public ?string $legalForm,
        public ?string $street,
        public ?string $city,
        public ?string $zip,
        public ?string $country,
        public bool    $isActive,
    ) {}

    /**
     * Factory method: build from raw ARES API array response.
     *
     * @param array<string, mixed> $data
     */
    public static function fromAresResponse(array $data): self
    {
        $address = $data['sidlo'] ?? [];

        return new self(
            ico:       (string) ($data['ico'] ?? ''),
            name:      (string) ($data['obchodniJmeno'] ?? $data['jmeno'] ?? ''),
            dic:       isset($data['dic']) ? (string) $data['dic'] : null,
            legalForm: isset($data['pravniForma']) ? (string) $data['pravniForma'] : null,
            street:    self::buildStreet($address),
            city:      isset($address['nazevObce']) ? (string) $address['nazevObce'] : null,
            zip:       isset($address['psc']) ? (string) $address['psc'] : null,
            country:   isset($address['nazevStatu']) ? (string) $address['nazevStatu'] : null,
            isActive:  in_array($data['stavSubjektu'] ?? null, ['AKTIVNI', 'AKTIVNÍ'], true),
        );
    }

    /**
     * Export to a plain associative array (e.g. for JSON encoding or further processing).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ico'        => $this->ico,
            'name'       => $this->name,
            'dic'        => $this->dic,
            'legal_form' => $this->legalForm,
            'address'    => [
                'street'  => $this->street,
                'city'    => $this->city,
                'zip'     => $this->zip,
                'country' => $this->country,
            ],
            'is_active'  => $this->isActive,
        ];
    }

    /**
     * @param array<string, mixed> $address
     */
    private static function buildStreet(array $address): ?string
    {
        $parts = array_filter([
            $address['nazevUlice'] ?? $address['nazevCastiObce'] ?? null,
            isset($address['cisloDomovni']) ? (string) $address['cisloDomovni'] : null,
            isset($address['cisloOrientacni']) ? '/' . $address['cisloOrientacni'] : null,
        ]);

        return $parts ? implode(' ', $parts) : null;
    }
}

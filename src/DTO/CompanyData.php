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
        /** @var array<string, mixed> $address */
        $address = is_array($data['sidlo'] ?? null) ? $data['sidlo'] : [];

        return new self(
            ico: self::stringOrEmpty($data['ico'] ?? null),
            name: self::stringOrEmpty($data['obchodniJmeno'] ?? $data['jmeno'] ?? null),
            dic: self::stringOrNull($data['dic'] ?? null),
            legalForm: self::stringOrNull($data['pravniForma'] ?? null),
            street: self::buildStreet($address),
            city: self::stringOrNull($address['nazevObce'] ?? null),
            zip: self::stringOrNull($address['psc'] ?? null),
            country: self::stringOrNull($address['nazevStatu'] ?? null),
            isActive: self::boolFromStatus($data['stavSubjektu'] ?? null),
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
        $street = $address['nazevUlice'] ?? $address['nazevCastiObce'] ?? null;
        $house  = $address['cisloDomovni'] ?? null;
        $orient = $address['cisloOrientacni'] ?? null;

        if (!is_string($street)) {
            return null;
        }

        $parts = [$street];

        if (is_string($house)) {
            $parts[] = $house;
        }

        if (is_string($orient)) {
            $parts[] = '/' . $orient;
        }

        return implode(' ', $parts);
    }

    /**
     * @param mixed $value
     */
    private static function stringOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @param mixed $value
     */
    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @param mixed $value
     */
    private static function boolFromStatus(mixed $value): bool
    {
        return is_string($value) && in_array($value, ['AKTIVNI', 'AKTIVNÍ'], true);
    }
}

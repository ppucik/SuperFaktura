<?php

declare(strict_types=1);

namespace SuperFaktura;

use SuperFaktura\Exception\InvalidIcoException;

/**
 * Validates Czech IČO (Identifikační číslo osoby).
 * IČO must be exactly 8 digits and pass the official checksum algorithm.
 */
class IcoValidator
{
    private const ICO_LENGTH = 8;

    /**
     * Validate and normalize the IČO (pads to 8 digits if necessary).
     *
     * @throws InvalidIcoException If the IČO is invalid
     */
    public function validate(string $ico): string
    {
        $ico = trim($ico);
        $ico = str_pad($ico, self::ICO_LENGTH, '0', STR_PAD_LEFT);

        if (!ctype_digit($ico)) {
            throw new InvalidIcoException("IČO must contain only digits. Got: '{$ico}'.");
        }

        if (strlen($ico) !== self::ICO_LENGTH) {
            throw new InvalidIcoException(
                "IČO must be exactly " . self::ICO_LENGTH . " digits long. Got: '{$ico}'."
            );
        }

        if (!$this->passesChecksum($ico)) {
            throw new InvalidIcoException("IČO '{$ico}' has an invalid checksum.");
        }

        return $ico;
    }

    /**
     * Czech IČO checksum algorithm (MOD 11).
     */
    private function passesChecksum(string $ico): bool
    {
        $weights = [8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $ico[$i] * $weights[$i];
        }

        $remainder = $sum % 11;
        $checkDigit = match ($remainder) {
            0       => 1,
            1       => 0,
            default => 11 - $remainder,
        };

        return $checkDigit === (int) $ico[7];
    }
}

<?php

declare(strict_types=1);

namespace SuperFaktura\Tests;

use SuperFaktura\Exception\InvalidIcoException;
use SuperFaktura\IcoValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IcoValidatorTest extends TestCase
{
    private IcoValidator $validator;

    protected function setUp(): void
    {
        /** @phpstan-ignore-next-line */
        $this->validator = new IcoValidator();
    }

    // -------------------------------------------------------------------------
    // Valid IČOs
    // -------------------------------------------------------------------------

    #[DataProvider('validIcoProvider')]
    public function testValidIcoPassesValidation(string $input, string $expected): void
    {
        $result = $this->validator->validate($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validIcoProvider(): array
    {
        return [
            'Škoda Auto'              => ['00177041', '00177041'],
            'ČEZ'                     => ['45274649', '45274649'],
            'short ico padded'        => ['177041',   '00177041'],  // auto-padded
            'Anthropic CZ (example)' => ['01569651', '01569651'],
        ];
    }

    // -------------------------------------------------------------------------
    // Invalid IČOs
    // -------------------------------------------------------------------------

    #[DataProvider('invalidIcoProvider')]
    public function testInvalidIcoThrowsException(string $ico, string $expectedMessage): void
    {
        $this->expectException(InvalidIcoException::class);
        $this->expectExceptionMessageMatches($expectedMessage);
        $this->validator->validate($ico);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidIcoProvider(): array
    {
        return [
            'letters in ico'         => ['ABCD1234', '/must contain only digits/'],
            'wrong checksum'         => ['12345678', '/invalid checksum/'],
            'empty string'           => ['',         '/invalid checksum/'],
        ];
    }

    public function testWhitespaceIsTrimmed(): void
    {
        // '01569651' is a valid IČO; whitespace should be stripped before validation
        $result = $this->validator->validate('  01569651  ');
        $this->assertSame('01569651', $result);
    }
}

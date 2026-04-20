<?php

declare(strict_types=1);

namespace SuperFaktura\Tests;

use SuperFaktura\Contract\AresClientInterface;
use SuperFaktura\AresService;
use SuperFaktura\DTO\CompanyData;
use SuperFaktura\Exception\AresNotFoundException;
use SuperFaktura\Exception\InvalidIcoException;
use SuperFaktura\IcoValidator;
use PHPUnit\Framework\TestCase;

class AresServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeService(array $rawApiResponse): AresService
    {
        $validator = new IcoValidator();

        $client = $this->createMock(AresClientInterface::class);
        $client->method('fetchByIco')->willReturn($rawApiResponse);

        return new AresService($validator, $client);
    }

    private function sampleApiResponse(string $ico = '01569651'): array
    {
        return [
            'ico'            => $ico,
            'obchodniJmeno'  => 'Testovací s.r.o.',
            'dic'            => 'CZ01569651',
            'pravniForma'    => '112',
            'stavSubjektu'   => 'AKTIVNI',
            'sidlo'          => [
                'nazevUlice'    => 'Wenceslas Square',
                'cisloDomovni'  => '1',
                'cisloOrientacni' => '1',
                'nazevObce'     => 'Praha',
                'psc'           => '11000',
                'nazevStatu'    => 'Česká republika',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testGetByIcoReturnsCompanyData(): void
    {
        $service = $this->makeService($this->sampleApiResponse());
        $company = $service->getByIco('01569651');

        $this->assertInstanceOf(CompanyData::class, $company);
        $this->assertSame('01569651', $company->ico);
        $this->assertSame('Testovací s.r.o.', $company->name);
        $this->assertSame('CZ01569651', $company->dic);
        $this->assertTrue($company->isActive);
    }

    public function testAddressIsMappedCorrectly(): void
    {
        $service = $this->makeService($this->sampleApiResponse());
        $company = $service->getByIco('01569651');

        $this->assertSame('Praha', $company->city);
        $this->assertSame('11000', $company->zip);
        $this->assertStringContainsString('Wenceslas Square', $company->street);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $service  = $this->makeService($this->sampleApiResponse());
        $company  = $service->getByIco('01569651');
        $array    = $company->toArray();

        $this->assertArrayHasKey('ico', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('dic', $array);
        $this->assertArrayHasKey('address', $array);
        $this->assertArrayHasKey('is_active', $array);
    }

    // -------------------------------------------------------------------------
    // Input validation
    // -------------------------------------------------------------------------

    public function testInvalidIcoThrows(): void
    {
        $client = $this->createMock(AresClientInterface::class);
        $client->expects($this->never())->method('fetchByIco');
        $service = new AresService(new IcoValidator(), $client);

        $this->expectException(InvalidIcoException::class);
        $service->getByIco('00000000'); // invalid checksum
    }

    // -------------------------------------------------------------------------
    // Batch lookup
    // -------------------------------------------------------------------------

    public function testGetByIcoMultipleHandlesPartialFailures(): void
    {
        $validator = new IcoValidator();
        $client    = $this->createMock(AresClientInterface::class);

        $client->method('fetchByIco')
            ->willReturnCallback(function (string $ico) {
                if ($ico === '01569651') {
                    return $this->sampleApiResponse($ico);
                }
                throw new AresNotFoundException("Not found: {$ico}");
            });

        $service = new AresService($validator, $client);
        $results = $service->getByIcoMultiple(['01569651', '00000001']);

        $this->assertInstanceOf(CompanyData::class, $results['01569651']);
        // '00000001' has invalid checksum → caught as InvalidIcoException
        $this->assertInstanceOf(\Exception::class, $results['00000001']);
    }

    // -------------------------------------------------------------------------
    // isActive flag
    // -------------------------------------------------------------------------

    public function testInactiveCompanyFlagIsSet(): void
    {
        $response                 = $this->sampleApiResponse();
        $response['stavSubjektu'] = 'ZANIKLÝ';

        $company = $this->makeService($response)->getByIco('01569651');

        $this->assertFalse($company->isActive);
    }

    public function testActiveCompanyWithoutDiacritics(): void
    {
        // ARES niekedy vráti 'AKTIVNI' (bez diakritiky)
        $response                 = $this->sampleApiResponse();
        $response['stavSubjektu'] = 'AKTIVNI';

        $company = $this->makeService($response)->getByIco('01569651');

        $this->assertTrue($company->isActive);
    }

    public function testActiveCompanyWithDiacritics(): void
    {
        // ARES niekedy vráti 'AKTIVNÍ' (s diakritikou) — bug fix verifikácia
        $response                 = $this->sampleApiResponse();
        $response['stavSubjektu'] = 'AKTIVNÍ';

        $company = $this->makeService($response)->getByIco('01569651');

        $this->assertTrue($company->isActive);
    }
}

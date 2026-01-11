<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DomainCheckerService;
use App\Service\DomainStatus;
use App\Service\WhoisClientInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DomainCheckerServiceTest extends TestCase
{
    private const SUPPORTED_TLDS = ['cz', 'com', 'net', 'eu', 'org', 'dev', 'ai', 'info', 'de', 'at', 'es', 'us'];

    #[Test]
    #[DataProvider('availableDomainProvider')]
    public function detectsAvailableDomain(string $tld, string $whoisResponse): void
    {
        $domain = "test-available-domain.{$tld}";
        $whoisClient = $this->createMock(WhoisClientInterface::class);
        $whoisClient->method('getSupportedTlds')->willReturn(self::SUPPORTED_TLDS);
        $whoisClient->method('query')->willReturn($whoisResponse);

        $service = new DomainCheckerService($whoisClient);
        $result = $service->check($domain);

        $this->assertSame($domain, $result->domain);
        $this->assertSame($tld, $result->tld);
        $this->assertSame(DomainStatus::Available, $result->status);
        $this->assertNull($result->errorMessage);
    }

    #[Test]
    #[DataProvider('registeredDomainProvider')]
    public function detectsRegisteredDomain(string $tld, string $whoisResponse): void
    {
        $domain = "test-registered-domain.{$tld}";
        $whoisClient = $this->createMock(WhoisClientInterface::class);
        $whoisClient->method('getSupportedTlds')->willReturn(self::SUPPORTED_TLDS);
        $whoisClient->method('query')->willReturn($whoisResponse);

        $service = new DomainCheckerService($whoisClient);
        $result = $service->check($domain);

        $this->assertSame($domain, $result->domain);
        $this->assertSame($tld, $result->tld);
        $this->assertSame(DomainStatus::Registered, $result->status);
        $this->assertNull($result->errorMessage);
    }

    #[Test]
    public function returnsErrorForUnsupportedTld(): void
    {
        $domain = 'example.xyz';
        $whoisClient = $this->createMock(WhoisClientInterface::class);
        $whoisClient->method('getSupportedTlds')->willReturn(self::SUPPORTED_TLDS);

        $service = new DomainCheckerService($whoisClient);
        $result = $service->check($domain);

        $this->assertSame($domain, $result->domain);
        $this->assertSame('xyz', $result->tld);
        $this->assertSame(DomainStatus::Error, $result->status);
        $this->assertSame('Unsupported TLD: .xyz', $result->errorMessage);
    }

    #[Test]
    public function returnsErrorForInvalidDomain(): void
    {
        $domain = 'invalid';
        $whoisClient = $this->createMock(WhoisClientInterface::class);
        $whoisClient->method('getSupportedTlds')->willReturn(self::SUPPORTED_TLDS);

        $service = new DomainCheckerService($whoisClient);
        $result = $service->check($domain);

        $this->assertSame($domain, $result->domain);
        $this->assertNull($result->tld);
        $this->assertSame(DomainStatus::Error, $result->status);
        $this->assertSame('Could not extract TLD', $result->errorMessage);
    }

    #[Test]
    public function returnsErrorWhenWhoisQueryFails(): void
    {
        $domain = 'example.com';
        $whoisClient = $this->createMock(WhoisClientInterface::class);
        $whoisClient->method('getSupportedTlds')->willReturn(self::SUPPORTED_TLDS);
        $whoisClient->method('query')->willReturn(null);

        $service = new DomainCheckerService($whoisClient);
        $result = $service->check($domain);

        $this->assertSame($domain, $result->domain);
        $this->assertSame('com', $result->tld);
        $this->assertSame(DomainStatus::Error, $result->status);
        $this->assertSame('WHOIS query failed', $result->errorMessage);
    }

    #[Test]
    public function returnsSupportedTlds(): void
    {
        $whoisClient = $this->createMock(WhoisClientInterface::class);
        $whoisClient->method('getSupportedTlds')->willReturn(self::SUPPORTED_TLDS);

        $service = new DomainCheckerService($whoisClient);

        $this->assertSame(self::SUPPORTED_TLDS, $service->getSupportedTlds());
    }

    public static function availableDomainProvider(): iterable
    {
        yield 'cz - available' => [
            'cz',
            "% No entries found.\n%\n% This is the WHOIS service for .cz domain",
        ];

        yield 'com - available' => [
            'com',
            "No match for \"TEST-AVAILABLE-DOMAIN.COM\".\n>>> Last update of whois database",
        ];

        yield 'net - available' => [
            'net',
            "No match for \"TEST-AVAILABLE-DOMAIN.NET\".\n>>> Last update of whois database",
        ];

        yield 'eu - available' => [
            'eu',
            "% The WHOIS service\nStatus: AVAILABLE\n",
        ];

        yield 'org - available' => [
            'org',
            "NOT FOUND\n>>> Last update of WHOIS database",
        ];

        yield 'dev - available' => [
            'dev',
            "No match for \"TEST-AVAILABLE-DOMAIN.DEV\".\n>>> Last update of whois database",
        ];

        yield 'ai - available (NOT FOUND)' => [
            'ai',
            "NOT FOUND\nThe queried object does not exist",
        ];

        yield 'ai - available (No Data Found)' => [
            'ai',
            "No Data Found\nThe domain has not been registered",
        ];

        yield 'info - available' => [
            'info',
            "NOT FOUND\nThe queried object does not exist",
        ];

        yield 'de - available' => [
            'de',
            "Domain: test-available-domain.de\nStatus: free",
        ];

        yield 'at - available' => [
            'at',
            "% nothing found",
        ];

        yield 'es - available (LIBRE)' => [
            'es',
            "Estado del dominio / Domain Status: LIBRE",
        ];

        yield 'es - available (NO EXISTE)' => [
            'es',
            "Estado: NO EXISTE\n",
        ];

        yield 'us - available' => [
            'us',
            "No Data Found\nThe domain has not been registered",
        ];
    }

    public static function registeredDomainProvider(): iterable
    {
        yield 'cz - registered' => [
            'cz',
            "domain: test-registered-domain.cz\nregistrant: CONTACT-ID\nnsset: NSS:NAME-1\n",
        ];

        yield 'com - registered' => [
            'com',
            "Domain Name: TEST-REGISTERED-DOMAIN.COM\nRegistry Domain ID: 123456\nRegistrar WHOIS Server: whois.example.com\n",
        ];

        yield 'net - registered' => [
            'net',
            "Domain Name: TEST-REGISTERED-DOMAIN.NET\nRegistry Domain ID: 123456\nRegistrar WHOIS Server: whois.example.com\n",
        ];

        yield 'eu - registered (NOT AVAILABLE)' => [
            'eu',
            "% The WHOIS service\nStatus: NOT AVAILABLE\n",
        ];

        yield 'eu - registered (REGISTERED)' => [
            'eu',
            "% The WHOIS service\nStatus: REGISTERED\nRegistrar: Example Registrar\n",
        ];

        yield 'org - registered' => [
            'org',
            "Domain Name: TEST-REGISTERED-DOMAIN.ORG\nRegistry Domain ID: 123456\n",
        ];

        yield 'dev - registered' => [
            'dev',
            "Domain Name: TEST-REGISTERED-DOMAIN.DEV\nRegistry Domain ID: 123456\n",
        ];

        yield 'ai - registered' => [
            'ai',
            "Domain Name: TEST-REGISTERED-DOMAIN.AI\nRegistry Domain ID: 123456\n",
        ];

        yield 'info - registered' => [
            'info',
            "Domain Name: TEST-REGISTERED-DOMAIN.INFO\nRegistry Domain ID: 123456\n",
        ];

        yield 'de - registered' => [
            'de',
            "Domain: test-registered-domain.de\nStatus: connect\nNserver: ns1.example.com\n",
        ];

        yield 'at - registered' => [
            'at',
            "domain: test-registered-domain.at\nregistrant: CONTACT-ID\nnserver: ns1.example.com\n",
        ];

        yield 'es - registered' => [
            'es',
            "Estado del dominio / Domain Status: ACTIVO\n",
        ];

        yield 'us - registered' => [
            'us',
            "Domain Name: TEST-REGISTERED-DOMAIN.US\nRegistry Domain ID: 123456\n",
        ];
    }
}

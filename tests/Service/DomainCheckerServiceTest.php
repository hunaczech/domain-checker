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
    private const SUPPORTED_TLDS = ['cz', 'com', 'net', 'eu', 'org', 'dev', 'ai', 'io', 'info', 'de', 'at', 'es', 'us', 'sk', 'ua', 'lt', 'fi', 'se', 'nl', 'bg', 'pt', 'it', 'hu', 'pl'];

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

        yield 'dev - available (RDAP 404)' => [
            'dev',
            "HTTP_STATUS:404\n{\"errorCode\":404,\"title\":\"Not Found\"}",
        ];

        yield 'ai - available (NOT FOUND)' => [
            'ai',
            "NOT FOUND\nThe queried object does not exist",
        ];

        yield 'ai - available (No Data Found)' => [
            'ai',
            "No Data Found\nThe domain has not been registered",
        ];

        yield 'io - available' => [
            'io',
            "Domain not found.\n>>> Last update of WHOIS database",
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

        yield 'sk - available' => [
            'sk',
            "DOMAIN NOT FOUND\n%No entries found",
        ];

        yield 'ua - available' => [
            'ua',
            "% No entries found for the selected source(s).\n%",
        ];

        yield 'lt - available' => [
            'lt',
            "Status: available\nRegistered: -",
        ];

        yield 'fi - available' => [
            'fi',
            "Domain not found\n",
        ];

        yield 'se - available' => [
            'se',
            "\"test-available-domain.se\" not found.\n",
        ];

        yield 'nl - available' => [
            'nl',
            "test-available-domain.nl is free\n",
        ];

        yield 'bg - available' => [
            'bg',
            "registration status: available\n",
        ];

        yield 'pt - available' => [
            'pt',
            "No match for \"test-available-domain.pt\"\n",
        ];

        yield 'it - available' => [
            'it',
            "Domain:             test-available-domain.it\nStatus:             AVAILABLE\n",
        ];

        yield 'hu - available' => [
            'hu',
            "% Whois server 1.0\n% No match\n",
        ];

        yield 'pl - available' => [
            'pl',
            "No information available about domain name test-available-domain.pl in the Registry NASK database.\n",
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

        yield 'dev - registered (RDAP 200)' => [
            'dev',
            "HTTP_STATUS:200\n{\"objectClassName\":\"domain\",\"ldhName\":\"test.dev\"}",
        ];

        yield 'ai - registered' => [
            'ai',
            "Domain Name: TEST-REGISTERED-DOMAIN.AI\nRegistry Domain ID: 123456\n",
        ];

        yield 'io - registered' => [
            'io',
            "Domain Name: TEST-REGISTERED-DOMAIN.IO\nRegistry Domain ID: 123456\nRegistrar: Example Registrar\n",
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

        yield 'sk - registered' => [
            'sk',
            "Domain-name: test-registered-domain.sk\nAdmin-id: SK-1234\n",
        ];

        yield 'ua - registered' => [
            'ua',
            "domain: test-registered-domain.ua\nstatus: ok\n",
        ];

        yield 'lt - registered' => [
            'lt',
            "Domain: test-registered-domain.lt\nStatus: registered\n",
        ];

        yield 'fi - registered' => [
            'fi',
            "domain: test-registered-domain.fi\nstatus: Registered\n",
        ];

        yield 'se - registered' => [
            'se',
            "domain: test-registered-domain.se\nstate: active\n",
        ];

        yield 'nl - registered' => [
            'nl',
            "Domain name: test-registered-domain.nl\nStatus: active\n",
        ];

        yield 'bg - registered' => [
            'bg',
            "DOMAIN NAME: test-registered-domain.bg\nregistration status: registered\n",
        ];

        yield 'pt - registered' => [
            'pt',
            "Domain Name: test-registered-domain.pt\nCreation Date: 2020-01-01\n",
        ];

        yield 'it - registered' => [
            'it',
            "Domain:             test-registered-domain.it\nStatus:             ok\n",
        ];

        yield 'hu - registered' => [
            'hu',
            "domain: test-registered-domain.hu\nregistrant-id: HU12345\n",
        ];

        yield 'pl - registered' => [
            'pl',
            "DOMAIN NAME: test-registered-domain.pl\nregistrant type: organization\n",
        ];
    }
}

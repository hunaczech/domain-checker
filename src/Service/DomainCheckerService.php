<?php

declare(strict_types=1);

namespace App\Service;

final class DomainCheckerService
{
    private const AVAILABLE_PATTERNS = [
        'cz' => '/No entries found/i',
        'com' => '/No match for/i',
        'net' => '/No match for/i',
        'eu' => '/^Status:\s*AVAILABLE/mi',
        'org' => '/NOT FOUND/i',
        'dev' => '/^HTTP_STATUS:404/m',
        'ai' => '/(NOT FOUND|No Data Found)/i',
        'io' => '/Domain not found/i',
        'info' => '/NOT FOUND/i',
        'de' => '/Status:\s*free/i',
        'at' => '/nothing found/i',
        'es' => '/(LIBRE|NO EXISTE)/i',
        'us' => '/No Data Found/i',
    ];

    private const REGISTERED_PATTERNS = [
        'eu' => '/^Status:\s*(NOT AVAILABLE|REGISTERED)/mi',
    ];

    public function __construct(
        private readonly WhoisClientInterface $whoisClient,
    ) {
    }

    public function check(string $domain): DomainCheckResult
    {
        $tld = $this->extractTld($domain);

        if ($tld === null) {
            return new DomainCheckResult($domain, null, DomainStatus::Error, 'Could not extract TLD');
        }

        $supportedTlds = $this->whoisClient->getSupportedTlds();
        if (!in_array($tld, $supportedTlds, true)) {
            return new DomainCheckResult($domain, $tld, DomainStatus::Error, sprintf('Unsupported TLD: .%s', $tld));
        }

        $response = $this->whoisClient->query($domain, $tld);

        if ($response === null) {
            return new DomainCheckResult($domain, $tld, DomainStatus::Error, 'WHOIS query failed');
        }

        return $this->parseResponse($domain, $tld, $response);
    }

    public function getSupportedTlds(): array
    {
        return $this->whoisClient->getSupportedTlds();
    }

    private function extractTld(string $domain): ?string
    {
        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            return null;
        }

        return strtolower(end($parts));
    }

    private function parseResponse(string $domain, string $tld, string $response): DomainCheckResult
    {
        $availablePattern = self::AVAILABLE_PATTERNS[$tld] ?? null;
        $registeredPattern = self::REGISTERED_PATTERNS[$tld] ?? null;

        if ($registeredPattern !== null && preg_match($registeredPattern, $response)) {
            return new DomainCheckResult($domain, $tld, DomainStatus::Registered);
        }

        if ($availablePattern !== null && preg_match($availablePattern, $response)) {
            return new DomainCheckResult($domain, $tld, DomainStatus::Available);
        }

        return new DomainCheckResult($domain, $tld, DomainStatus::Registered);
    }
}

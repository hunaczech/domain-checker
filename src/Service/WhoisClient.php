<?php

declare(strict_types=1);

namespace App\Service;

final class WhoisClient implements WhoisClientInterface
{
    private const WHOIS_SERVERS = [
        'cz' => 'whois.nic.cz',
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'eu' => 'whois.eu',
        'org' => 'whois.pir.org',
        'ai' => 'whois.nic.ai',
        'io' => 'whois.nic.io',
        'info' => 'whois.afilias.net',
        'de' => 'whois.denic.de',
        'at' => 'whois.nic.at',
        'es' => 'whois.nic.es',
        'us' => 'whois.nic.us',
        'sk' => 'whois.sk-nic.sk',
        'ua' => 'whois.ua',
        'lt' => 'whois.domreg.lt',
        'fi' => 'whois.fi',
        'se' => 'whois.iis.se',
        'nl' => 'whois.domain-registry.nl',
        'bg' => 'whois.register.bg',
        'pt' => 'whois.dns.pt',
        'it' => 'whois.nic.it',
    ];

    private const RDAP_SERVERS = [
        'dev' => 'https://pubapi.registry.google/rdap/domain/',
    ];

    private const WHOIS_PORT = 43;
    private const TIMEOUT = 10;

    public function query(string $domain, string $tld): ?string
    {
        if (isset(self::RDAP_SERVERS[$tld])) {
            return $this->queryRdap($domain, $tld);
        }

        return $this->queryWhois($domain, $tld);
    }

    private function queryWhois(string $domain, string $tld): ?string
    {
        $server = self::WHOIS_SERVERS[$tld] ?? null;

        if ($server === null) {
            return null;
        }

        $socket = @fsockopen($server, self::WHOIS_PORT, $errno, $errstr, self::TIMEOUT);

        if (!$socket) {
            return null;
        }

        stream_set_timeout($socket, self::TIMEOUT);

        $query = $this->buildQuery($domain, $tld);
        fwrite($socket, $query . "\r\n");

        $response = '';
        while (!feof($socket)) {
            $response .= fgets($socket, 128);
        }

        fclose($socket);

        return $response;
    }

    private function queryRdap(string $domain, string $tld): ?string
    {
        $baseUrl = self::RDAP_SERVERS[$tld] ?? null;

        if ($baseUrl === null) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($baseUrl . $domain, false, $context);

        if ($response === false) {
            return null;
        }

        // Extract HTTP status code from response headers
        $statusCode = 200;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $statusCode = (int) ($matches[1] ?? 200);
        }

        return 'HTTP_STATUS:' . $statusCode . "\n" . $response;
    }

    public function getSupportedTlds(): array
    {
        return array_merge(
            array_keys(self::WHOIS_SERVERS),
            array_keys(self::RDAP_SERVERS),
        );
    }

    private function buildQuery(string $domain, string $tld): string
    {
        return match ($tld) {
            'de' => '-T dn,ace ' . $domain,
            default => $domain,
        };
    }
}

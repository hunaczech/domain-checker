<?php

declare(strict_types=1);

namespace App\Service;

final class WhoisClient
{
    private const WHOIS_SERVERS = [
        'cz' => 'whois.nic.cz',
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'eu' => 'whois.eu',
        'org' => 'whois.pir.org',
        'dev' => 'whois.nic.google',
        'ai' => 'whois.nic.ai',
        'info' => 'whois.afilias.net',
        'de' => 'whois.denic.de',
        'at' => 'whois.nic.at',
        'es' => 'whois.nic.es',
        'us' => 'whois.nic.us',
    ];

    private const WHOIS_PORT = 43;
    private const TIMEOUT = 10;

    public function query(string $domain, string $tld): ?string
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

    public function getSupportedTlds(): array
    {
        return array_keys(self::WHOIS_SERVERS);
    }

    private function buildQuery(string $domain, string $tld): string
    {
        return match ($tld) {
            'de' => '-T dn,ace ' . $domain,
            default => $domain,
        };
    }
}

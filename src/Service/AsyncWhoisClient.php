<?php

declare(strict_types=1);

namespace App\Service;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Socket\ConnectContext;
use Amp\Socket\Socket;
use Amp\TimeoutCancellation;
use function Amp\Socket\connect;

final class AsyncWhoisClient implements WhoisClientInterface
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

        try {
            $context = (new ConnectContext())->withConnectTimeout(self::TIMEOUT);
            $cancellation = new TimeoutCancellation(self::TIMEOUT);

            /** @var Socket $socket */
            $socket = connect($server . ':' . self::WHOIS_PORT, $context, $cancellation);

            $query = $this->buildQuery($domain, $tld);
            $socket->write($query . "\r\n");

            $response = '';
            while (($chunk = $socket->read()) !== null) {
                $response .= $chunk;
            }

            $socket->close();

            return $response;
        } catch (\Throwable) {
            return null;
        }
    }

    private function queryRdap(string $domain, string $tld): ?string
    {
        $baseUrl = self::RDAP_SERVERS[$tld] ?? null;

        if ($baseUrl === null) {
            return null;
        }

        try {
            $client = HttpClientBuilder::buildDefault();
            $request = new Request($baseUrl . $domain);
            $request->setTransferTimeout(self::TIMEOUT);

            $response = $client->request($request);
            $body = $response->getBody()->buffer();

            // Return status code as part of response for pattern matching
            return 'HTTP_STATUS:' . $response->getStatus() . "\n" . $body;
        } catch (\Throwable) {
            return null;
        }
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

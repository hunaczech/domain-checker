<?php

declare(strict_types=1);

namespace App\Service;

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

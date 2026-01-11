<?php

declare(strict_types=1);

namespace App\Service;

interface WhoisClientInterface
{
    public function query(string $domain, string $tld): ?string;

    /**
     * @return array<string>
     */
    public function getSupportedTlds(): array;
}

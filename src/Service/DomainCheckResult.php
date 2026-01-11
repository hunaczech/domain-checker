<?php

declare(strict_types=1);

namespace App\Service;

final readonly class DomainCheckResult
{
    public function __construct(
        public string $domain,
        public ?string $tld,
        public DomainStatus $status,
        public ?string $errorMessage = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

enum DomainStatus: string
{
    case Available = 'available';
    case Registered = 'registered';
    case Error = 'error';
}

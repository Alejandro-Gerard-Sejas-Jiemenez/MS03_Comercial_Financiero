<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\System;

use DateTimeImmutable;
use Src\Shared\Application\Port\Clock;

final class SystemClock implements Clock
{
    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }

    public function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}

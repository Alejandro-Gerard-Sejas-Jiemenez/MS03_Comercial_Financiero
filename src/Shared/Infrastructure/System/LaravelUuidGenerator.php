<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\System;

use Illuminate\Support\Str;
use Src\Shared\Application\Port\UuidGenerator;

/**
 * UUID v4, el mismo formato que `gen_random_uuid()` de PostgreSQL.
 */
final class LaravelUuidGenerator implements UuidGenerator
{
    public function generar(): string
    {
        return (string) Str::uuid();
    }
}

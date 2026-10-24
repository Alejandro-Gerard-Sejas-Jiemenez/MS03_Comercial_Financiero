<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

interface UuidGenerator
{
    public function generar(): string;
}

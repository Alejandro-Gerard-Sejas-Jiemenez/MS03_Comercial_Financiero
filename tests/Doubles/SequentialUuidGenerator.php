<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Shared\Application\Port\UuidGenerator;

/**
 * UUIDs predecibles para que las pruebas puedan afirmar sobre los ids.
 */
final class SequentialUuidGenerator implements UuidGenerator
{
    private int $siguiente = 1;

    public function __construct(private readonly string $prefijo = '00000000-0000-4000-8000-') {}

    public function generar(): string
    {
        return $this->prefijo.str_pad((string) $this->siguiente++, 12, '0', STR_PAD_LEFT);
    }
}

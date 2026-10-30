<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

final class ResultadoDevolucion
{
    /** @param array<string, mixed> $respuesta */
    private function __construct(
        public readonly bool $exitoso,
        public readonly ?string $referencia,
        public readonly ?string $motivo,
        public readonly array $respuesta,
    ) {}

    /** @param array<string, mixed> $respuesta */
    public static function exitoso(string $referencia, array $respuesta = []): self
    {
        return new self(true, $referencia, null, $respuesta);
    }

    /** @param array<string, mixed> $respuesta */
    public static function fallido(string $motivo, array $respuesta = []): self
    {
        return new self(false, null, $motivo, $respuesta);
    }
}

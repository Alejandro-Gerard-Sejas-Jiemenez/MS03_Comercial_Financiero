<?php

declare(strict_types=1);

namespace Src\Pagos\Application\Port;

/**
 * Lo que responde la pasarela a un cobro. Estructura de datos simple: por la
 * frontera no pasa el cliente HTTP ni su respuesta cruda sin envolver.
 */
final class ResultadoCobro
{
    /** @param array<string, mixed> $respuesta */
    private function __construct(
        public readonly bool $exitoso,
        public readonly ?string $referencia,
        public readonly ?string $motivo,
        public readonly array $respuesta,
    ) {
    }

    /** @param array<string, mixed> $respuesta */
    public static function exitoso(string $referencia, array $respuesta = []): self
    {
        return new self(true, $referencia, null, $respuesta);
    }

    /** @param array<string, mixed> $respuesta */
    public static function fallido(string $motivo, array $respuesta = [], ?string $referencia = null): self
    {
        return new self(false, $referencia, $motivo, $respuesta);
    }
}

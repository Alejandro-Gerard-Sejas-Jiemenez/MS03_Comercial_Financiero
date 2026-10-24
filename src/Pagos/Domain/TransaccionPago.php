<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

use DomainException;
use Src\Shared\Domain\Money;

/**
 * Un movimiento con la pasarela. Inmutable por diseno y append-only en la base
 * de datos (trigger): si algo sale mal no se corrige la fila, se agrega otra
 * transaccion.
 */
final class TransaccionPago
{
    /** @param array<string, mixed>|null $respuestaPasarela */
    private function __construct(
        public readonly string $transaccionId,
        public readonly string $pagoId,
        public readonly TipoTransaccion $tipo,
        public readonly Money $monto,
        public readonly EstadoTransaccion $estado,
        public readonly ?string $referenciaPasarela,
        public readonly ?array $respuestaPasarela,
    ) {
        if (! $this->monto->esPositivo()) {
            throw new DomainException('Una transaccion con la pasarela debe tener monto positivo');
        }
    }

    /** @param array<string, mixed>|null $respuestaPasarela */
    public static function registrar(
        string $transaccionId,
        string $pagoId,
        TipoTransaccion $tipo,
        Money $monto,
        EstadoTransaccion $estado,
        ?string $referenciaPasarela = null,
        ?array $respuestaPasarela = null,
    ): self {
        return new self(
            $transaccionId,
            $pagoId,
            $tipo,
            $monto,
            $estado,
            $referenciaPasarela,
            $respuestaPasarela,
        );
    }

    public function fueExitosa(): bool
    {
        return $this->estado === EstadoTransaccion::EXITOSA;
    }
}

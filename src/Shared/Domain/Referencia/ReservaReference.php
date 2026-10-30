<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Referencia;

use DateTimeImmutable;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Copia local de lo minimo que MS-03 necesita de una reserva de MS-02:
 * fechas, estado y a quien pertenece.
 */
final class ReservaReference
{
    public function __construct(
        public readonly string $reservaId,
        public readonly string $clienteId,
        public readonly string $anfitrionId,
        public readonly string $alojamientoId,
        public readonly DateTimeImmutable $fechaCheckin,
        public readonly DateTimeImmutable $fechaCheckout,
        public readonly EstadoReserva $estado,
        public readonly ?string $categoriaAlojamiento = null,
    ) {
        if ($this->fechaCheckout <= $this->fechaCheckin) {
            throw new ReglaDeNegocioViolada('El check-out debe ser posterior al check-in');
        }
    }

    /** Columna generada `noches` en la base de datos. */
    public function noches(): int
    {
        return (int) $this->fechaCheckin->diff($this->fechaCheckout)->days;
    }

    public function estaCompletada(): bool
    {
        return $this->estado === EstadoReserva::COMPLETADA;
    }

    /** Fecha desde la que la comision se puede liquidar: check-out + N dias del acuerdo. */
    public function liquidableDesde(int $diasLiquidacion): DateTimeImmutable
    {
        if ($diasLiquidacion < 0) {
            throw new ReglaDeNegocioViolada('Los dias de liquidacion no pueden ser negativos');
        }

        return $this->fechaCheckout->modify("+{$diasLiquidacion} days");
    }
}

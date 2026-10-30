<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Pago;

use Src\Financiero\Domain\Enum\EstadoPago;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Reglas de reembolso sobre un pago capturado.
 *
 * Parte de `Pago`: usa sus campos privados.
 */
trait Reembolsos
{
    /**
     * Mueve el estado segun el total acumulado de reembolsos PROCESADOS,
     * incluido el que se acaba de procesar.
     *
     * El acumulado lo calcula el caso de uso: el pago no guarda el historial de
     * reembolsos, lo guardan las filas de `reembolso`.
     */
    public function aplicarReembolso(Money $reembolsadoAcumulado): void
    {
        if (! in_array($this->estado, [EstadoPago::CAPTURADO, EstadoPago::REEMBOLSADO_PARCIAL], true)) {
            throw new ConflictoDeEstado(
                "No se puede reembolsar un pago en estado {$this->estado->value}",
            );
        }

        if ($reembolsadoAcumulado->esNegativo()) {
            throw new ReglaDeNegocioViolada('El monto reembolsado no puede ser negativo');
        }

        if ($reembolsadoAcumulado->esMayorQue($this->total())) {
            throw new ConflictoDeEstado(
                'No se puede reembolsar mas que el total del pago '
                ."({$reembolsadoAcumulado->aDecimal()} > {$this->total()->aDecimal()})",
            );
        }

        $this->estado = $reembolsadoAcumulado->igualA($this->total())
            ? EstadoPago::REEMBOLSADO
            : EstadoPago::REEMBOLSADO_PARCIAL;
    }

    /** Un pago capturado o parcialmente reembolsado todavia tiene dinero que devolver. */
    public function admiteReembolso(): bool
    {
        return in_array($this->estado, [EstadoPago::CAPTURADO, EstadoPago::REEMBOLSADO_PARCIAL], true);
    }

    public function asegurarAdmiteReembolso(): void
    {
        if (! $this->admiteReembolso()) {
            throw new ConflictoDeEstado(
                "El pago {$this->pagoId} esta en estado {$this->estado->value} y no admite reembolso",
            );
        }
    }

    /** Lo que todavia se puede devolver: el total menos lo ya reembolsado. */
    public function saldoReembolsable(Money $yaReembolsado): Money
    {
        return $this->total()->menos($yaReembolsado);
    }
}

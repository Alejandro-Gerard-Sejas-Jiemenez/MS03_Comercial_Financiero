<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Comision;

use DateTimeImmutable;
use Src\Financiero\Domain\Enum\EstadoComision;
use Src\Shared\Domain\Exception\ConflictoDeEstado;

/**
 * Cambios de estado de la comision: liquidable, asignada, liberada y revertida.
 *
 * Parte de `Comision`: usa sus campos privados.
 */
trait CicloDeVida
{
    /**
     * la estadia termino, asi que la comision pasa a LIQUIDABLE y
     * queda disponible desde `check-out + dias_liquidacion` del acuerdo.
     */
    public function marcarLiquidable(DateTimeImmutable $liquidableDesde): void
    {
        if ($this->estado === EstadoComision::REVERTIDA) {
            throw new ConflictoDeEstado(
                "La comision {$this->comisionId} esta REVERTIDA y no se puede liquidar",
            );
        }

        if ($this->estado !== EstadoComision::RETENIDA) {
            throw new ConflictoDeEstado(
                "Solo una comision RETENIDA se vuelve LIQUIDABLE, no una {$this->estado->value}",
            );
        }

        $this->estado = EstadoComision::LIQUIDABLE;
        $this->liquidableDesde = new DateTimeImmutable($liquidableDesde->format('Y-m-d').' 00:00:00');
    }

    /** Liquidable y con su plazo ya cumplido en la fecha dada. */
    public function sePuedeLiquidarEn(DateTimeImmutable $fecha): bool
    {
        if ($this->estado !== EstadoComision::LIQUIDABLE) {
            return false;
        }

        if ($this->liquidableDesde === null) {
            return false;
        }

        return new DateTimeImmutable($fecha->format('Y-m-d').' 00:00:00') >= $this->liquidableDesde;
    }

    /** entra en una liquidacion. */
    public function asignarALiquidacion(string $liquidacionId): void
    {
        if ($this->estado !== EstadoComision::LIQUIDABLE) {
            throw new ConflictoDeEstado(
                "Solo se liquida una comision LIQUIDABLE, no una {$this->estado->value}",
            );
        }

        $this->estado = EstadoComision::LIQUIDADA;
        $this->liquidacionId = $liquidacionId;
    }

    /**
     * la liquidacion se rechazo o se anulo, asi que la
     * comision vuelve a estar disponible para la siguiente corrida.
     */
    public function liberar(): void
    {
        if ($this->estado !== EstadoComision::LIQUIDADA) {
            throw new ConflictoDeEstado(
                "Solo se libera una comision LIQUIDADA, no una {$this->estado->value}",
            );
        }

        $this->estado = EstadoComision::LIQUIDABLE;
        $this->liquidacionId = null;
    }

    /**
     * hubo reembolso. Una comision ya pagada al anfitrion no se
     * puede revertir sin mas: eso se arregla descontando de la liquidacion
     * siguiente, no reescribiendo esta.
     */
    public function revertir(): void
    {
        if ($this->estado === EstadoComision::REVERTIDA) {
            return;
        }

        if ($this->estado === EstadoComision::LIQUIDADA) {
            throw new ConflictoDeEstado(
                "La comision {$this->comisionId} ya esta LIQUIDADA: "
                .'el reembolso se descuenta de la liquidacion siguiente',
            );
        }

        $this->estado = EstadoComision::REVERTIDA;
        $this->liquidacionId = null;
    }

    public function estaRevertida(): bool
    {
        return $this->estado === EstadoComision::REVERTIDA;
    }
}

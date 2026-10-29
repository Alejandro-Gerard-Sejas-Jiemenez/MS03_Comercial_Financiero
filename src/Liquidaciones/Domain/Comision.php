<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Comision de un pago capturado. Un pago capturado produce exactamente una
 * comision (`pago_id` es UNIQUE en la tabla).
 *
 * Guarda el porcentaje aplicado como snapshot: si manana cambia la regla de
 * monetizacion, esta comision no se mueve. Es la razon por la que el historico
 * financiero es auditable.
 */
final class Comision
{
    private function __construct(
        private readonly string $comisionId,
        private readonly string $pagoId,
        private readonly string $reservaId,
        private readonly string $anfitrionId,
        private readonly ?string $reglaId,
        private readonly ?string $acuerdoId,
        private ?string $liquidacionId,
        private readonly Money $baseComisionable,
        private readonly ?string $porcentajeAplicado,
        private readonly Money $montoComision,
        private readonly Money $montoNetoAnfitrion,
        private EstadoComision $estado,
        private ?DateTimeImmutable $liquidableDesde,
    ) {}

    public static function registrar(
        string $comisionId,
        string $pagoId,
        string $reservaId,
        string $anfitrionId,
        Money $baseComisionable,
        Money $montoComision,
        ?string $porcentajeAplicado = null,
        ?string $reglaId = null,
        ?string $acuerdoId = null,
    ): self {
        if ($baseComisionable->esNegativo()) {
            throw new ReglaDeNegocioViolada('La base comisionable no puede ser negativa');
        }

        if ($montoComision->esNegativo()) {
            throw new ReglaDeNegocioViolada('La comision no puede ser negativa');
        }

        if ($montoComision->esMayorQue($baseComisionable)) {
            throw new ReglaDeNegocioViolada('La comision no puede superar la base comisionable');
        }

        return new self(
            $comisionId,
            $pagoId,
            $reservaId,
            $anfitrionId,
            $reglaId,
            $acuerdoId,
            null,
            $baseComisionable,
            $porcentajeAplicado,
            $montoComision,
            // neto = base - comision
            $baseComisionable->menos($montoComision),
            EstadoComision::RETENIDA,
            null,
        );
    }

    public static function reconstituir(
        string $comisionId,
        string $pagoId,
        string $reservaId,
        string $anfitrionId,
        ?string $reglaId,
        ?string $acuerdoId,
        ?string $liquidacionId,
        Money $baseComisionable,
        ?string $porcentajeAplicado,
        Money $montoComision,
        Money $montoNetoAnfitrion,
        EstadoComision $estado,
        ?DateTimeImmutable $liquidableDesde,
    ): self {
        return new self(
            $comisionId,
            $pagoId,
            $reservaId,
            $anfitrionId,
            $reglaId,
            $acuerdoId,
            $liquidacionId,
            $baseComisionable,
            $porcentajeAplicado,
            $montoComision,
            $montoNetoAnfitrion,
            $estado,
            $liquidableDesde,
        );
    }

    /**
     * CU-LIQ-03: la estadia termino, asi que la comision pasa a LIQUIDABLE y
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

    /** CU-LIQ-04: entra en una liquidacion. */
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
     * CU-LIQ-06 y CU-LIQ-08: la liquidacion se rechazo o se anulo, asi que la
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
     * CU-LIQ-02: hubo reembolso. Una comision ya pagada al anfitrion no se
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

    public function comisionId(): string
    {
        return $this->comisionId;
    }

    public function pagoId(): string
    {
        return $this->pagoId;
    }

    public function reservaId(): string
    {
        return $this->reservaId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function reglaId(): ?string
    {
        return $this->reglaId;
    }

    public function acuerdoId(): ?string
    {
        return $this->acuerdoId;
    }

    public function liquidacionId(): ?string
    {
        return $this->liquidacionId;
    }

    public function baseComisionable(): Money
    {
        return $this->baseComisionable;
    }

    public function porcentajeAplicado(): ?string
    {
        return $this->porcentajeAplicado;
    }

    public function montoComision(): Money
    {
        return $this->montoComision;
    }

    public function montoNetoAnfitrion(): Money
    {
        return $this->montoNetoAnfitrion;
    }

    public function moneda(): string
    {
        return $this->montoComision->moneda();
    }

    public function estado(): EstadoComision
    {
        return $this->estado;
    }

    public function liquidableDesde(): ?DateTimeImmutable
    {
        return $this->liquidableDesde;
    }
}

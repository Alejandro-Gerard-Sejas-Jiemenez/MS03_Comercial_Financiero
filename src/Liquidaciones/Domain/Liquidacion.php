<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

use DateTimeImmutable;
use DomainException;
use Src\Liquidaciones\Domain\Event\LiquidacionPagada;
use Src\Shared\Domain\Money;
use Src\Shared\Domain\RegistraEventos;

/**
 * Lo que la plataforma le paga a un anfitrion por un periodo.
 *
 *   total_neto = total_bruto - total_comisiones - total_reembolsos
 *
 * La base garantiza esa ecuacion con un CHECK; aqui se garantiza ademas que no
 * se genere una liquidacion de monto negativo y que los estados se muevan en
 * orden: GENERADA -> APROBADA -> PAGADA, con RECHAZADA y ANULADA como salidas.
 */
final class Liquidacion
{
    use RegistraEventos;

    private function __construct(
        private readonly string $liquidacionId,
        private readonly string $anfitrionId,
        private ?string $metodoId,
        private readonly DateTimeImmutable $periodoDesde,
        private readonly DateTimeImmutable $periodoHasta,
        private readonly Money $totalBruto,
        private readonly Money $totalComisiones,
        private readonly Money $totalReembolsos,
        private EstadoLiquidacion $estado,
        private ?string $aprobadoPor,
        private ?string $referenciaTransferencia,
    ) {
    }

    public static function generar(
        string $liquidacionId,
        string $anfitrionId,
        DateTimeImmutable $periodoDesde,
        DateTimeImmutable $periodoHasta,
        Money $totalBruto,
        Money $totalComisiones,
        Money $totalReembolsos,
        ?string $metodoId = null,
    ): self {
        if ($periodoHasta < $periodoDesde) {
            throw new DomainException('El periodo no puede terminar antes de empezar');
        }

        $neto = $totalBruto->menos($totalComisiones)->menos($totalReembolsos);

        if ($neto->esNegativo()) {
            throw new DomainException(
                "La liquidacion quedaria en {$neto->aDecimal()}: "
                . 'los reembolsos superan lo que se le debe al anfitrion',
            );
        }

        if ($neto->esCero()) {
            throw new DomainException('No se genera una liquidacion de monto cero');
        }

        return new self(
            $liquidacionId,
            $anfitrionId,
            $metodoId,
            self::soloFecha($periodoDesde),
            self::soloFecha($periodoHasta),
            $totalBruto,
            $totalComisiones,
            $totalReembolsos,
            EstadoLiquidacion::GENERADA,
            null,
            null,
        );
    }

    public static function reconstituir(
        string $liquidacionId,
        string $anfitrionId,
        ?string $metodoId,
        DateTimeImmutable $periodoDesde,
        DateTimeImmutable $periodoHasta,
        Money $totalBruto,
        Money $totalComisiones,
        Money $totalReembolsos,
        EstadoLiquidacion $estado,
        ?string $aprobadoPor,
        ?string $referenciaTransferencia,
    ): self {
        return new self(
            $liquidacionId,
            $anfitrionId,
            $metodoId,
            $periodoDesde,
            $periodoHasta,
            $totalBruto,
            $totalComisiones,
            $totalReembolsos,
            $estado,
            $aprobadoPor,
            $referenciaTransferencia,
        );
    }

    public function totalNeto(): Money
    {
        return $this->totalBruto->menos($this->totalComisiones)->menos($this->totalReembolsos);
    }

    /** CU-LIQ-05. */
    public function aprobar(string $aprobadoPor): void
    {
        if ($this->estado !== EstadoLiquidacion::GENERADA) {
            throw new DomainException(
                "Solo se aprueba una liquidacion GENERADA, no una {$this->estado->value}",
            );
        }

        if ($this->metodoId === null) {
            throw new DomainException(
                'No se aprueba una liquidacion sin metodo de cobro verificado del anfitrion',
            );
        }

        $this->estado = EstadoLiquidacion::APROBADA;
        $this->aprobadoPor = $aprobadoPor;
    }

    /** CU-LIQ-06: rechazo con motivo; las comisiones se liberan en el caso de uso. */
    public function rechazar(string $rechazadoPor, string $motivo): void
    {
        if ($this->estado !== EstadoLiquidacion::GENERADA) {
            throw new DomainException(
                "Solo se rechaza una liquidacion GENERADA, no una {$this->estado->value}",
            );
        }

        if (trim($motivo) === '') {
            throw new DomainException('Rechazar una liquidacion exige un motivo');
        }

        $this->estado = EstadoLiquidacion::RECHAZADA;
        $this->aprobadoPor = $rechazadoPor;
    }

    /**
     * CU-LIQ-06 y CU-LIQ-08: se anula una liquidacion que aun no se pago. Una
     * PAGADA no se anula: el dinero ya salio y eso se corrige con un asiento de
     * reversa.
     */
    public function anular(string $motivo): void
    {
        if (! in_array($this->estado, [EstadoLiquidacion::GENERADA, EstadoLiquidacion::APROBADA], true)) {
            throw new DomainException(
                "No se anula una liquidacion en estado {$this->estado->value}",
            );
        }

        if (trim($motivo) === '') {
            throw new DomainException('Anular una liquidacion exige un motivo');
        }

        $this->estado = EstadoLiquidacion::ANULADA;
    }

    /** CU-LIQ-07: se registro la transferencia. */
    public function pagar(string $referenciaTransferencia): void
    {
        if ($this->estado !== EstadoLiquidacion::APROBADA) {
            throw new DomainException(
                "Solo se paga una liquidacion APROBADA, no una {$this->estado->value}",
            );
        }

        if (trim($referenciaTransferencia) === '') {
            throw new DomainException('Pagar una liquidacion exige la referencia de la transferencia');
        }

        $this->estado = EstadoLiquidacion::PAGADA;
        $this->referenciaTransferencia = $referenciaTransferencia;

        $this->registrar(new LiquidacionPagada(
            liquidacionId: $this->liquidacionId,
            anfitrionId: $this->anfitrionId,
            totalNeto: $this->totalNeto(),
            periodoDesde: $this->periodoDesde,
            periodoHasta: $this->periodoHasta,
            referenciaTransferencia: $referenciaTransferencia,
        ));
    }

    public function asignarMetodoDeCobro(string $metodoId): void
    {
        if ($this->estado !== EstadoLiquidacion::GENERADA) {
            throw new DomainException(
                "El metodo de cobro se asigna antes de aprobar, no en estado {$this->estado->value}",
            );
        }

        $this->metodoId = $metodoId;
    }

    public function estaPagada(): bool
    {
        return $this->estado === EstadoLiquidacion::PAGADA;
    }

    public function liquidacionId(): string
    {
        return $this->liquidacionId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function metodoId(): ?string
    {
        return $this->metodoId;
    }

    public function periodoDesde(): DateTimeImmutable
    {
        return $this->periodoDesde;
    }

    public function periodoHasta(): DateTimeImmutable
    {
        return $this->periodoHasta;
    }

    public function totalBruto(): Money
    {
        return $this->totalBruto;
    }

    public function totalComisiones(): Money
    {
        return $this->totalComisiones;
    }

    public function totalReembolsos(): Money
    {
        return $this->totalReembolsos;
    }

    public function moneda(): string
    {
        return $this->totalBruto->moneda();
    }

    public function estado(): EstadoLiquidacion
    {
        return $this->estado;
    }

    public function aprobadoPor(): ?string
    {
        return $this->aprobadoPor;
    }

    public function referenciaTransferencia(): ?string
    {
        return $this->referenciaTransferencia;
    }

    private static function soloFecha(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d') . ' 00:00:00');
    }
}

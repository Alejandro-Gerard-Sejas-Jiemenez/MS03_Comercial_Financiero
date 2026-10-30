<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Liquidacion;

use Src\Financiero\Domain\Enum\EstadoLiquidacion;
use Src\Financiero\Domain\Event\LiquidacionPagada;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Cambios de estado de la liquidacion: aprobar, rechazar, anular y pagar.
 *
 * Parte de `Liquidacion`: usa sus campos privados.
 */
trait CicloDeVida
{
    /** . */
    public function aprobar(string $aprobadoPor): void
    {
        if ($this->estado !== EstadoLiquidacion::GENERADA) {
            throw new ConflictoDeEstado(
                "Solo se aprueba una liquidacion GENERADA, no una {$this->estado->value}",
            );
        }

        if ($this->metodoId === null) {
            throw new ReglaDeNegocioViolada(
                'No se aprueba una liquidacion sin metodo de cobro verificado del anfitrion',
            );
        }

        $this->estado = EstadoLiquidacion::APROBADA;
        $this->aprobadoPor = $aprobadoPor;
    }

    /** rechazo con motivo; las comisiones se liberan en el caso de uso. */
    public function rechazar(string $rechazadoPor, string $motivo): void
    {
        if ($this->estado !== EstadoLiquidacion::GENERADA) {
            throw new ConflictoDeEstado(
                "Solo se rechaza una liquidacion GENERADA, no una {$this->estado->value}",
            );
        }

        if (trim($motivo) === '') {
            throw new ReglaDeNegocioViolada('Rechazar una liquidacion exige un motivo');
        }

        $this->estado = EstadoLiquidacion::RECHAZADA;
        $this->aprobadoPor = $rechazadoPor;
    }

    /**
     * se anula una liquidacion que aun no se pago. Una
     * PAGADA no se anula: el dinero ya salio y eso se corrige con un asiento de
     * reversa.
     */
    public function anular(string $motivo): void
    {
        if (! in_array($this->estado, [EstadoLiquidacion::GENERADA, EstadoLiquidacion::APROBADA], true)) {
            throw new ConflictoDeEstado(
                "No se anula una liquidacion en estado {$this->estado->value}",
            );
        }

        if (trim($motivo) === '') {
            throw new ReglaDeNegocioViolada('Anular una liquidacion exige un motivo');
        }

        $this->estado = EstadoLiquidacion::ANULADA;
    }

    /** se registro la transferencia. */
    public function pagar(string $referenciaTransferencia): void
    {
        if ($this->estado !== EstadoLiquidacion::APROBADA) {
            throw new ConflictoDeEstado(
                "Solo se paga una liquidacion APROBADA, no una {$this->estado->value}",
            );
        }

        if (trim($referenciaTransferencia) === '') {
            throw new ReglaDeNegocioViolada('Pagar una liquidacion exige la referencia de la transferencia');
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
            throw new ConflictoDeEstado(
                "El metodo de cobro se asigna antes de aprobar, no en estado {$this->estado->value}",
            );
        }

        $this->metodoId = $metodoId;
    }

    public function estaPagada(): bool
    {
        return $this->estado === EstadoLiquidacion::PAGADA;
    }
}

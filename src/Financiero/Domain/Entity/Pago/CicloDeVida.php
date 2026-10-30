<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Pago;

use Src\Financiero\Domain\Enum\EstadoPago;
use Src\Financiero\Domain\Event\PagoCancelado;
use Src\Financiero\Domain\Event\PagoCapturado;
use Src\Financiero\Domain\Event\PagoFallido;
use Src\Shared\Domain\Exception\ConflictoDeEstado;

/**
 * Cambios de estado del pago: autorizar, capturar, fallar y cancelar.
 *
 * Parte de `Pago`: usa sus campos privados.
 */
trait CicloDeVida
{
    public function autorizar(string $referenciaPasarela, ?string $pasarela = null): void
    {
        if ($this->estado !== EstadoPago::PENDIENTE) {
            throw new ConflictoDeEstado(
                "No se puede autorizar un pago en estado {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::AUTORIZADO;
        $this->referenciaPasarela = $referenciaPasarela;
        $this->pasarela ??= $pasarela;
    }

    /**
     * Cobro confirmado por la pasarela. Solo se puede capturar una vez: un
     * reintento de la saga sobre un pago ya capturado es un error de negocio,
     * no una captura nueva.
     */
    public function capturar(string $referenciaPasarela, ?string $pasarela = null): void
    {
        if (! in_array($this->estado, [EstadoPago::PENDIENTE, EstadoPago::AUTORIZADO], true)) {
            throw new ConflictoDeEstado(
                "No se puede capturar un pago en estado {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::CAPTURADO;
        $this->referenciaPasarela = $referenciaPasarela;
        $this->pasarela ??= $pasarela;

        $this->registrar(new PagoCapturado(
            pagoId: $this->pagoId,
            reservaId: $this->reservaId,
            clienteId: $this->clienteId,
            anfitrionId: $this->anfitrionId,
            subtotal: $this->subtotal,
            descuento: $this->descuento,
            cargoServicio: $this->cargoServicio,
            total: $this->total(),
            promocionId: $this->promocionId,
            sagaId: $this->sagaId,
            referenciaPasarela: $referenciaPasarela,
        ));
    }

    public function marcarFallido(?string $motivo = null): void
    {
        if (! in_array($this->estado, [EstadoPago::PENDIENTE, EstadoPago::AUTORIZADO], true)) {
            throw new ConflictoDeEstado(
                "No se puede marcar fallido un pago en estado {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::FALLIDO;

        $this->registrar(new PagoFallido($this->pagoId, $this->reservaId, $motivo, $this->sagaId));
    }

    /** Compensacion de la saga, o expiracion del pago pendiente. */
    public function cancelar(string $motivo): void
    {
        if (! in_array($this->estado, [EstadoPago::PENDIENTE, EstadoPago::AUTORIZADO], true)) {
            throw new ConflictoDeEstado(
                "Solo se cancela un pago pendiente o autorizado, no uno {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::CANCELADO;

        $this->registrar(new PagoCancelado($this->pagoId, $this->reservaId, $motivo, $this->sagaId));
    }

    public function estaCapturado(): bool
    {
        return $this->estado === EstadoPago::CAPTURADO;
    }
}

<?php

declare(strict_types=1);

namespace Src\Pagos\Domain\Event;

use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\Money;

/**
 * `pago.capturado`. Es el evento central del microservicio:
 *  - MS-02 confirma la reserva;
 *  - MS-04 lo guarda para la prediccion de demanda (F11);
 *  - dentro de MS-03, Liquidaciones registra la comision (CU-LIQ-01),
 *    Promociones registra el uso (CU-PRO-03) y Pagos emite el comprobante
 *    (CU-PAG-10).
 *
 * Los montos viajan en formato decimal, igual que las columnas NUMERIC(12,2).
 */
final class PagoCapturado implements DomainEvent
{
    public function __construct(
        private readonly string $pagoId,
        private readonly string $reservaId,
        private readonly string $clienteId,
        private readonly string $anfitrionId,
        private readonly Money $subtotal,
        private readonly Money $descuento,
        private readonly Money $cargoServicio,
        private readonly Money $total,
        private readonly ?string $promocionId = null,
        private readonly ?string $sagaId = null,
        private readonly ?string $referenciaPasarela = null,
    ) {
    }

    public function tipoEvento(): string
    {
        return 'pago.capturado';
    }

    public function agregadoTipo(): string
    {
        return 'pago';
    }

    public function agregadoId(): string
    {
        return $this->pagoId;
    }

    public function payload(): array
    {
        return [
            'pago_id' => $this->pagoId,
            'reserva_id' => $this->reservaId,
            'cliente_id' => $this->clienteId,
            'anfitrion_id' => $this->anfitrionId,
            'promocion_id' => $this->promocionId,
            'subtotal_alojamiento' => $this->subtotal->aDecimal(),
            'descuento' => $this->descuento->aDecimal(),
            'cargo_servicio' => $this->cargoServicio->aDecimal(),
            'monto_total' => $this->total->aDecimal(),
            'moneda' => $this->total->moneda(),
            'referencia_pasarela' => $this->referenciaPasarela,
        ];
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }
}

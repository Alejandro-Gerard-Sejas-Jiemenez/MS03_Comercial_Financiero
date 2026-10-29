<?php

declare(strict_types=1);

namespace Src\Pagos\Domain\Event;

use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\Money;

/**
 * `reembolso.procesado`. MS-02 marca la reserva como reembolsada y, dentro de
 * MS-03, Liquidaciones revierte la comision (CU-LIQ-02).
 */
final class ReembolsoProcesado implements DomainEvent
{
    public function __construct(
        private readonly string $reembolsoId,
        private readonly string $pagoId,
        private readonly string $reservaId,
        private readonly string $anfitrionId,
        private readonly Money $monto,
        private readonly bool $esTotal,
        private readonly ?string $referenciaPasarela = null,
        private readonly ?string $sagaId = null,
    ) {}

    public function tipoEvento(): string
    {
        return 'reembolso.procesado';
    }

    public function agregadoTipo(): string
    {
        return 'reembolso';
    }

    public function agregadoId(): string
    {
        return $this->reembolsoId;
    }

    public function payload(): array
    {
        return [
            'reembolso_id' => $this->reembolsoId,
            'pago_id' => $this->pagoId,
            'reserva_id' => $this->reservaId,
            'anfitrion_id' => $this->anfitrionId,
            'monto' => $this->monto->aDecimal(),
            'moneda' => $this->monto->moneda(),
            'es_total' => $this->esTotal,
            'referencia_pasarela' => $this->referenciaPasarela,
        ];
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }
}

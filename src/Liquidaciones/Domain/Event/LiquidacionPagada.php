<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain\Event;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\Money;

/**
 * `liquidacion.pagada`. MS-01 lo usa para avisarle al anfitrion que ya se le
 * transfirio su dinero.
 */
final class LiquidacionPagada implements DomainEvent
{
    public function __construct(
        private readonly string $liquidacionId,
        private readonly string $anfitrionId,
        private readonly Money $totalNeto,
        private readonly DateTimeImmutable $periodoDesde,
        private readonly DateTimeImmutable $periodoHasta,
        private readonly string $referenciaTransferencia,
    ) {
    }

    public function tipoEvento(): string
    {
        return 'liquidacion.pagada';
    }

    public function agregadoTipo(): string
    {
        return 'liquidacion';
    }

    public function agregadoId(): string
    {
        return $this->liquidacionId;
    }

    public function payload(): array
    {
        return [
            'liquidacion_id' => $this->liquidacionId,
            'anfitrion_id' => $this->anfitrionId,
            'total_neto' => $this->totalNeto->aDecimal(),
            'moneda' => $this->totalNeto->moneda(),
            'periodo_desde' => $this->periodoDesde->format('Y-m-d'),
            'periodo_hasta' => $this->periodoHasta->format('Y-m-d'),
            'referencia_transferencia' => $this->referenciaTransferencia,
        ];
    }

    public function sagaId(): ?string
    {
        return null;
    }
}

<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\ConsultarPagos;

use Src\Pagos\Domain\Comprobante;
use Src\Pagos\Domain\Pago;
use Src\Shared\Domain\Money;

final class DetalleDePago
{
    /**
     * @param list<\Src\Pagos\Domain\TransaccionPago> $transacciones
     * @param list<\Src\Pagos\Domain\Reembolso> $reembolsos
     */
    public function __construct(
        public readonly Pago $pago,
        public readonly array $transacciones,
        public readonly array $reembolsos,
        public readonly ?Comprobante $comprobante,
        public readonly Money $totalReembolsado,
    ) {
    }

    /** Lo que todavia se puede devolver de este pago. */
    public function saldoReembolsable(): Money
    {
        return $this->pago->total()->menos($this->totalReembolsado);
    }
}

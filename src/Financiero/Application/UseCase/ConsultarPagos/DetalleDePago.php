<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConsultarPagos;

use Src\Financiero\Domain\Entity\Comprobante;
use Src\Financiero\Domain\Entity\Pago;
use Src\Financiero\Domain\Entity\Reembolso;
use Src\Financiero\Domain\Entity\TransaccionPago;
use Src\Shared\Domain\Money;

final class DetalleDePago
{
    /**
     * @param  list<TransaccionPago>  $transacciones
     * @param  list<Reembolso>  $reembolsos
     */
    public function __construct(
        public readonly Pago $pago,
        public readonly array $transacciones,
        public readonly array $reembolsos,
        public readonly ?Comprobante $comprobante,
        public readonly Money $totalReembolsado,
    ) {}

    /** Lo que todavia se puede devolver de este pago. */
    public function saldoReembolsable(): Money
    {
        return $this->pago->total()->menos($this->totalReembolsado);
    }
}

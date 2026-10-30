<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

use Src\Financiero\Domain\Entity\Comprobante;

interface ComprobanteRepository
{
    public function buscar(string $comprobanteId): ?Comprobante;

    /** Comprobante ya emitido del pago, si existe: evita emitir dos veces. */
    public function delPago(string $pagoId): ?Comprobante;

    public function guardar(Comprobante $comprobante): void;
}

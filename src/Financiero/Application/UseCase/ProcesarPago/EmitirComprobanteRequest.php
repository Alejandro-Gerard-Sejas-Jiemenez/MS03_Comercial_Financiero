<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ProcesarPago;

use Src\Financiero\Domain\Enum\TipoComprobante;

final class EmitirComprobanteRequest
{
    public function __construct(
        public readonly string $pagoId,
        public readonly TipoComprobante $tipo = TipoComprobante::RECIBO,
        public readonly ?string $nitCliente = null,
        public readonly ?string $razonSocial = null,
    ) {}
}

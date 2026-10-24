<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\EmitirComprobante;

use Src\Pagos\Domain\TipoComprobante;

final class EmitirComprobanteCommand
{
    public function __construct(
        public readonly string $pagoId,
        public readonly TipoComprobante $tipo = TipoComprobante::RECIBO,
        public readonly ?string $nitCliente = null,
        public readonly ?string $razonSocial = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Domain\Entity\Pago;
use Src\Shared\Application\Response\MontoResponse;

/** Pago con los dos montos derivados que ven el cliente y el anfitrion al crearlo, cobrarlo o cancelarlo. */
final class PagoConTotalResponse
{
    public function __construct(
        public readonly PagoResponse $pago,
        public readonly MontoResponse $total,
        public readonly MontoResponse $montoAnfitrion,
    ) {}

    public static function desde(Pago $pago): self
    {
        return new self(
            PagoResponse::desde($pago),
            MontoResponse::desde($pago->total()),
            MontoResponse::desde($pago->montoDelAnfitrion()),
        );
    }
}

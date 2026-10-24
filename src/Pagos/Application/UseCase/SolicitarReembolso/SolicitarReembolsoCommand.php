<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\SolicitarReembolso;

use DateTimeImmutable;
use Src\Pagos\Domain\MotivoReembolso;
use Src\Shared\Domain\Money;

final class SolicitarReembolsoCommand
{
    public function __construct(
        public readonly string $reservaId,
        public readonly MotivoReembolso $motivo = MotivoReembolso::CANCELACION_CLIENTE,
        public readonly ?DateTimeImmutable $fechaCancelacion = null,
        /** Monto decidido a mano (disputa, caso manual); si viene, se salta la politica. */
        public readonly ?Money $montoForzado = null,
        public readonly ?string $detalle = null,
        public readonly ?string $solicitadoPor = null,
    ) {
    }
}

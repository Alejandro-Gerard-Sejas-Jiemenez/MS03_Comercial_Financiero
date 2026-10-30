<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\SincronizarReferencias;

use DateTimeImmutable;

final class SincronizarReservaRequest
{
    public function __construct(
        public readonly string $reservaId,
        public readonly string $clienteId,
        public readonly string $anfitrionId,
        public readonly string $alojamientoId,
        public readonly DateTimeImmutable $fechaCheckin,
        public readonly DateTimeImmutable $fechaCheckout,
        public readonly string $estado,
        public readonly ?string $categoriaAlojamiento = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\SincronizarReferencias;

final class SincronizarAnfitrionRequest
{
    public function __construct(
        public readonly string $anfitrionId,
        public readonly string $nombreCompleto,
        public readonly string $estado,
        public readonly ?string $nit = null,
        public readonly ?string $razonSocial = null,
    ) {}
}

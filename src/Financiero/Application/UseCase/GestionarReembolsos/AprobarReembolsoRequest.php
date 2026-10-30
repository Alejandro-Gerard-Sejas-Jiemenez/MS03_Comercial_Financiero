<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarReembolsos;

final class AprobarReembolsoRequest
{
    public function __construct(
        public readonly string $reembolsoId,
        public readonly bool $aprobado,
        public readonly string $revisadoPor,
        public readonly ?string $detalle = null,
    ) {}
}

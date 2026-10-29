<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\AprobarReembolso;

final class AprobarReembolsoCommand
{
    public function __construct(
        public readonly string $reembolsoId,
        public readonly bool $aprobado,
        public readonly string $revisadoPor,
        public readonly ?string $detalle = null,
    ) {}
}

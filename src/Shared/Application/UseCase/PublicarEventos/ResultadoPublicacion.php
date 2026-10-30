<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\PublicarEventos;

final class ResultadoPublicacion
{
    public function __construct(
        public readonly int $publicados,
        public readonly int $fallidos,
    ) {}
}

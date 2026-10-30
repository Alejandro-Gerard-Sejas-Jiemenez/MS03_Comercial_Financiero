<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\PublicarEventos;

final class PublicarEventosOutboxRequest
{
    public function __construct(public readonly int $lote = 100)
    {
        if ($this->lote < 1) {
            throw new \InvalidArgumentException('El lote debe ser al menos 1');
        }
    }
}

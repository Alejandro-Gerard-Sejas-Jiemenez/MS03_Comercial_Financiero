<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConciliarTransacciones;

use DateTimeImmutable;

final class EjecutarConciliacionRequest
{
    public function __construct(
        /** Si no viene, se concilia el dia anterior. */
        public readonly ?DateTimeImmutable $fechaCorte = null,
        public readonly ?string $ejecutadaPor = null,
        public readonly string $moneda = 'BOB',
    ) {}
}

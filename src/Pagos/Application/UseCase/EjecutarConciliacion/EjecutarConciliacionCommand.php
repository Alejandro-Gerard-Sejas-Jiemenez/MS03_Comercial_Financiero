<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\EjecutarConciliacion;

use DateTimeImmutable;

final class EjecutarConciliacionCommand
{
    public function __construct(
        /** Si no viene, se concilia el dia anterior. */
        public readonly ?DateTimeImmutable $fechaCorte = null,
        public readonly ?string $ejecutadaPor = null,
        public readonly string $moneda = 'BOB',
    ) {
    }
}

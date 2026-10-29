<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\VencerPromociones;

use DateTimeImmutable;

/**
 * El JOB no recibe nada: la fecha de corte sale del puerto Clock. El campo
 * `fecha` existe para poder reprocesar un dia concreto.
 */
final class VencerPromocionesCommand
{
    public function __construct(
        public readonly ?DateTimeImmutable $fecha = null,
    ) {}
}

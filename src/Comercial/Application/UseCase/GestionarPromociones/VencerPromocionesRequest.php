<?php

declare(strict_types=1);

namespace Src\Comercial\Application\UseCase\GestionarPromociones;

use DateTimeImmutable;

/**
 * El JOB no recibe nada: la fecha de corte sale del puerto Clock. El campo
 * `fecha` existe para poder reprocesar un dia concreto.
 */
final class VencerPromocionesRequest
{
    public function __construct(
        public readonly ?DateTimeImmutable $fecha = null,
    ) {}
}

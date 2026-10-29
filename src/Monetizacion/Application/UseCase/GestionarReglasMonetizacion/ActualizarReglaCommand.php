<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion;

use DateTimeImmutable;

/** Solo los campos no nulos se aplican. */
final class ActualizarReglaCommand
{
    public function __construct(
        public readonly string $reglaId,
        public readonly string $actualizadoPor,
        public readonly ?string $nombre = null,
        public readonly ?string $porcentaje = null,
        public readonly ?int $montoFijoCentavos = null,
        public readonly ?DateTimeImmutable $vigenteHasta = null,
    ) {}
}

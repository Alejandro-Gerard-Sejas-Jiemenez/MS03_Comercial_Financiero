<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\GestionarPromociones;

use DateTimeImmutable;
use Src\Monetizacion\Domain\FinanciamientoDescuento;

/** Solo los campos no nulos se aplican. */
final class ActualizarPromocionCommand
{
    public function __construct(
        public readonly string $promocionId,
        public readonly string $actualizadoPor,
        public readonly ?string $nombre = null,
        public readonly ?string $valor = null,
        public readonly ?DateTimeImmutable $vigenteDesde = null,
        public readonly ?DateTimeImmutable $vigenteHasta = null,
        public readonly ?int $minNoches = null,
        public readonly ?int $maxUsos = null,
        public readonly ?FinanciamientoDescuento $financiadaPor = null,
    ) {}
}

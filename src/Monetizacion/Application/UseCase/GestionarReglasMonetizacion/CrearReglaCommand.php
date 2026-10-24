<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Concepto;
use Src\Monetizacion\Domain\TipoCalculo;

final class CrearReglaCommand
{
    public function __construct(
        public readonly string $nombre,
        public readonly Concepto $concepto,
        public readonly TipoCalculo $tipoCalculo,
        public readonly DateTimeImmutable $vigenteDesde,
        public readonly string $creadoPor,
        public readonly ?string $porcentaje = null,
        public readonly ?int $montoFijoCentavos = null,
        public readonly string $moneda = 'BOB',
        public readonly ?DateTimeImmutable $vigenteHasta = null,
        public readonly ?string $categoriaAlojamiento = null,
        public readonly ?string $nivelAnfitrion = null,
    ) {
    }
}

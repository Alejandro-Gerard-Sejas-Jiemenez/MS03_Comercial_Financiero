<?php

declare(strict_types=1);

namespace Src\Comercial\Application\UseCase\GestionarPromociones;

use DateTimeImmutable;
use Src\Comercial\Domain\Enum\RolCreador;
use Src\Comercial\Domain\Enum\TipoDescuento;
use Src\Comercial\Domain\Enum\TipoPromocion;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;

final class CrearPromocionRequest
{
    /** @param list<string> $alojamientos alojamientos alcanzados; vacio = todos */
    public function __construct(
        public readonly string $nombre,
        public readonly TipoPromocion $tipo,
        public readonly TipoDescuento $tipoDescuento,
        public readonly string $valor,
        public readonly DateTimeImmutable $vigenteDesde,
        public readonly DateTimeImmutable $vigenteHasta,
        public readonly RolCreador $creadaPorRol,
        public readonly string $creadaPor,
        public readonly ?string $codigo = null,
        public readonly ?string $anfitrionId = null,
        public readonly FinanciamientoDescuento $financiadaPor = FinanciamientoDescuento::ANFITRION,
        public readonly int $minNoches = 1,
        public readonly ?int $maxUsos = null,
        public readonly string $moneda = 'BOB',
        public readonly array $alojamientos = [],
    ) {}
}

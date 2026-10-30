<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\Response;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Entity\ReglaMonetizacion;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de ReglaMonetizacion: solo datos primitivos, sin reglas. */
final class ReglaMonetizacionResponse
{
    public function __construct(
        public readonly string $reglaId,
        public readonly string $nombre,
        public readonly string $concepto,
        public readonly string $tipoCalculo,
        public readonly ?string $porcentaje,
        public readonly ?MontoResponse $montoFijo,
        public readonly string $moneda,
        public readonly ?string $categoriaAlojamiento,
        public readonly ?string $nivelAnfitrion,
        public readonly DateTimeImmutable $vigenteDesde,
        public readonly ?DateTimeImmutable $vigenteHasta,
        public readonly string $estado,
        public readonly string $creadoPor,
    ) {}

    public static function desde(ReglaMonetizacion $e): self
    {
        return new self(
            reglaId: $e->reglaId(),
            nombre: $e->nombre(),
            concepto: $e->concepto()->value,
            tipoCalculo: $e->tipoCalculo()->value,
            porcentaje: $e->porcentaje(),
            montoFijo: MontoResponse::desdeOpcional($e->montoFijo()),
            moneda: $e->moneda(),
            categoriaAlojamiento: $e->categoriaAlojamiento(),
            nivelAnfitrion: $e->nivelAnfitrion(),
            vigenteDesde: $e->vigenteDesde(),
            vigenteHasta: $e->vigenteHasta(),
            estado: $e->estado()->value,
            creadoPor: $e->creadoPor(),
        );
    }

    public static function desdeOpcional(?ReglaMonetizacion $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

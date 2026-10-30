<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\Response;

use Src\Monetizacion\Domain\ValueObject\DesgloseComercial;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de DesgloseComercial: solo datos primitivos, sin reglas. */
final class DesgloseComercialResponse
{
    public function __construct(
        public readonly MontoResponse $subtotal,
        public readonly MontoResponse $descuento,
        public readonly MontoResponse $cargoServicio,
        public readonly MontoResponse $total,
        public readonly MontoResponse $baseComisionable,
        public readonly MontoResponse $montoComision,
        public readonly MontoResponse $netoAnfitrion,
        public readonly ?string $porcentajeAplicado,
        public readonly ?string $reglaComisionId,
        public readonly ?string $reglaCargoServicioId,
    ) {}

    public static function desde(DesgloseComercial $e): self
    {
        return new self(
            subtotal: MontoResponse::desde($e->subtotal),
            descuento: MontoResponse::desde($e->descuento),
            cargoServicio: MontoResponse::desde($e->cargoServicio),
            total: MontoResponse::desde($e->total),
            baseComisionable: MontoResponse::desde($e->baseComisionable),
            montoComision: MontoResponse::desde($e->montoComision),
            netoAnfitrion: MontoResponse::desde($e->netoAnfitrion),
            porcentajeAplicado: $e->porcentajeAplicado,
            reglaComisionId: $e->reglaComisionId,
            reglaCargoServicioId: $e->reglaCargoServicioId,
        );
    }

    public static function desdeOpcional(?DesgloseComercial $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

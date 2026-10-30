<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Application\UseCase\ProcesarPago\Cotizacion;
use Src\Monetizacion\Application\Response\DesgloseComercialResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de Cotizacion: solo datos primitivos, sin reglas. */
final class CotizacionResponse
{
    public function __construct(
        public readonly DesgloseComercialResponse $desglose,
        public readonly ?string $promocionId,
        public readonly ?string $acuerdoId,
    ) {}

    public static function desde(Cotizacion $e): self
    {
        return new self(
            desglose: DesgloseComercialResponse::desde($e->desglose),
            promocionId: $e->promocionId,
            acuerdoId: $e->acuerdoId,
        );
    }

    public static function desdeOpcional(?Cotizacion $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

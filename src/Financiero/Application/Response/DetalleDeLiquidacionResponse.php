<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Application\UseCase\ConsultarSaldosYReportes\DetalleDeLiquidacion;

/** Modelo de respuesta (Clean Architecture: Response Model) de DetalleDeLiquidacion: solo datos primitivos, sin reglas. */
final class DetalleDeLiquidacionResponse
{
    public function __construct(
        public readonly LiquidacionResponse $liquidacion,
        /** @var list<ComisionResponse> */
        public readonly array $comisiones,
    ) {}

    public static function desde(DetalleDeLiquidacion $e): self
    {
        return new self(
            liquidacion: LiquidacionResponse::desde($e->liquidacion),
            comisiones: array_map(ComisionResponse::desde(...), $e->comisiones),
        );
    }

    public static function desdeOpcional(?DetalleDeLiquidacion $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

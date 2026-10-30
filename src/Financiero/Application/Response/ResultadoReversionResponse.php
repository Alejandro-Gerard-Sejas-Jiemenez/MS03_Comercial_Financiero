<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Application\UseCase\GestionarComisiones\ResultadoReversion;

/** Modelo de respuesta (Clean Architecture: Response Model) de ResultadoReversion: solo datos primitivos, sin reglas. */
final class ResultadoReversionResponse
{
    public function __construct(
        public readonly ?ComisionResponse $comision,
        public readonly bool $revertida,
        public readonly bool $requiereAjusteEnLaSiguienteLiquidacion,
        public readonly string $detalle,
    ) {}

    public static function desde(ResultadoReversion $e): self
    {
        return new self(
            comision: ComisionResponse::desdeOpcional($e->comision),
            revertida: $e->revertida,
            requiereAjusteEnLaSiguienteLiquidacion: $e->requiereAjusteEnLaSiguienteLiquidacion,
            detalle: $e->detalle,
        );
    }

    public static function desdeOpcional(?ResultadoReversion $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

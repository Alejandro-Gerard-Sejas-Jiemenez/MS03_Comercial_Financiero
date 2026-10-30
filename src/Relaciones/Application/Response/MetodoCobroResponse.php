<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\Response;

use Src\Relaciones\Domain\Entity\MetodoCobro;

/** Modelo de respuesta (Clean Architecture: Response Model) de MetodoCobro: solo datos primitivos, sin reglas. */
final class MetodoCobroResponse
{
    public function __construct(
        public readonly string $metodoId,
        public readonly string $anfitrionId,
        public readonly string $tipo,
        public readonly ?string $banco,
        public readonly string $titular,
        public readonly ?string $ultimosDigitos,
        public readonly bool $esPrincipal,
        public readonly bool $verificado,
        public readonly bool $activo,
    ) {}

    public static function desde(MetodoCobro $e): self
    {
        return new self(
            metodoId: $e->metodoId(),
            anfitrionId: $e->anfitrionId(),
            tipo: $e->tipo()->value,
            banco: $e->banco(),
            titular: $e->titular(),
            ultimosDigitos: $e->ultimosDigitos(),
            esPrincipal: $e->esPrincipal(),
            verificado: $e->estaVerificado(),
            activo: $e->estaActivo(),
        );
    }

    public static function desdeOpcional(?MetodoCobro $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Application\Port\IngresoPorCategoria;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de IngresoPorCategoria: solo datos primitivos, sin reglas. */
final class IngresoPorCategoriaResponse
{
    public function __construct(
        public readonly ?string $categoriaAlojamiento,
        public readonly MontoResponse $totalCobrado,
        public readonly MontoResponse $totalComisiones,
        public readonly int $cantidadPagos,
    ) {}

    public static function desde(IngresoPorCategoria $e): self
    {
        return new self(
            categoriaAlojamiento: $e->categoriaAlojamiento,
            totalCobrado: MontoResponse::desde($e->totalCobrado),
            totalComisiones: MontoResponse::desde($e->totalComisiones),
            cantidadPagos: $e->cantidadPagos,
        );
    }

    public static function desdeOpcional(?IngresoPorCategoria $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

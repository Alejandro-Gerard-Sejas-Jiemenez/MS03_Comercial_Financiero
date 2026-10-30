<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Application\Port\ResumenDeIngresos;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de ResumenDeIngresos: solo datos primitivos, sin reglas. */
final class ResumenDeIngresosResponse
{
    public function __construct(
        public readonly MontoResponse $totalCobrado,
        public readonly MontoResponse $totalComisiones,
        public readonly MontoResponse $totalCargosServicio,
        public readonly MontoResponse $totalReembolsos,
        public readonly MontoResponse $totalNetoAnfitriones,
        public readonly int $cantidadPagos,
    ) {}

    public static function desde(ResumenDeIngresos $e): self
    {
        return new self(
            totalCobrado: MontoResponse::desde($e->totalCobrado),
            totalComisiones: MontoResponse::desde($e->totalComisiones),
            totalCargosServicio: MontoResponse::desde($e->totalCargosServicio),
            totalReembolsos: MontoResponse::desde($e->totalReembolsos),
            totalNetoAnfitriones: MontoResponse::desde($e->totalNetoAnfitriones),
            cantidadPagos: $e->cantidadPagos,
        );
    }

    public static function desdeOpcional(?ResumenDeIngresos $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

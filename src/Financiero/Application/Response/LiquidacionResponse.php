<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Liquidacion;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de Liquidacion: solo datos primitivos, sin reglas. */
final class LiquidacionResponse
{
    public function __construct(
        public readonly string $liquidacionId,
        public readonly string $anfitrionId,
        public readonly ?string $metodoId,
        public readonly DateTimeImmutable $periodoDesde,
        public readonly DateTimeImmutable $periodoHasta,
        public readonly MontoResponse $totalBruto,
        public readonly MontoResponse $totalComisiones,
        public readonly MontoResponse $totalReembolsos,
        public readonly string $estado,
        public readonly ?string $aprobadoPor,
        public readonly ?string $referenciaTransferencia,
    ) {}

    public static function desde(Liquidacion $e): self
    {
        return new self(
            liquidacionId: $e->liquidacionId(),
            anfitrionId: $e->anfitrionId(),
            metodoId: $e->metodoId(),
            periodoDesde: $e->periodoDesde(),
            periodoHasta: $e->periodoHasta(),
            totalBruto: MontoResponse::desde($e->totalBruto()),
            totalComisiones: MontoResponse::desde($e->totalComisiones()),
            totalReembolsos: MontoResponse::desde($e->totalReembolsos()),
            estado: $e->estado()->value,
            aprobadoPor: $e->aprobadoPor(),
            referenciaTransferencia: $e->referenciaTransferencia(),
        );
    }

    public static function desdeOpcional(?Liquidacion $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

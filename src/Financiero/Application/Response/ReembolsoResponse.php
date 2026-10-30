<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Domain\Entity\Reembolso;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de Reembolso: solo datos primitivos, sin reglas. */
final class ReembolsoResponse
{
    public function __construct(
        public readonly string $reembolsoId,
        public readonly string $pagoId,
        public readonly MontoResponse $monto,
        public readonly string $motivo,
        public readonly string $estado,
        public readonly ?string $detalle,
        public readonly ?string $solicitadoPor,
        public readonly ?string $transaccionId,
    ) {}

    public static function desde(Reembolso $e): self
    {
        return new self(
            reembolsoId: $e->reembolsoId(),
            pagoId: $e->pagoId(),
            monto: MontoResponse::desde($e->monto()),
            motivo: $e->motivo()->value,
            estado: $e->estado()->value,
            detalle: $e->detalle(),
            solicitadoPor: $e->solicitadoPor(),
            transaccionId: $e->transaccionId(),
        );
    }

    public static function desdeOpcional(?Reembolso $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

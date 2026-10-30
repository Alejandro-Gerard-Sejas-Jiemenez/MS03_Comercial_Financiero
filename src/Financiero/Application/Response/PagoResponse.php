<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Domain\Entity\Pago;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de Pago: solo datos primitivos, sin reglas. */
final class PagoResponse
{
    public function __construct(
        public readonly string $pagoId,
        public readonly string $reservaId,
        public readonly string $clienteId,
        public readonly string $anfitrionId,
        public readonly ?string $promocionId,
        public readonly MontoResponse $subtotal,
        public readonly MontoResponse $descuento,
        public readonly MontoResponse $cargoServicio,
        public readonly string $metodo,
        public readonly string $estado,
        public readonly string $idempotencyKey,
        public readonly ?string $sagaId,
        public readonly ?string $pasarela,
        public readonly ?string $referenciaPasarela,
    ) {}

    public static function desde(Pago $e): self
    {
        return new self(
            pagoId: $e->pagoId(),
            reservaId: $e->reservaId(),
            clienteId: $e->clienteId(),
            anfitrionId: $e->anfitrionId(),
            promocionId: $e->promocionId(),
            subtotal: MontoResponse::desde($e->subtotal()),
            descuento: MontoResponse::desde($e->descuento()),
            cargoServicio: MontoResponse::desde($e->cargoServicio()),
            metodo: $e->metodo()->value,
            estado: $e->estado()->value,
            idempotencyKey: $e->idempotencyKey(),
            sagaId: $e->sagaId(),
            pasarela: $e->pasarela(),
            referenciaPasarela: $e->referenciaPasarela(),
        );
    }

    public static function desdeOpcional(?Pago $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

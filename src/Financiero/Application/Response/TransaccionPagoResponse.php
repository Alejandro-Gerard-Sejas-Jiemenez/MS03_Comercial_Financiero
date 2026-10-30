<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Domain\Entity\TransaccionPago;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de TransaccionPago: solo datos primitivos, sin reglas. */
final class TransaccionPagoResponse
{
    public function __construct(
        public readonly string $transaccionId,
        public readonly string $pagoId,
        public readonly string $tipo,
        public readonly MontoResponse $monto,
        public readonly string $estado,
        public readonly ?string $referenciaPasarela,
        public readonly ?array $respuestaPasarela,
    ) {}

    public static function desde(TransaccionPago $e): self
    {
        return new self(
            transaccionId: $e->transaccionId,
            pagoId: $e->pagoId,
            tipo: $e->tipo->value,
            monto: MontoResponse::desde($e->monto),
            estado: $e->estado->value,
            referenciaPasarela: $e->referenciaPasarela,
            respuestaPasarela: $e->respuestaPasarela,
        );
    }

    public static function desdeOpcional(?TransaccionPago $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

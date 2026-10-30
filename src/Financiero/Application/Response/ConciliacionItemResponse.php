<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Domain\Entity\ConciliacionItem;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de ConciliacionItem: solo datos primitivos, sin reglas. */
final class ConciliacionItemResponse
{
    public function __construct(
        public readonly string $referenciaPasarela,
        public readonly MontoResponse $montoPasarela,
        public readonly string $resultado,
        public readonly ?string $transaccionId,
        public readonly ?string $observacion,
        public readonly ?string $itemId,
    ) {}

    public static function desde(ConciliacionItem $e): self
    {
        return new self(
            referenciaPasarela: $e->referenciaPasarela(),
            montoPasarela: MontoResponse::desde($e->montoPasarela()),
            resultado: $e->resultado()->value,
            transaccionId: $e->transaccionId(),
            observacion: $e->observacion(),
            itemId: $e->itemId(),
        );
    }

    public static function desdeOpcional(?ConciliacionItem $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

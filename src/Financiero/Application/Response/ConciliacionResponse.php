<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Conciliacion;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de Conciliacion: solo datos primitivos, sin reglas. */
final class ConciliacionResponse
{
    public function __construct(
        public readonly string $conciliacionId,
        public readonly string $pasarela,
        public readonly DateTimeImmutable $fechaCorte,
        public readonly MontoResponse $totalSistema,
        public readonly MontoResponse $totalPasarela,
        public readonly string $estado,
        public readonly ?string $ejecutadaPor,
        /** @var list<ConciliacionItemResponse> */
        public readonly array $items,
    ) {}

    public static function desde(Conciliacion $e): self
    {
        return new self(
            conciliacionId: $e->conciliacionId(),
            pasarela: $e->pasarela(),
            fechaCorte: $e->fechaCorte(),
            totalSistema: MontoResponse::desde($e->totalSistema()),
            totalPasarela: MontoResponse::desde($e->totalPasarela()),
            estado: $e->estado()->value,
            ejecutadaPor: $e->ejecutadaPor(),
            items: array_map(ConciliacionItemResponse::desde(...), $e->items()),
        );
    }

    public static function desdeOpcional(?Conciliacion $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\ValueObject;

use DateTimeImmutable;
use Src\Financiero\Domain\Enum\EstadoPago;

/**
 * Filtros de ConsultarPagos. Quien consulta y con que rol lo decide
 * el controlador; aqui solo viajan los filtros ya resueltos.
 */
final class CriterioDePagos
{
    public function __construct(
        public readonly ?string $clienteId = null,
        public readonly ?string $anfitrionId = null,
        public readonly ?string $reservaId = null,
        public readonly ?EstadoPago $estado = null,
        public readonly ?DateTimeImmutable $desde = null,
        public readonly ?DateTimeImmutable $hasta = null,
        public readonly int $limite = 50,
    ) {}
}

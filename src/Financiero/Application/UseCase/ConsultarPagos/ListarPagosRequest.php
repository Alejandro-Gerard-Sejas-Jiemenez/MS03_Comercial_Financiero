<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConsultarPagos;

use DateTimeImmutable;
use Src\Financiero\Domain\Enum\EstadoPago;

/**
 * Lo que pide quien lista pagos y con que papel lo hace. Un ADMIN ve todo con los
 * filtros que quiera; cualquier otro usuario solo ve lo suyo, como `cliente` o
 * como `anfitrion`, y el caso de uso ignora los filtros de persona que mande.
 */
final class ListarPagosRequest
{
    public const COMO_CLIENTE = 'cliente';

    public const COMO_ANFITRION = 'anfitrion';

    public function __construct(
        public readonly string $usuarioId,
        public readonly bool $esAdmin,
        public readonly string $como = self::COMO_CLIENTE,
        public readonly ?string $clienteId = null,
        public readonly ?string $anfitrionId = null,
        public readonly ?string $reservaId = null,
        public readonly ?EstadoPago $estado = null,
        public readonly ?DateTimeImmutable $desde = null,
        public readonly ?DateTimeImmutable $hasta = null,
        public readonly int $limite = 50,
    ) {}
}

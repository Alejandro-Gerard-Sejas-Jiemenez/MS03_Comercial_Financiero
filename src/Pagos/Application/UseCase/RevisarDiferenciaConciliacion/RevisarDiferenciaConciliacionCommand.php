<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\RevisarDiferenciaConciliacion;

final class RevisarDiferenciaConciliacionCommand
{
    public function __construct(
        public readonly string $conciliacionId,
        /** Identidad del item dentro de la conciliacion. */
        public readonly string $referenciaPasarela,
        public readonly string $observacion,
        public readonly string $revisadoPor,
        /** Si la diferencia era un item mal ligado, aqui va la transaccion correcta. */
        public readonly ?string $transaccionId = null,
        /** Cierra la conciliacion si ya no queda nada por explicar. */
        public readonly bool $cerrar = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

/**
 * Transaccion de base de datos vista desde la capa de Aplicacion, para que los
 * casos de uso no importen `Illuminate\Support\Facades\DB`.
 */
interface TransactionManager
{
    /**
     * @template T
     * @param callable():T $operacion
     * @return T
     */
    public function ejecutar(callable $operacion): mixed;
}

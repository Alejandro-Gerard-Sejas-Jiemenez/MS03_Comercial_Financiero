<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Shared\Application\Port\TransactionManager;
use Throwable;

/**
 * Ejecuta la operacion sin base de datos y cuenta las transacciones abiertas,
 * para poder afirmar que el caso de uso agrupo sus escrituras en una sola.
 */
final class SyncTransactionManager implements TransactionManager
{
    private int $transacciones = 0;

    private int $revertidas = 0;

    public function ejecutar(callable $operacion): mixed
    {
        ++$this->transacciones;

        try {
            return $operacion();
        } catch (Throwable $e) {
            ++$this->revertidas;

            throw $e;
        }
    }

    public function transacciones(): int
    {
        return $this->transacciones;
    }

    public function revertidas(): int
    {
        return $this->revertidas;
    }
}

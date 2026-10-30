<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence\Service;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Port\TransactionManager;

final class EloquentTransactionManager implements TransactionManager
{
    public function ejecutar(callable $operacion): mixed
    {
        return DB::transaction($operacion);
    }
}

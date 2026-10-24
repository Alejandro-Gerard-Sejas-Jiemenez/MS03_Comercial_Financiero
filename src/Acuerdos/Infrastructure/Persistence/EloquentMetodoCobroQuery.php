<?php

declare(strict_types=1);

namespace Src\Acuerdos\Infrastructure\Persistence;

use Src\Acuerdos\Application\Port\MetodoCobroQuery;

/**
 * Consulta de lectura para Liquidaciones: devuelve solo el `metodo_id`, nunca
 * el numero cifrado ni los datos de la cuenta.
 */
final class EloquentMetodoCobroQuery implements MetodoCobroQuery
{
    public function metodoPrincipalVerificado(string $anfitrionId): ?string
    {
        $metodoId = MetodoCobroModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->where('es_principal', true)
            ->where('verificado', true)
            ->value('metodo_id');

        return $metodoId === null ? null : (string) $metodoId;
    }
}

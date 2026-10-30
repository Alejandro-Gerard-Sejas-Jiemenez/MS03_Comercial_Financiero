<?php

declare(strict_types=1);

namespace Src\Relaciones\Infrastructure\Persistence\Query;

use Src\Relaciones\Application\Port\MetodoCobroQuery;
use Src\Relaciones\Infrastructure\Persistence\Model\MetodoCobroModel;

/**
 * Consulta de lectura para Financiero: devuelve solo el `metodo_id`, nunca
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

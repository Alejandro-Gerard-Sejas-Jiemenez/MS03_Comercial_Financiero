<?php

declare(strict_types=1);

namespace Src\Acuerdos\Infrastructure\Persistence;

use Src\Acuerdos\Application\Port\AcuerdoComercialQuery;
use Src\Acuerdos\Application\Port\AcuerdoVigente;
use Src\Acuerdos\Domain\EstadoAcuerdo;

/**
 * Consulta de lectura para Liquidaciones y Pagos. Lee solo las tablas de
 * Acuerdos y devuelve una estructura de datos simple: por la frontera nunca
 * pasa un modelo de Eloquent ni la entidad.
 */
final class EloquentAcuerdoComercialQuery implements AcuerdoComercialQuery
{
    public function acuerdoActivoDe(string $anfitrionId): ?AcuerdoVigente
    {
        $fila = AcuerdoComercialModel::query()
            ->select(['acuerdo_id', 'anfitrion_id', 'dias_liquidacion', 'regla_comision_id'])
            ->where('anfitrion_id', $anfitrionId)
            ->where('estado', EstadoAcuerdo::ACTIVO->value)
            ->first();

        if ($fila === null) {
            return null;
        }

        return new AcuerdoVigente(
            acuerdoId: $fila->acuerdo_id,
            anfitrionId: $fila->anfitrion_id,
            diasLiquidacion: (int) $fila->dias_liquidacion,
            reglaComisionId: $fila->regla_comision_id,
        );
    }
}

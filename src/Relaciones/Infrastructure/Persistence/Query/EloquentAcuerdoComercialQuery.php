<?php

declare(strict_types=1);

namespace Src\Relaciones\Infrastructure\Persistence\Query;

use Src\Relaciones\Application\Port\AcuerdoComercialQuery;
use Src\Relaciones\Application\Port\AcuerdoVigente;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;
use Src\Relaciones\Infrastructure\Persistence\Model\AcuerdoComercialModel;

/**
 * Consulta de lectura para Financiero. Lee solo las tablas de
 * acuerdos y devuelve una estructura de datos simple: por la frontera nunca
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

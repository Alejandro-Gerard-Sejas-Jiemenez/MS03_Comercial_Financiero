<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\CarbonImmutable;

/**
 * Tabla `comercial.comision`.
 *
 * @property string $comision_id
 * @property string $pago_id
 * @property string $reserva_id
 * @property string $anfitrion_id
 * @property string|null $regla_id
 * @property string|null $acuerdo_id
 * @property string|null $liquidacion_id
 * @property string $base_comisionable
 * @property string|null $porcentaje_aplicado
 * @property string $monto_comision
 * @property string $monto_neto_anfitrion
 * @property string $moneda
 * @property string $estado
 * @property CarbonImmutable|null $liquidable_desde
 */
final class ComisionModel extends Model
{
    protected $table = 'comision';

    protected $primaryKey = 'comision_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'comision_id',
        'pago_id',
        'reserva_id',
        'anfitrion_id',
        'regla_id',
        'acuerdo_id',
        'liquidacion_id',
        'base_comisionable',
        'porcentaje_aplicado',
        'monto_comision',
        'monto_neto_anfitrion',
        'moneda',
        'estado',
        'liquidable_desde',
    ];

    protected $casts = [
        'liquidable_desde' => 'immutable_date',
    ];
}

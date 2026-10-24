<?php

declare(strict_types=1);

namespace Src\Acuerdos\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.acuerdo_comercial`.
 *
 * Termina en `Model` para no confundirlo con la entidad `AcuerdoComercial`
 * del Dominio, como pide la convencion de nombres del documento.
 *
 * @property string $acuerdo_id
 * @property string $anfitrion_id
 * @property string|null $regla_comision_id
 * @property string $estado
 * @property \Illuminate\Support\CarbonImmutable $fecha_inicio
 * @property \Illuminate\Support\CarbonImmutable|null $fecha_fin
 * @property array<string, mixed> $condiciones
 * @property int $dias_liquidacion
 * @property string|null $aprobado_por
 * @property \Illuminate\Support\CarbonImmutable|null $aprobado_en
 * @property string|null $motivo_estado
 */
final class AcuerdoComercialModel extends Model
{
    protected $table = 'acuerdo_comercial';

    protected $primaryKey = 'acuerdo_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'acuerdo_id',
        'anfitrion_id',
        'regla_comision_id',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'condiciones',
        'dias_liquidacion',
        'aprobado_por',
        'aprobado_en',
        'motivo_estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'immutable_date',
        'fecha_fin' => 'immutable_date',
        'condiciones' => 'array',
        'dias_liquidacion' => 'integer',
        'aprobado_en' => 'immutable_datetime',
    ];
}

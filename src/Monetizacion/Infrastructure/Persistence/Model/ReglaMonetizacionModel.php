<?php

declare(strict_types=1);

namespace Src\Monetizacion\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\CarbonImmutable;

/**
 * Tabla `comercial.regla_monetizacion`.
 *
 * Termina en `Model` para no confundirlo con la entidad `ReglaMonetizacion`
 * del Dominio, como pide la convencion de nombres del documento.
 *
 * @property string $regla_id
 * @property string $nombre
 * @property string $concepto
 * @property string $tipo_calculo
 * @property string|null $porcentaje
 * @property string|null $monto_fijo
 * @property string $moneda
 * @property string|null $categoria_alojamiento
 * @property string|null $nivel_anfitrion
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable|null $vigente_hasta
 * @property string $estado
 * @property string $creado_por
 */
final class ReglaMonetizacionModel extends Model
{
    protected $table = 'regla_monetizacion';

    protected $primaryKey = 'regla_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'regla_id',
        'nombre',
        'concepto',
        'tipo_calculo',
        'porcentaje',
        'monto_fijo',
        'moneda',
        'categoria_alojamiento',
        'nivel_anfitrion',
        'vigente_desde',
        'vigente_hasta',
        'estado',
        'creado_por',
    ];

    protected $casts = [
        'vigente_desde' => 'immutable_date',
        'vigente_hasta' => 'immutable_date',
    ];
}

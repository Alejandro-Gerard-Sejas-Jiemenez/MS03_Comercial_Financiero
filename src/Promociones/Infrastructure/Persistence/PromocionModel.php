<?php

declare(strict_types=1);

namespace Src\Promociones\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\CarbonImmutable;

/**
 * Tabla `comercial.promocion`.
 *
 * Termina en `Model` para no confundirlo con la entidad `Promocion` del
 * Dominio, como pide la convencion de nombres del documento.
 *
 * @property string $promocion_id
 * @property string|null $codigo
 * @property string $nombre
 * @property string $tipo
 * @property string $tipo_descuento
 * @property string $valor
 * @property string $moneda
 * @property string $creada_por_rol
 * @property string $creada_por
 * @property string|null $anfitrion_id
 * @property string $financiada_por
 * @property int $min_noches
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable $vigente_hasta
 * @property int|null $max_usos
 * @property int $usos_actuales
 * @property string $estado
 */
final class PromocionModel extends Model
{
    protected $table = 'promocion';

    protected $primaryKey = 'promocion_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'promocion_id',
        'codigo',
        'nombre',
        'tipo',
        'tipo_descuento',
        'valor',
        'moneda',
        'creada_por_rol',
        'creada_por',
        'anfitrion_id',
        'financiada_por',
        'min_noches',
        'vigente_desde',
        'vigente_hasta',
        'max_usos',
        'usos_actuales',
        'estado',
    ];

    protected $casts = [
        'vigente_desde' => 'immutable_datetime',
        'vigente_hasta' => 'immutable_datetime',
        'min_noches' => 'integer',
        'max_usos' => 'integer',
        'usos_actuales' => 'integer',
    ];
}

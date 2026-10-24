<?php

declare(strict_types=1);

namespace Src\Promociones\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.promocion_alojamiento`: los alojamientos alcanzados por una
 * promocion. Sin filas, la promocion alcanza a todos los del anfitrion (o de
 * la plataforma).
 *
 * La llave primaria es compuesta (`promocion_id`, `alojamiento_id`) y la tabla
 * no tiene timestamps, asi que el repositorio la usa solo con consultas,
 * inserciones y borrados, nunca con `find()` ni `save()`.
 *
 * @property string $promocion_id
 * @property string $alojamiento_id
 */
final class PromocionAlojamientoModel extends Model
{
    protected $table = 'promocion_alojamiento';

    protected $primaryKey = 'promocion_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'promocion_id',
        'alojamiento_id',
    ];
}

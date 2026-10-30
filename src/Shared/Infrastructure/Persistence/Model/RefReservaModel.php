<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\CarbonImmutable;

/**
 * Tabla `comercial.ref_reserva` (copia local alimentada por eventos de MS-02).
 *
 * `noches` es una columna generada por PostgreSQL: no se escribe.
 *
 * @property string $reserva_id
 * @property string $cliente_id
 * @property string $anfitrion_id
 * @property string $alojamiento_id
 * @property string|null $categoria_alojamiento
 * @property CarbonImmutable $fecha_checkin
 * @property CarbonImmutable $fecha_checkout
 * @property int $noches
 * @property string $estado
 */
final class RefReservaModel extends Model
{
    protected $table = 'ref_reserva';

    protected $primaryKey = 'reserva_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'reserva_id',
        'cliente_id',
        'anfitrion_id',
        'alojamiento_id',
        'categoria_alojamiento',
        'fecha_checkin',
        'fecha_checkout',
        'estado',
        'sincronizado_en',
    ];

    protected $casts = [
        'fecha_checkin' => 'immutable_date',
        'fecha_checkout' => 'immutable_date',
        'noches' => 'integer',
    ];
}

<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.conciliacion_item`.
 *
 * @property string $item_id
 * @property string $conciliacion_id
 * @property string|null $transaccion_id
 * @property string $referencia_pasarela
 * @property string $monto_pasarela
 * @property string $resultado
 * @property string|null $observacion
 */
final class ConciliacionItemModel extends Model
{
    protected $table = 'conciliacion_item';

    protected $primaryKey = 'item_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'conciliacion_id',
        'transaccion_id',
        'referencia_pasarela',
        'monto_pasarela',
        'resultado',
        'observacion',
    ];
}

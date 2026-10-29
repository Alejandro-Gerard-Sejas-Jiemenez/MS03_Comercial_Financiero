<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\CarbonImmutable;

/**
 * Tabla `comercial.conciliacion`. `diferencia` es columna generada: no se escribe.
 *
 * @property string $conciliacion_id
 * @property string $pasarela
 * @property CarbonImmutable $fecha_corte
 * @property string $total_sistema
 * @property string $total_pasarela
 * @property string $diferencia
 * @property string $estado
 * @property string|null $ejecutada_por
 */
final class ConciliacionModel extends Model
{
    protected $table = 'conciliacion';

    protected $primaryKey = 'conciliacion_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'conciliacion_id',
        'pasarela',
        'fecha_corte',
        'total_sistema',
        'total_pasarela',
        'estado',
        'ejecutada_por',
    ];

    protected $casts = [
        'fecha_corte' => 'immutable_date',
    ];

    /** @return HasMany<ConciliacionItemModel, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ConciliacionItemModel::class, 'conciliacion_id', 'conciliacion_id');
    }
}

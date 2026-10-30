<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\CarbonImmutable;

/**
 * Tabla `comercial.liquidacion`. `total_neto` lo escribe el repositorio y la
 * base lo verifica con un CHECK contra bruto, comisiones y reembolsos.
 *
 * @property string $liquidacion_id
 * @property string $anfitrion_id
 * @property string|null $metodo_id
 * @property CarbonImmutable $periodo_desde
 * @property CarbonImmutable $periodo_hasta
 * @property string $total_bruto
 * @property string $total_comisiones
 * @property string $total_reembolsos
 * @property string $total_neto
 * @property string $moneda
 * @property string $estado
 * @property string|null $aprobado_por
 * @property string|null $referencia_transferencia
 */
final class LiquidacionModel extends Model
{
    protected $table = 'liquidacion';

    protected $primaryKey = 'liquidacion_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'liquidacion_id',
        'anfitrion_id',
        'metodo_id',
        'periodo_desde',
        'periodo_hasta',
        'total_bruto',
        'total_comisiones',
        'total_reembolsos',
        'total_neto',
        'moneda',
        'estado',
        'aprobado_por',
        'aprobado_en',
        'pagado_en',
        'referencia_transferencia',
    ];

    protected $casts = [
        'periodo_desde' => 'immutable_date',
        'periodo_hasta' => 'immutable_date',
        'aprobado_en' => 'immutable_datetime',
        'pagado_en' => 'immutable_datetime',
    ];
}

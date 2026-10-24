<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.movimiento_contable`: libro de solo insercion. Un trigger de
 * PostgreSQL rechaza UPDATE y DELETE, asi que este modelo solo inserta y lee.
 *
 * @property int $movimiento_id
 * @property string $asiento_id
 * @property string $cuenta
 * @property string $tipo
 * @property string $monto
 * @property string $moneda
 * @property string $origen_tipo
 * @property string $origen_id
 * @property string|null $anfitrion_id
 */
final class MovimientoContableModel extends Model
{
    protected $table = 'movimiento_contable';

    protected $primaryKey = 'movimiento_id';

    public $timestamps = false;

    protected $fillable = [
        'asiento_id',
        'cuenta',
        'tipo',
        'monto',
        'moneda',
        'origen_tipo',
        'origen_id',
        'anfitrion_id',
    ];
}

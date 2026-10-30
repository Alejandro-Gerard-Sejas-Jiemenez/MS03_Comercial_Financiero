<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.reembolso`.
 *
 * @property string $reembolso_id
 * @property string $pago_id
 * @property string|null $transaccion_id
 * @property string $monto
 * @property string $motivo
 * @property string|null $detalle
 * @property string $estado
 * @property string|null $solicitado_por
 */
final class ReembolsoModel extends Model
{
    protected $table = 'reembolso';

    protected $primaryKey = 'reembolso_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'reembolso_id',
        'pago_id',
        'transaccion_id',
        'monto',
        'motivo',
        'detalle',
        'estado',
        'solicitado_por',
    ];
}

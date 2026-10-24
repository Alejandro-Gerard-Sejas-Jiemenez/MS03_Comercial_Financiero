<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.pago`. Detalle de infraestructura: termina en `Model` para
 * no confundirlo con la entidad `Pago` del Dominio.
 *
 * @property string $pago_id
 * @property string $reserva_id
 * @property string $cliente_id
 * @property string $anfitrion_id
 * @property string|null $promocion_id
 * @property string $subtotal_alojamiento
 * @property string $descuento
 * @property string $cargo_servicio
 * @property string $monto_total
 * @property string $moneda
 * @property string $metodo
 * @property string|null $pasarela
 * @property string $estado
 * @property string $idempotency_key
 * @property string|null $saga_id
 */
final class PagoModel extends Model
{
    protected $table = 'pago';

    protected $primaryKey = 'pago_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'pago_id',
        'reserva_id',
        'cliente_id',
        'anfitrion_id',
        'promocion_id',
        'subtotal_alojamiento',
        'descuento',
        'cargo_servicio',
        'monto_total',
        'moneda',
        'metodo',
        'pasarela',
        'estado',
        'idempotency_key',
        'saga_id',
        'pagado_en',
    ];

    protected $casts = [
        'pagado_en' => 'immutable_datetime',
    ];
}

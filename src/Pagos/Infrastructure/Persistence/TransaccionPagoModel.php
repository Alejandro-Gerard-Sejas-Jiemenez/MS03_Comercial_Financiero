<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.transaccion_pago`. Append-only: un trigger de PostgreSQL
 * rechaza UPDATE y DELETE, asi que este modelo solo inserta y consulta.
 *
 * @property string $transaccion_id
 * @property string $pago_id
 * @property string $tipo
 * @property string $monto
 * @property string $estado
 * @property string|null $referencia_pasarela
 * @property array<string, mixed>|null $respuesta_pasarela
 */
final class TransaccionPagoModel extends Model
{
    protected $table = 'transaccion_pago';

    protected $primaryKey = 'transaccion_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** Solo tiene created_at, con DEFAULT now() en la base. */
    public $timestamps = false;

    protected $fillable = [
        'transaccion_id',
        'pago_id',
        'tipo',
        'monto',
        'estado',
        'referencia_pasarela',
        'respuesta_pasarela',
    ];

    protected $casts = [
        'respuesta_pasarela' => 'array',
    ];
}

<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.comprobante`.
 *
 * @property string $comprobante_id
 * @property string $pago_id
 * @property string $tipo
 * @property string $numero
 * @property string|null $nit_cliente
 * @property string|null $razon_social
 * @property string $monto
 * @property string|null $archivo_url
 * @property string|null $hash_documento
 */
final class ComprobanteModel extends Model
{
    protected $table = 'comprobante';

    protected $primaryKey = 'comprobante_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** Solo tiene `emitido_en`, con DEFAULT now(). */
    public $timestamps = false;

    protected $fillable = [
        'comprobante_id',
        'pago_id',
        'tipo',
        'numero',
        'nit_cliente',
        'razon_social',
        'monto',
        'archivo_url',
        'hash_documento',
    ];
}

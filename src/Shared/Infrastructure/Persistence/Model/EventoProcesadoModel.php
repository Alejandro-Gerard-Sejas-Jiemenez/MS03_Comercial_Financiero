<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.evento_procesado` (inbox de consumidores idempotentes).
 *
 * @property string $mensaje_id
 * @property string $tipo_evento
 * @property string $origen
 */
final class EventoProcesadoModel extends Model
{
    protected $table = 'evento_procesado';

    protected $primaryKey = 'mensaje_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['mensaje_id', 'tipo_evento', 'origen'];
}

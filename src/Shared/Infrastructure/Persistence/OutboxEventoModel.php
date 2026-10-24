<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.outbox_evento`. Detalle de infraestructura: el dominio no la conoce.
 *
 * @property string $evento_id
 * @property string $agregado_tipo
 * @property string $agregado_id
 * @property string $tipo_evento
 * @property array<string, mixed> $payload
 * @property string|null $saga_id
 * @property string|null $publicado_en
 * @property int $intentos
 */
final class OutboxEventoModel extends Model
{
    protected $table = 'outbox_evento';

    protected $primaryKey = 'evento_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** La tabla solo tiene created_at; lo pone PostgreSQL con DEFAULT now(). */
    public $timestamps = false;

    protected $fillable = [
        'evento_id',
        'agregado_tipo',
        'agregado_id',
        'tipo_evento',
        'payload',
        'saga_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'intentos' => 'integer',
    ];
}

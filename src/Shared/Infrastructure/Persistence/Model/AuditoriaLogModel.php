<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.auditoria_log`.
 *
 * @property int $auditoria_id
 * @property string|null $usuario_id
 * @property string $accion
 * @property string $entidad
 * @property string|null $entidad_id
 * @property array<string, mixed>|null $antes
 * @property array<string, mixed>|null $despues
 */
final class AuditoriaLogModel extends Model
{
    protected $table = 'auditoria_log';

    protected $primaryKey = 'auditoria_id';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'accion',
        'entidad',
        'entidad_id',
        'antes',
        'despues',
        'ip',
    ];

    protected $casts = [
        'antes' => 'array',
        'despues' => 'array',
    ];
}

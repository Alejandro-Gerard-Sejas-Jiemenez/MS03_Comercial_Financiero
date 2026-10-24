<?php

declare(strict_types=1);

namespace Src\Referencias\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.ref_anfitrion` (copia local alimentada por eventos de MS-01).
 *
 * @property string $anfitrion_id
 * @property string $nombre_completo
 * @property string|null $nit
 * @property string|null $razon_social
 * @property string $estado_verificacion
 */
final class RefAnfitrionModel extends Model
{
    protected $table = 'ref_anfitrion';

    protected $primaryKey = 'anfitrion_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** Solo tiene `sincronizado_en`, que se escribe a mano en el repositorio. */
    public $timestamps = false;

    protected $fillable = [
        'anfitrion_id',
        'nombre_completo',
        'nit',
        'razon_social',
        'estado_verificacion',
        'sincronizado_en',
    ];
}

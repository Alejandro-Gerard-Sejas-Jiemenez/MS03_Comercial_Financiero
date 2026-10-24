<?php

declare(strict_types=1);

namespace Src\Acuerdos\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `comercial.metodo_cobro_anfitrion`.
 *
 * `numero_cifrado` guarda el valor que produjo el puerto `CifradorDeDatos`;
 * en texto claro solo esta `ultimos_digitos`. El modelo nunca descifra: eso lo
 * decide el caso de uso.
 *
 * @property string $metodo_id
 * @property string $anfitrion_id
 * @property string $tipo
 * @property string|null $banco
 * @property string $titular
 * @property string $numero_cifrado
 * @property string|null $ultimos_digitos
 * @property bool $es_principal
 * @property bool $verificado
 */
final class MetodoCobroModel extends Model
{
    protected $table = 'metodo_cobro_anfitrion';

    protected $primaryKey = 'metodo_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'metodo_id',
        'anfitrion_id',
        'tipo',
        'banco',
        'titular',
        'numero_cifrado',
        'ultimos_digitos',
        'es_principal',
        'verificado',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'verificado' => 'boolean',
    ];

    /** El numero cifrado no sale nunca en una respuesta serializada. */
    protected $hidden = [
        'numero_cifrado',
    ];
}

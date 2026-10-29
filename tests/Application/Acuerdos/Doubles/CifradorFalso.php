<?php

declare(strict_types=1);

namespace Tests\Application\Acuerdos\Doubles;

use Src\Acuerdos\Application\Port\CifradorDeDatos;

/**
 * Doble del puerto `CifradorDeDatos`: reversible y reconocible, para que las
 * pruebas puedan afirmar que lo guardado no es el texto claro.
 *
 * No es criptografia: en produccion el adaptador usa `Crypt` de Laravel.
 */
final class CifradorFalso implements CifradorDeDatos
{
    private const PREFIJO = 'cif:';

    public function cifrar(string $valor): string
    {
        return self::PREFIJO.base64_encode($valor);
    }

    public function descifrar(string $valorCifrado): string
    {
        return base64_decode(substr($valorCifrado, strlen(self::PREFIJO)), true) ?: '';
    }
}

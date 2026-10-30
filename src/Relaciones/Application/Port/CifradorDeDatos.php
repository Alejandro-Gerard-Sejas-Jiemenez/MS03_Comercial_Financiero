<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\Port;

/**
 * Cifrado de datos sensibles visto desde la capa de Aplicacion, para que el
 * caso de uso no importe `Illuminate\Support\Facades\Crypt`.
 *
 * Lo usa el numero de la cuenta se guarda cifrado en
 * `numero_cifrado` y en texto claro solo quedan los `ultimos_digitos`.
 */
interface CifradorDeDatos
{
    public function cifrar(string $valor): string;

    /** @throws \RuntimeException si el valor no se puede descifrar */
    public function descifrar(string $valorCifrado): string;
}

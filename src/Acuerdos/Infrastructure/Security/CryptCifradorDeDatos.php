<?php

declare(strict_types=1);

namespace Src\Acuerdos\Infrastructure\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Src\Acuerdos\Application\Port\CifradorDeDatos;

/**
 * Adaptador del puerto `CifradorDeDatos` con el cifrado de Laravel
 * (`APP_KEY`, AES-256-CBC autenticado).
 *
 * Es el unico lugar del paquete que conoce `Crypt`: el Dominio y los casos de
 * uso solo ven la interfaz.
 */
final class CryptCifradorDeDatos implements CifradorDeDatos
{
    public function cifrar(string $valor): string
    {
        return Crypt::encryptString($valor);
    }

    public function descifrar(string $valorCifrado): string
    {
        try {
            return Crypt::decryptString($valorCifrado);
        } catch (DecryptException $e) {
            // No se propaga el valor cifrado en el mensaje.
            throw new RuntimeException('No se pudo descifrar el dato almacenado', 0, $e);
        }
    }
}

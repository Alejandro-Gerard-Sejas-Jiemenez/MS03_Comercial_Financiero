<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity\MetodoCobro;

use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Edicion de los datos del metodo de cobro y sus validaciones.
 *
 * Parte de `MetodoCobro`: usa sus campos privados.
 */
trait Edicion
{
    /** Solo los valores no nulos se aplican. */
    public function actualizarDatos(?string $titular = null, ?string $banco = null): void
    {
        if ($titular !== null) {
            $this->titular = self::validarTitular($titular);
        }

        if ($banco !== null) {
            $this->banco = self::normalizarBanco($banco);
        }
    }

    /**
     * Cambia la cuenta de destino. Obliga a verificar de nuevo: el
     * Administrador aprobo la cuenta anterior, no esta.
     */
    public function reemplazarNumero(string $numeroCifrado, ?string $ultimosDigitos): void
    {
        $this->numeroCifrado = self::validarNumeroCifrado($numeroCifrado);
        $this->ultimosDigitos = self::validarUltimosDigitos($ultimosDigitos);
        $this->verificado = false;
    }

    private static function validarTitular(string $titular): string
    {
        $limpio = trim($titular);

        if ($limpio === '') {
            throw new ReglaDeNegocioViolada('El metodo de cobro necesita el titular de la cuenta');
        }

        return $limpio;
    }

    private static function validarNumeroCifrado(string $numeroCifrado): string
    {
        if (trim($numeroCifrado) === '') {
            throw new ReglaDeNegocioViolada('El metodo de cobro necesita el numero cifrado');
        }

        return $numeroCifrado;
    }

    private static function validarUltimosDigitos(?string $ultimosDigitos): ?string
    {
        if ($ultimosDigitos === null) {
            return null;
        }

        if (preg_match('/^\d{4}$/', $ultimosDigitos) !== 1) {
            throw new ReglaDeNegocioViolada("Los ultimos digitos deben ser cuatro: {$ultimosDigitos}");
        }

        return $ultimosDigitos;
    }

    private static function normalizarBanco(?string $banco): ?string
    {
        if ($banco === null) {
            return null;
        }

        $limpio = trim($banco);

        return $limpio === '' ? null : $limpio;
    }
}

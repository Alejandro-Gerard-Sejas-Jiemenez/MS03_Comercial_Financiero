<?php

declare(strict_types=1);

namespace Src\Acuerdos\Domain;

use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Metodo de cobro del anfitrion (F17): donde se le paga su liquidacion.
 *
 * El dominio nunca ve el numero en claro. Recibe el valor ya cifrado y los
 * cuatro ultimos digitos, que son lo unico legible que se guarda. Cifrar es un
 * detalle de infraestructura detras del puerto `CifradorDeDatos`.
 *
 * "Un solo metodo principal por anfitrion" no se decide aqui, porque mira los
 * otros metodos del anfitrion: lo coordina el caso de uso y lo garantiza el
 * indice unico parcial `uq_metodo_principal`.
 */
final class MetodoCobro
{
    private function __construct(
        private readonly string $metodoId,
        private readonly string $anfitrionId,
        private readonly TipoMetodoCobro $tipo,
        private ?string $banco,
        private string $titular,
        private string $numeroCifrado,
        private ?string $ultimosDigitos,
        private bool $esPrincipal,
        private bool $verificado,
    ) {}

    public static function registrar(
        string $metodoId,
        string $anfitrionId,
        TipoMetodoCobro $tipo,
        string $titular,
        string $numeroCifrado,
        ?string $ultimosDigitos = null,
        ?string $banco = null,
        bool $esPrincipal = false,
    ): self {
        return new self(
            $metodoId,
            $anfitrionId,
            $tipo,
            self::normalizarBanco($banco),
            self::validarTitular($titular),
            self::validarNumeroCifrado($numeroCifrado),
            self::validarUltimosDigitos($ultimosDigitos),
            $esPrincipal,
            // Un metodo nuevo siempre nace sin verificar: lo verifica el
            // Administrador en CU-ACU-04.
            false,
        );
    }

    /** Reconstruye la entidad desde la base de datos, sin revalidar. */
    public static function reconstituir(
        string $metodoId,
        string $anfitrionId,
        TipoMetodoCobro $tipo,
        ?string $banco,
        string $titular,
        string $numeroCifrado,
        ?string $ultimosDigitos,
        bool $esPrincipal,
        bool $verificado,
    ): self {
        return new self(
            $metodoId,
            $anfitrionId,
            $tipo,
            $banco,
            $titular,
            $numeroCifrado,
            $ultimosDigitos,
            $esPrincipal,
            $verificado,
        );
    }

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

    public function marcarPrincipal(): void
    {
        $this->esPrincipal = true;
    }

    public function dejarDeSerPrincipal(): void
    {
        $this->esPrincipal = false;
    }

    /** CU-ACU-04: idempotente, verificar dos veces no cambia nada. */
    public function verificar(): void
    {
        $this->verificado = true;
    }

    /**
     * Solo el metodo principal y verificado sirve para pagarle al anfitrion.
     * Es la condicion que consulta Liquidaciones por `MetodoCobroQuery`.
     */
    public function sirveParaLiquidar(): bool
    {
        return $this->esPrincipal && $this->verificado;
    }

    public function metodoId(): string
    {
        return $this->metodoId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function tipo(): TipoMetodoCobro
    {
        return $this->tipo;
    }

    public function banco(): ?string
    {
        return $this->banco;
    }

    public function titular(): string
    {
        return $this->titular;
    }

    public function numeroCifrado(): string
    {
        return $this->numeroCifrado;
    }

    public function ultimosDigitos(): ?string
    {
        return $this->ultimosDigitos;
    }

    public function esPrincipal(): bool
    {
        return $this->esPrincipal;
    }

    public function estaVerificado(): bool
    {
        return $this->verificado;
    }

    public function esDe(string $anfitrionId): bool
    {
        return $this->anfitrionId === $anfitrionId;
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

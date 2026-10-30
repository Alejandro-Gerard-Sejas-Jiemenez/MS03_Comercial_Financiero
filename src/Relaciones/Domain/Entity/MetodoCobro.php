<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity;

use Src\Relaciones\Domain\Entity\MetodoCobro\CicloDeVida;
use Src\Relaciones\Domain\Entity\MetodoCobro\Edicion;
use Src\Relaciones\Domain\Entity\MetodoCobro\Lectura;
use Src\Relaciones\Domain\Enum\TipoMetodoCobro;

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
    use CicloDeVida;
    use Edicion;
    use Lectura;

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
        private bool $activo = true,
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
            // Administrador en .
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
        bool $activo = true,
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
            $activo,
        );
    }
}

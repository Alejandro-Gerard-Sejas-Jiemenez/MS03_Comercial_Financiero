<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Entity;

use DateTimeImmutable;
use Src\Comercial\Domain\Entity\Promocion\Aplicabilidad;
use Src\Comercial\Domain\Entity\Promocion\CicloDeVida;
use Src\Comercial\Domain\Entity\Promocion\Edicion;
use Src\Comercial\Domain\Entity\Promocion\Lectura;
use Src\Comercial\Domain\Entity\Promocion\Uso;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Enum\RolCreador;
use Src\Comercial\Domain\Enum\TipoDescuento;
use Src\Comercial\Domain\Enum\TipoPromocion;
use Src\Comercial\Domain\ValueObject\AlcanceDePromocion;
use Src\Comercial\Domain\ValueObject\CodigoCupon;
use Src\Comercial\Domain\ValueObject\TopeDeUsos;
use Src\Comercial\Domain\ValueObject\ValorDeDescuento;
use Src\Comercial\Domain\ValueObject\Vigencia;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Shared\Domain\Money;
use Src\Shared\Domain\RegistraEventos;

/**
 * Promocion de F09: descuento con vigencia, noches minimas y tope de usos.
 *
 * Entidad pura: no conoce Eloquent ni Laravel. La fila de la base de datos se
 * traduce con `reconstituir()`. Acumula sus eventos de dominio.
 *
 * Las reglas de cada dato viven en sus objetos de valor (`CodigoCupon`,
 * `Vigencia`, `TopeDeUsos`, `AlcanceDePromocion`, `ValorDeDescuento`) y la
 * pregunta "¿le sirve a esta reserva?" se contesta aqui mismo. Aqui quedan
 * el ciclo de vida (estados) y la coordinacion.
 *
 * `financiada_por` se modela con `FinanciamientoDescuento` de Monetizacion,
 * porque es ese modulo el que decide la base comisionable con ese dato.
 */
final class Promocion
{
    use Aplicabilidad;
    use CicloDeVida;
    use Edicion;
    use Lectura;
    use RegistraEventos;
    use Uso;

    private function __construct(
        private readonly string $promocionId,
        private ?CodigoCupon $codigo,
        private string $nombre,
        private readonly TipoPromocion $tipo,
        private ValorDeDescuento $valor,
        private readonly string $moneda,
        private readonly RolCreador $creadaPorRol,
        private readonly string $creadaPor,
        private FinanciamientoDescuento $financiadaPor,
        private int $minNoches,
        private Vigencia $vigencia,
        private TopeDeUsos $tope,
        private EstadoPromocion $estado,
        private AlcanceDePromocion $alcance,
    ) {}

    /**
     * Nace en BORRADOR: se publica con `activar()`, para que una promocion a
     * medio configurar no descuente nada.
     *
     * @param  list<string>  $alojamientos  alojamientos alcanzados; vacio = todos
     *                                      los del anfitrion (o de la plataforma)
     */
    public static function crear(
        string $promocionId,
        string $nombre,
        TipoPromocion $tipo,
        TipoDescuento $tipoDescuento,
        string $valor,
        DateTimeImmutable $vigenteDesde,
        DateTimeImmutable $vigenteHasta,
        RolCreador $creadaPorRol,
        string $creadaPor,
        ?string $codigo = null,
        ?string $anfitrionId = null,
        FinanciamientoDescuento $financiadaPor = FinanciamientoDescuento::ANFITRION,
        int $minNoches = 1,
        ?int $maxUsos = null,
        string $moneda = 'BOB',
        array $alojamientos = [],
    ): self {
        $vigencia = Vigencia::crear($vigenteDesde, $vigenteHasta);

        return new self(
            $promocionId,
            $codigo === null ? null : CodigoCupon::desde($codigo),
            self::validarNombre($nombre),
            $tipo,
            ValorDeDescuento::crear($valor, $tipoDescuento),
            Money::cero($moneda)->moneda(),
            $creadaPorRol,
            $creadaPor,
            $financiadaPor,
            self::validarMinNoches($minNoches),
            $vigencia,
            TopeDeUsos::crear($maxUsos),
            EstadoPromocion::BORRADOR,
            AlcanceDePromocion::crear($anfitrionId, $alojamientos),
        );
    }

    /**
     * Reconstruye la entidad desde la base de datos, sin revalidar reglas de
     * creacion.
     *
     * @param  list<string>  $alojamientos
     */
    public static function reconstituir(
        string $promocionId,
        ?string $codigo,
        string $nombre,
        TipoPromocion $tipo,
        TipoDescuento $tipoDescuento,
        string $valor,
        string $moneda,
        RolCreador $creadaPorRol,
        string $creadaPor,
        ?string $anfitrionId,
        FinanciamientoDescuento $financiadaPor,
        int $minNoches,
        DateTimeImmutable $vigenteDesde,
        DateTimeImmutable $vigenteHasta,
        ?int $maxUsos,
        int $usosActuales,
        EstadoPromocion $estado,
        array $alojamientos = [],
    ): self {
        return new self(
            $promocionId,
            $codigo === null ? null : CodigoCupon::reconstituir($codigo),
            $nombre,
            $tipo,
            ValorDeDescuento::reconstituir($valor, $tipoDescuento),
            $moneda,
            $creadaPorRol,
            $creadaPor,
            $financiadaPor,
            $minNoches,
            Vigencia::reconstituir($vigenteDesde, $vigenteHasta),
            TopeDeUsos::reconstituir($maxUsos, $usosActuales),
            $estado,
            AlcanceDePromocion::reconstituir($anfitrionId, $alojamientos),
        );
    }
}

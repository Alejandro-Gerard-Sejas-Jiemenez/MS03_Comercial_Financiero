<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion;

use DateTimeImmutable;
use Src\Monetizacion\Application\Port\ReglaMonetizacionRepository;
use Src\Monetizacion\Application\Response\ReglaMonetizacionResponse;
use Src\Monetizacion\Domain\Entity\ReglaMonetizacion;
use Src\Monetizacion\Domain\Enum\Concepto;
use Src\Monetizacion\Domain\Enum\EstadoRegla;
use Src\Monetizacion\Domain\Enum\TipoCalculo;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/** Caso de uso GestionarReglasMonetizacion: implementa la interfaz de entrada GestionarReglasMonetizacionUseCase. */
final class GestionarReglasMonetizacionService implements GestionarReglasMonetizacionUseCase
{
    public function __construct(
        private readonly ReglaMonetizacionRepository $reglas,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly AuditLogger $auditoria,
    ) {}

    public function crear(CrearReglaRequest $comando): ReglaMonetizacionResponse
    {
        $regla = $comando->tipoCalculo === TipoCalculo::PORCENTAJE
            ? ReglaMonetizacion::porPorcentaje(
                reglaId: $this->uuid->generar(),
                nombre: $comando->nombre,
                concepto: $comando->concepto,
                porcentaje: $comando->porcentaje ?? throw new ReglaDeNegocioViolada(
                    'Una regla PORCENTAJE necesita porcentaje',
                ),
                vigenteDesde: $comando->vigenteDesde,
                creadoPor: $comando->creadoPor,
                vigenteHasta: $comando->vigenteHasta,
                categoriaAlojamiento: $comando->categoriaAlojamiento,
                nivelAnfitrion: $comando->nivelAnfitrion,
                moneda: $comando->moneda,
            )
            : ReglaMonetizacion::porMontoFijo(
                reglaId: $this->uuid->generar(),
                nombre: $comando->nombre,
                concepto: $comando->concepto,
                montoFijo: Money::deCentavos(
                    $comando->montoFijoCentavos ?? throw new ReglaDeNegocioViolada(
                        'Una regla MONTO_FIJO necesita monto',
                    ),
                    $comando->moneda,
                ),
                vigenteDesde: $comando->vigenteDesde,
                creadoPor: $comando->creadoPor,
                vigenteHasta: $comando->vigenteHasta,
                categoriaAlojamiento: $comando->categoriaAlojamiento,
                nivelAnfitrion: $comando->nivelAnfitrion,
            );

        $this->reglas->guardar($regla);
        $this->auditoria->registrar(
            'CREAR_REGLA_MONETIZACION',
            'regla_monetizacion',
            $regla->reglaId(),
            null,
            ['nombre' => $regla->nombre(), 'concepto' => $regla->concepto()->value],
            $comando->creadoPor,
        );

        return ReglaMonetizacionResponse::desde($regla);
    }

    public function actualizar(ActualizarReglaRequest $comando): ReglaMonetizacionResponse
    {
        $regla = $this->obligatoria($comando->reglaId);
        $antes = $this->retrato($regla);

        if ($comando->nombre !== null) {
            $regla->renombrar($comando->nombre);
        }

        if ($comando->porcentaje !== null) {
            $regla->cambiarPorcentaje($comando->porcentaje);
        }

        if ($comando->montoFijoCentavos !== null) {
            $regla->cambiarMontoFijo(Money::deCentavos($comando->montoFijoCentavos, $regla->moneda()));
        }

        if ($comando->vigenteHasta !== null) {
            $regla->cerrarVigencia($comando->vigenteHasta);
        }

        $this->reglas->guardar($regla);
        $this->auditoria->registrar(
            'ACTUALIZAR_REGLA_MONETIZACION',
            'regla_monetizacion',
            $regla->reglaId(),
            $antes,
            $this->retrato($regla),
            $comando->actualizadoPor,
        );

        return ReglaMonetizacionResponse::desde($regla);
    }

    /**
     * Las reglas no se borran: se desactivan. El historico de comisiones ya
     * calculadas guarda su propia tarifa, asi que nada cambia hacia atras.
     */
    public function desactivar(string $reglaId, string $desactivadoPor): ReglaMonetizacionResponse
    {
        $regla = $this->obligatoria($reglaId);
        $antes = $this->retrato($regla);

        $regla->desactivar();

        $this->reglas->guardar($regla);
        $this->auditoria->registrar(
            'DESACTIVAR_REGLA_MONETIZACION',
            'regla_monetizacion',
            $reglaId,
            $antes,
            $this->retrato($regla),
            $desactivadoPor,
        );

        return ReglaMonetizacionResponse::desde($regla);
    }

    /** @return list<ReglaMonetizacion> */
    public function listar(?Concepto $concepto = null, ?EstadoRegla $estado = null): array
    {
        return array_map(ReglaMonetizacionResponse::desde(...), $this->reglas->listar($concepto, $estado));
    }

    /**
     * Reglas que rigen hoy (o en la fecha dada). Es la consulta que usa MS-04
     * para la tarifa sugerida de F11.
     *
     * @return list<ReglaMonetizacionResponse>
     */
    public function vigentes(
        Concepto $concepto,
        ?DateTimeImmutable $fecha = null,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
    ): array {
        return array_map(ReglaMonetizacionResponse::desde(...), $this->reglas->vigentes(
            $concepto,
            $fecha ?? $this->clock->hoy(),
            $categoriaAlojamiento,
            $nivelAnfitrion,
        ));
    }

    public function consultar(string $reglaId): ReglaMonetizacionResponse
    {
        return ReglaMonetizacionResponse::desde($this->obligatoria($reglaId));
    }

    private function obligatoria(string $reglaId): ReglaMonetizacion
    {
        return $this->reglas->buscar($reglaId)
            ?? throw new RecursoNoEncontrado("La regla {$reglaId} no existe");
    }

    /** @return array<string, string|null> */
    private function retrato(ReglaMonetizacion $regla): array
    {
        return [
            'nombre' => $regla->nombre(),
            'porcentaje' => $regla->porcentaje(),
            'monto_fijo' => $regla->montoFijo()?->aDecimal(),
            'vigente_hasta' => $regla->vigenteHasta()?->format('Y-m-d'),
            'estado' => $regla->estado()->value,
        ];
    }
}

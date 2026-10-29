<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Concepto;
use Src\Monetizacion\Domain\EstadoRegla;
use Src\Monetizacion\Domain\ReglaMonetizacion;
use Src\Monetizacion\Domain\ReglaMonetizacionRepository;
use Src\Monetizacion\Domain\TipoCalculo;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * CU-MON-01 GestionarReglasMonetizacion (CRUD, disparador REST).
 *
 * El CRUD puro va junto en un servicio `Gestionar...`, como decide el documento
 * de arquitectura: abrir un Command + Handler por cada verbo solo agregaria
 * clases sin agregar reglas. Las reglas de la entidad siguen en el Dominio.
 *
 * Autorizacion: solo el Administrador escribe; MS-04 consulta `vigentes()`.
 * Esa comprobacion la hara el middleware de la capa de Presentacion.
 */
final class GestionarReglasMonetizacionService
{
    public function __construct(
        private readonly ReglaMonetizacionRepository $reglas,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly AuditLogger $auditoria,
    ) {}

    public function crear(CrearReglaCommand $comando): ReglaMonetizacion
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

        return $regla;
    }

    public function actualizar(ActualizarReglaCommand $comando): ReglaMonetizacion
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

        return $regla;
    }

    /**
     * Las reglas no se borran: se desactivan. El historico de comisiones ya
     * calculadas guarda su propia tarifa, asi que nada cambia hacia atras.
     */
    public function desactivar(string $reglaId, string $desactivadoPor): ReglaMonetizacion
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

        return $regla;
    }

    /** @return list<ReglaMonetizacion> */
    public function listar(?Concepto $concepto = null, ?EstadoRegla $estado = null): array
    {
        return $this->reglas->listar($concepto, $estado);
    }

    /**
     * Reglas que rigen hoy (o en la fecha dada). Es la consulta que usa MS-04
     * para la tarifa sugerida de F11.
     *
     * @return list<ReglaMonetizacion>
     */
    public function vigentes(
        Concepto $concepto,
        ?DateTimeImmutable $fecha = null,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
    ): array {
        return $this->reglas->vigentes(
            $concepto,
            $fecha ?? $this->clock->hoy(),
            $categoriaAlojamiento,
            $nivelAnfitrion,
        );
    }

    public function consultar(string $reglaId): ReglaMonetizacion
    {
        return $this->obligatoria($reglaId);
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

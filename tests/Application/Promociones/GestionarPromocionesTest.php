<?php

declare(strict_types=1);

namespace Tests\Application\Promociones;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Application\UseCase\GestionarPromociones\ActualizarPromocionCommand;
use Src\Promociones\Application\UseCase\GestionarPromociones\AsignarAlojamientosCommand;
use Src\Promociones\Application\UseCase\GestionarPromociones\CrearPromocionCommand;
use Src\Promociones\Application\UseCase\GestionarPromociones\GestionarPromocionesService;
use Src\Promociones\Domain\EstadoPromocion;
use Src\Promociones\Domain\TipoDescuento;
use Src\Promociones\Domain\TipoPromocion;
use Src\Promociones\Domain\RolCreador;
use Tests\Doubles\InMemoryPromocionRepository;
use Tests\Doubles\SequentialUuidGenerator;
use Tests\Doubles\SpyAuditLogger;

/**
 * CU-PRO-01 GestionarPromociones (CRUD, disparador REST).
 */
final class GestionarPromocionesTest extends TestCase
{
    private InMemoryPromocionRepository $promociones;

    private SpyAuditLogger $auditoria;

    private GestionarPromocionesService $servicio;

    protected function setUp(): void
    {
        $this->promociones = new InMemoryPromocionRepository();
        $this->auditoria = new SpyAuditLogger();
        $this->servicio = new GestionarPromocionesService(
            $this->promociones,
            new SequentialUuidGenerator(),
            $this->auditoria,
        );
    }

    public function test_crear_deja_la_promocion_en_borrador_y_la_audita(): void
    {
        $promocion = $this->servicio->crear($this->comandoDeCreacion());

        self::assertSame('00000000-0000-4000-8000-000000000001', $promocion->promocionId());
        self::assertSame(EstadoPromocion::BORRADOR, $promocion->estado());
        self::assertSame('VERANO10', $promocion->codigo());
        self::assertSame(0, $promocion->usosActuales());
        self::assertSame(1, $this->promociones->cantidad());
        self::assertSame('CREAR_PROMOCION', $this->auditoria->registros()[0]['accion']);
        self::assertSame('promocion', $this->auditoria->registros()[0]['entidad']);
    }

    public function test_no_se_puede_crear_dos_promociones_con_el_mismo_cupon(): void
    {
        $this->servicio->crear($this->comandoDeCreacion());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VERANO10');

        $this->servicio->crear($this->comandoDeCreacion());
    }

    public function test_actualizar_cambia_solo_los_campos_enviados(): void
    {
        $creada = $this->servicio->crear($this->comandoDeCreacion());

        $promocion = $this->servicio->actualizar(new ActualizarPromocionCommand(
            promocionId: $creada->promocionId(),
            actualizadoPor: 'usr-9',
            nombre: 'Verano recargado',
            valor: '20.00',
            minNoches: 3,
        ));

        self::assertSame('Verano recargado', $promocion->nombre());
        self::assertSame('20.00', $promocion->valor());
        self::assertSame(3, $promocion->minNoches());
        // Lo que no se envia no se toca.
        self::assertSame('VERANO10', $promocion->codigo());
        self::assertSame(
            '2026-12-31',
            $promocion->vigenteHasta()->format('Y-m-d'),
        );
        self::assertSame('ACTUALIZAR_PROMOCION', $this->auditoria->registros()[1]['accion']);
    }

    public function test_asignar_alojamientos_reemplaza_la_lista(): void
    {
        $creada = $this->servicio->crear($this->comandoDeCreacion());

        $promocion = $this->servicio->asignarAlojamientos(new AsignarAlojamientosCommand(
            promocionId: $creada->promocionId(),
            alojamientos: ['aloj-1', 'aloj-2', 'aloj-1'],
            asignadoPor: 'usr-9',
        ));

        self::assertSame(['aloj-1', 'aloj-2'], $promocion->alojamientos());

        $promocion = $this->servicio->asignarAlojamientos(new AsignarAlojamientosCommand(
            promocionId: $creada->promocionId(),
            alojamientos: ['aloj-3'],
            asignadoPor: 'usr-9',
        ));

        self::assertSame(['aloj-3'], $promocion->alojamientos());
    }

    public function test_activar_pausar_y_cancelar_cambian_el_estado(): void
    {
        $id = $this->servicio->crear($this->comandoDeCreacion())->promocionId();

        self::assertSame(EstadoPromocion::ACTIVA, $this->servicio->activar($id, 'usr-9')->estado());
        self::assertSame(EstadoPromocion::PAUSADA, $this->servicio->pausar($id, 'usr-9')->estado());
        self::assertSame(EstadoPromocion::CANCELADA, $this->servicio->cancelar($id, 'usr-9')->estado());

        $acciones = array_column($this->auditoria->registros(), 'accion');
        self::assertSame(
            ['CREAR_PROMOCION', 'ACTIVAR_PROMOCION', 'PAUSAR_PROMOCION', 'CANCELAR_PROMOCION'],
            $acciones,
        );
    }

    public function test_listar_filtra_por_anfitrion_y_estado(): void
    {
        $delAnfitrion = $this->servicio->crear($this->comandoDeCreacion(codigo: 'A1'))->promocionId();
        $this->servicio->crear($this->comandoDeCreacion(codigo: 'B1', anfitrionId: 'anf-2'));
        $this->servicio->activar($delAnfitrion, 'usr-9');

        self::assertCount(2, $this->servicio->listar());
        self::assertCount(1, $this->servicio->listar(anfitrionId: 'anf-1'));
        self::assertCount(1, $this->servicio->listar(estado: EstadoPromocion::ACTIVA));
        self::assertCount(2, $this->servicio->listar(tipo: TipoPromocion::CUPON));
    }

    public function test_consultar_una_promocion_inexistente_falla(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        $this->servicio->consultar('promo-9');
    }

    private function comandoDeCreacion(
        string $codigo = 'VERANO10',
        ?string $anfitrionId = 'anf-1',
    ): CrearPromocionCommand {
        return new CrearPromocionCommand(
            nombre: 'Verano 10 %',
            tipo: TipoPromocion::CUPON,
            tipoDescuento: TipoDescuento::PORCENTAJE,
            valor: '10.00',
            vigenteDesde: new DateTimeImmutable('2026-10-01 00:00:00'),
            vigenteHasta: new DateTimeImmutable('2026-12-31 23:59:59'),
            creadaPorRol: RolCreador::ANFITRION,
            creadaPor: 'usr-1',
            codigo: $codigo,
            anfitrionId: $anfitrionId,
            financiadaPor: FinanciamientoDescuento::ANFITRION,
            minNoches: 2,
            maxUsos: 100,
        );
    }
}

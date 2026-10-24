<?php

declare(strict_types=1);

namespace Tests\Application\Liquidaciones;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Liquidaciones\Application\UseCase\GenerarLiquidaciones\GenerarLiquidacionesCommand;
use Src\Liquidaciones\Application\UseCase\GenerarLiquidaciones\GenerarLiquidacionesHandler;
use Src\Liquidaciones\Application\UseCase\MarcarComisionesLiquidables\MarcarComisionesLiquidablesCommand;
use Src\Liquidaciones\Application\UseCase\MarcarComisionesLiquidables\MarcarComisionesLiquidablesHandler;
use Src\Liquidaciones\Application\UseCase\PagarLiquidacion\PagarLiquidacionCommand;
use Src\Liquidaciones\Application\UseCase\PagarLiquidacion\PagarLiquidacionHandler;
use Src\Liquidaciones\Application\UseCase\RetenerLiquidacionesDeAnfitrion\RetenerLiquidacionesDeAnfitrionCommand;
use Src\Liquidaciones\Application\UseCase\RetenerLiquidacionesDeAnfitrion\RetenerLiquidacionesDeAnfitrionHandler;
use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\EstadoComision;
use Src\Liquidaciones\Domain\EstadoLiquidacion;
use Src\Referencias\Domain\AnfitrionReference;
use Src\Referencias\Domain\EstadoReserva;
use Src\Referencias\Domain\EstadoVerificacionAnfitrion;
use Src\Referencias\Domain\ReservaReference;
use Tests\Doubles\FakeAcuerdoComercialQuery;
use Tests\Doubles\FakeMetodoCobroQuery;
use Tests\Doubles\FrozenClock;
use Tests\Doubles\InMemoryAnfitrionReferenceRepository;
use Tests\Doubles\InMemoryComisionRepository;
use Tests\Doubles\InMemoryEventPublisher;
use Tests\Doubles\InMemoryLibroContableRepository;
use Tests\Doubles\InMemoryLiquidacionRepository;
use Tests\Doubles\InMemoryReservaReferenceRepository;
use Tests\Doubles\SequentialUuidGenerator;
use Tests\Doubles\SpyAuditLogger;
use Tests\Doubles\SyncTransactionManager;
use Src\Shared\Domain\Money;

/**
 * CU-LIQ-03, CU-LIQ-04, CU-LIQ-07 y CU-LIQ-08: de la comision retenida al
 * dinero transferido, con dobles en memoria.
 */
final class CicloDeLiquidacionTest extends TestCase
{
    private InMemoryComisionRepository $comisiones;
    private InMemoryLiquidacionRepository $liquidaciones;
    private InMemoryAnfitrionReferenceRepository $anfitriones;
    private InMemoryLibroContableRepository $libro;
    private InMemoryEventPublisher $eventos;
    private SyncTransactionManager $tx;
    private SequentialUuidGenerator $uuid;
    private FrozenClock $clock;
    private SpyAuditLogger $auditoria;

    protected function setUp(): void
    {
        $this->comisiones = new InMemoryComisionRepository();
        $this->liquidaciones = new InMemoryLiquidacionRepository();
        $this->anfitriones = new InMemoryAnfitrionReferenceRepository(
            $this->anfitrion('anfitrion-1', EstadoVerificacionAnfitrion::VERIFICADO),
        );
        $this->libro = new InMemoryLibroContableRepository();
        $this->eventos = new InMemoryEventPublisher();
        $this->tx = new SyncTransactionManager();
        $this->uuid = new SequentialUuidGenerator();
        $this->clock = new FrozenClock('2026-11-20 10:00:00');
        $this->auditoria = new SpyAuditLogger();
    }

    public function test_marca_la_comision_liquidable_desde_checkout_mas_los_dias_del_acuerdo(): void
    {
        $this->comisiones->guardar($this->comision('comision-1'));
        $reservas = new InMemoryReservaReferenceRepository($this->reserva());
        $acuerdos = (new FakeAcuerdoComercialQuery())->con('anfitrion-1', diasLiquidacion: 10);

        $marcadas = (new MarcarComisionesLiquidablesHandler(
            $this->comisiones,
            $reservas,
            $acuerdos,
            $this->tx,
        ))(new MarcarComisionesLiquidablesCommand('reserva-1'));

        self::assertSame(['comision-1'], $marcadas);

        $comision = $this->comisiones->buscar('comision-1');
        self::assertSame(EstadoComision::LIQUIDABLE, $comision->estado());
        // check-out 2026-11-05 + 10 dias = 2026-11-15
        self::assertSame('2026-11-15', $comision->liquidableDesde()->format('Y-m-d'));
    }

    public function test_sin_acuerdo_activo_usa_el_plazo_por_omision_de_siete_dias(): void
    {
        $this->comisiones->guardar($this->comision('comision-1'));

        (new MarcarComisionesLiquidablesHandler(
            $this->comisiones,
            new InMemoryReservaReferenceRepository($this->reserva()),
            new FakeAcuerdoComercialQuery(),
            $this->tx,
        ))(new MarcarComisionesLiquidablesCommand('reserva-1'));

        self::assertSame(
            '2026-11-12',
            $this->comisiones->buscar('comision-1')->liquidableDesde()->format('Y-m-d'),
        );
    }

    public function test_genera_una_liquidacion_por_anfitrion_con_sus_comisiones_liquidables(): void
    {
        $this->comisionLiquidable('comision-1', base: 90000, monto: 13500);
        $this->comisionLiquidable('comision-2', base: 60000, monto: 9000);

        $generadas = ($this->generarLiquidaciones())(new GenerarLiquidacionesCommand());

        self::assertCount(1, $generadas);
        $liquidacion = $generadas[0];
        // bruto = 900 + 600 = 1500; comisiones = 135 + 90 = 225; neto = 1275
        self::assertSame('1500.00', $liquidacion->totalBruto()->aDecimal());
        self::assertSame('225.00', $liquidacion->totalComisiones()->aDecimal());
        self::assertSame('1275.00', $liquidacion->totalNeto()->aDecimal());
        self::assertSame('metodo-1', $liquidacion->metodoId());
        self::assertSame(EstadoLiquidacion::GENERADA, $liquidacion->estado());
    }

    public function test_las_comisiones_liquidadas_quedan_ligadas_a_su_liquidacion(): void
    {
        $this->comisionLiquidable('comision-1');

        $liquidacion = ($this->generarLiquidaciones())(new GenerarLiquidacionesCommand())[0];

        $comision = $this->comisiones->buscar('comision-1');
        self::assertSame(EstadoComision::LIQUIDADA, $comision->estado());
        self::assertSame($liquidacion->liquidacionId(), $comision->liquidacionId());
    }

    public function test_no_genera_liquidacion_de_un_anfitrion_sin_comisiones_liquidables(): void
    {
        // La comision existe pero sigue RETENIDA: la estadia no termino.
        $this->comisiones->guardar($this->comision('comision-1'));

        self::assertSame([], ($this->generarLiquidaciones())(new GenerarLiquidacionesCommand()));
        self::assertSame(0, $this->liquidaciones->cantidad());
    }

    public function test_no_genera_liquidacion_de_un_anfitrion_suspendido(): void
    {
        $this->comisionLiquidable('comision-1');
        // MS-01 lo suspendio y el evento ya actualizo la copia local.
        $this->anfitriones->guardar(
            $this->anfitrion('anfitrion-1', EstadoVerificacionAnfitrion::SUSPENDIDO),
        );

        self::assertSame([], ($this->generarLiquidaciones())(new GenerarLiquidacionesCommand()));
    }

    public function test_no_incluye_comisiones_cuyo_plazo_todavia_no_vencio(): void
    {
        $this->comisionLiquidable('comision-1', liquidableDesde: '2026-12-01');

        // El reloj esta en 2026-11-20: el plazo no vencio.
        self::assertSame([], ($this->generarLiquidaciones())(new GenerarLiquidacionesCommand()));
    }

    public function test_pagar_la_liquidacion_emite_el_evento_y_cancela_la_deuda_en_el_libro(): void
    {
        $this->comisionLiquidable('comision-1', base: 90000, monto: 13500);
        $liquidacion = ($this->generarLiquidaciones())(new GenerarLiquidacionesCommand())[0];
        $liquidacion->aprobar('admin-1');
        $this->liquidaciones->guardar($liquidacion);

        $pagada = (new PagarLiquidacionHandler(
            $this->liquidaciones,
            $this->libro,
            $this->eventos,
            $this->auditoria,
            $this->uuid,
            $this->tx,
        ))(new PagarLiquidacionCommand($liquidacion->liquidacionId(), 'TRANSF-99', 'admin-1'));

        self::assertSame(EstadoLiquidacion::PAGADA, $pagada->estado());
        self::assertTrue($this->eventos->contiene('liquidacion.pagada'));
        self::assertSame(1, $this->libro->cantidadDeAsientos());
        // El asiento deja el saldo por pagar en cero para ese anfitrion.
        self::assertSame(
            '-765.00',
            $this->libro->saldoPorPagarDe('anfitrion-1')->aDecimal(),
            'solo hay el asiento del pago de la liquidacion, sin el del cobro',
        );
    }

    public function test_retener_anula_las_liquidaciones_pendientes_y_libera_sus_comisiones(): void
    {
        $this->comisionLiquidable('comision-1');
        $liquidacion = ($this->generarLiquidaciones())(new GenerarLiquidacionesCommand())[0];

        $retenidas = (new RetenerLiquidacionesDeAnfitrionHandler(
            $this->liquidaciones,
            $this->comisiones,
            $this->auditoria,
            $this->tx,
        ))(new RetenerLiquidacionesDeAnfitrionCommand('anfitrion-1'));

        self::assertSame([$liquidacion->liquidacionId()], $retenidas);
        self::assertSame(
            EstadoLiquidacion::ANULADA,
            $this->liquidaciones->buscar($liquidacion->liquidacionId())->estado(),
        );
        // La comision vuelve a estar disponible: el dinero no queda atrapado.
        self::assertSame(EstadoComision::LIQUIDABLE, $this->comisiones->buscar('comision-1')->estado());
        self::assertNull($this->comisiones->buscar('comision-1')->liquidacionId());
    }

    private function generarLiquidaciones(): GenerarLiquidacionesHandler
    {
        return new GenerarLiquidacionesHandler(
            $this->comisiones,
            $this->liquidaciones,
            $this->anfitriones,
            (new FakeMetodoCobroQuery())->con('anfitrion-1', 'metodo-1'),
            $this->uuid,
            $this->clock,
            $this->tx,
        );
    }

    private function comision(string $id, int $base = 90000, int $monto = 13500): Comision
    {
        return Comision::registrar(
            comisionId: $id,
            pagoId: 'pago-' . $id,
            reservaId: 'reserva-1',
            anfitrionId: 'anfitrion-1',
            baseComisionable: Money::deCentavos($base),
            montoComision: Money::deCentavos($monto),
            porcentajeAplicado: '15',
        );
    }

    private function comisionLiquidable(
        string $id,
        int $base = 90000,
        int $monto = 13500,
        string $liquidableDesde = '2026-11-15',
    ): void {
        $comision = $this->comision($id, $base, $monto);
        $comision->marcarLiquidable(new DateTimeImmutable($liquidableDesde));
        $this->comisiones->guardar($comision);
    }

    private function anfitrion(string $id, EstadoVerificacionAnfitrion $estado): AnfitrionReference
    {
        return new AnfitrionReference($id, 'Anfitrion de prueba', $estado);
    }

    private function reserva(): ReservaReference
    {
        return new ReservaReference(
            reservaId: 'reserva-1',
            clienteId: 'cliente-1',
            anfitrionId: 'anfitrion-1',
            alojamientoId: 'alojamiento-1',
            fechaCheckin: new DateTimeImmutable('2026-11-01'),
            fechaCheckout: new DateTimeImmutable('2026-11-05'),
            estado: EstadoReserva::COMPLETADA,
            categoriaAlojamiento: 'DEPARTAMENTO',
        );
    }
}

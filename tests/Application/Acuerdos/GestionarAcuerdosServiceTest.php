<?php

declare(strict_types=1);

namespace Tests\Application\Acuerdos;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Acuerdos\Application\UseCase\GestionarAcuerdos\ActualizarCondicionesAcuerdoCommand;
use Src\Acuerdos\Application\UseCase\GestionarAcuerdos\CrearAcuerdoBorradorCommand;
use Src\Acuerdos\Application\UseCase\GestionarAcuerdos\GestionarAcuerdosService;
use Src\Acuerdos\Domain\EstadoAcuerdo;
use Tests\Doubles\InMemoryAcuerdoComercialRepository;
use Tests\Doubles\SequentialUuidGenerator;
use Tests\Doubles\SpyAuditLogger;

/**
 * CU-ACU-01 `GestionarAcuerdos`: el CRUD del acuerdo comercial.
 */
final class GestionarAcuerdosServiceTest extends TestCase
{
    private InMemoryAcuerdoComercialRepository $acuerdos;

    private SpyAuditLogger $auditoria;

    private GestionarAcuerdosService $servicio;

    protected function setUp(): void
    {
        $this->acuerdos = new InMemoryAcuerdoComercialRepository();
        $this->auditoria = new SpyAuditLogger();

        $this->servicio = new GestionarAcuerdosService(
            $this->acuerdos,
            new SequentialUuidGenerator(),
            $this->auditoria,
        );
    }

    public function test_crear_un_borrador_lo_guarda_con_un_id_nuevo(): void
    {
        $acuerdo = $this->servicio->crear(new CrearAcuerdoBorradorCommand(
            anfitrionId: 'anf-1',
            fechaInicio: new DateTimeImmutable('2026-11-01'),
            creadoPor: 'admin-1',
            diasLiquidacion: 15,
            reglaComisionId: 'regla-1',
            condiciones: ['retencion' => '2.5'],
        ));

        self::assertSame('00000000-0000-4000-8000-000000000001', $acuerdo->acuerdoId());
        self::assertSame(EstadoAcuerdo::BORRADOR, $acuerdo->estado());
        self::assertSame(15, $acuerdo->diasLiquidacion());
        self::assertSame(1, $this->acuerdos->cantidad());
        self::assertSame('CREAR_ACUERDO_COMERCIAL', $this->auditoria->registros()[0]['accion']);
    }

    public function test_actualizar_condiciones_solo_cambia_lo_enviado(): void
    {
        $acuerdo = $this->servicio->crear(new CrearAcuerdoBorradorCommand(
            anfitrionId: 'anf-1',
            fechaInicio: new DateTimeImmutable('2026-11-01'),
            creadoPor: 'admin-1',
            diasLiquidacion: 7,
            reglaComisionId: 'regla-1',
        ));

        $actualizado = $this->servicio->actualizarCondiciones(new ActualizarCondicionesAcuerdoCommand(
            acuerdoId: $acuerdo->acuerdoId(),
            actualizadoPor: 'admin-1',
            diasLiquidacion: 30,
        ));

        self::assertSame(30, $actualizado->diasLiquidacion());
        self::assertSame('regla-1', $actualizado->reglaComisionId(), 'lo no enviado no cambia');
    }

    public function test_listar_filtra_por_estado(): void
    {
        $this->servicio->crear($this->comandoDe('anf-1'));
        $this->servicio->crear($this->comandoDe('anf-2'));

        self::assertCount(2, $this->servicio->listar());
        self::assertCount(2, $this->servicio->listar(EstadoAcuerdo::BORRADOR));
        self::assertCount(0, $this->servicio->listar(EstadoAcuerdo::ACTIVO));
    }

    public function test_el_historial_devuelve_los_acuerdos_de_un_anfitrion_del_mas_nuevo_al_mas_viejo(): void
    {
        $this->servicio->crear(new CrearAcuerdoBorradorCommand(
            anfitrionId: 'anf-1',
            fechaInicio: new DateTimeImmutable('2025-01-01'),
            creadoPor: 'admin-1',
        ));
        $this->servicio->crear(new CrearAcuerdoBorradorCommand(
            anfitrionId: 'anf-1',
            fechaInicio: new DateTimeImmutable('2026-01-01'),
            creadoPor: 'admin-1',
        ));
        $this->servicio->crear($this->comandoDe('anf-2'));

        $historial = $this->servicio->historialDe('anf-1');

        self::assertCount(2, $historial);
        self::assertSame('2026-01-01', $historial[0]->fechaInicio()->format('Y-m-d'));
        self::assertSame('2025-01-01', $historial[1]->fechaInicio()->format('Y-m-d'));
    }

    public function test_consultar_un_acuerdo_que_no_existe_falla(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        $this->servicio->consultar('acu-fantasma');
    }

    private function comandoDe(string $anfitrionId): CrearAcuerdoBorradorCommand
    {
        return new CrearAcuerdoBorradorCommand(
            anfitrionId: $anfitrionId,
            fechaInicio: new DateTimeImmutable('2026-11-01'),
            creadoPor: 'admin-1',
        );
    }
}

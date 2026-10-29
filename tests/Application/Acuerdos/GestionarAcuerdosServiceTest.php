<?php

declare(strict_types=1);

namespace Tests\Application\Acuerdos;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
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
        $this->acuerdos = new InMemoryAcuerdoComercialRepository;
        $this->auditoria = new SpyAuditLogger;

        $this->servicio = new GestionarAcuerdosService(
            $this->acuerdos,
            new SequentialUuidGenerator,
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

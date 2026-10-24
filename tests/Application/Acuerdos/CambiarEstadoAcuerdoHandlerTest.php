<?php

declare(strict_types=1);

namespace Tests\Application\Acuerdos;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Acuerdos\Application\UseCase\CambiarEstadoAcuerdo\CambiarEstadoAcuerdoCommand;
use Src\Acuerdos\Application\UseCase\CambiarEstadoAcuerdo\CambiarEstadoAcuerdoHandler;
use Src\Acuerdos\Domain\AcuerdoComercial;
use Src\Acuerdos\Domain\EstadoAcuerdo;
use Tests\Doubles\FrozenClock;
use Tests\Doubles\InMemoryAcuerdoComercialRepository;
use Tests\Doubles\InMemoryEventPublisher;
use Tests\Doubles\SpyAuditLogger;
use Tests\Doubles\SyncTransactionManager;

/**
 * CU-ACU-02 `CambiarEstadoAcuerdo` con dobles en memoria: sin Laravel ni base
 * de datos. La regla dura es que un anfitrion tenga un solo acuerdo ACTIVO.
 */
final class CambiarEstadoAcuerdoHandlerTest extends TestCase
{
    private InMemoryAcuerdoComercialRepository $acuerdos;

    private InMemoryEventPublisher $eventos;

    private SyncTransactionManager $transacciones;

    private SpyAuditLogger $auditoria;

    private CambiarEstadoAcuerdoHandler $handler;

    protected function setUp(): void
    {
        $this->acuerdos = new InMemoryAcuerdoComercialRepository();
        $this->eventos = new InMemoryEventPublisher();
        $this->transacciones = new SyncTransactionManager();
        $this->auditoria = new SpyAuditLogger();

        $this->handler = new CambiarEstadoAcuerdoHandler(
            $this->acuerdos,
            $this->eventos,
            $this->transacciones,
            new FrozenClock('2026-10-23 12:00:00'),
            $this->auditoria,
        );
    }

    public function test_un_anfitrion_solo_puede_tener_un_acuerdo_activo(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));
        $this->acuerdos->guardar($this->borrador('acu-2', 'anf-1'));

        $this->handler->manejar($this->activarComando('acu-1'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('acu-1');

        $this->handler->manejar($this->activarComando('acu-2'));
    }

    public function test_otro_anfitrion_si_puede_activar_su_propio_acuerdo(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));
        $this->acuerdos->guardar($this->borrador('acu-2', 'anf-2'));

        $this->handler->manejar($this->activarComando('acu-1'));
        $this->handler->manejar($this->activarComando('acu-2'));

        self::assertTrue($this->acuerdos->buscar('acu-1')?->estaActivo());
        self::assertTrue($this->acuerdos->buscar('acu-2')?->estaActivo());
    }

    public function test_reactivar_el_mismo_acuerdo_suspendido_no_choca_consigo_mismo(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));

        $this->handler->manejar($this->activarComando('acu-1'));
        $this->handler->manejar(new CambiarEstadoAcuerdoCommand(
            acuerdoId: 'acu-1',
            estadoDestino: EstadoAcuerdo::SUSPENDIDO,
            solicitadoPor: 'admin-1',
            motivo: 'Revision de documentos',
        ));
        $this->handler->manejar($this->activarComando('acu-1'));

        self::assertTrue($this->acuerdos->buscar('acu-1')?->estaActivo());
        self::assertSame(
            ['acuerdo_comercial.activado', 'acuerdo_comercial.suspendido', 'acuerdo_comercial.activado'],
            $this->eventos->tipos(),
        );
    }

    public function test_cada_transicion_valida_publica_su_evento(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));

        $this->handler->manejar($this->activarComando('acu-1'));
        self::assertSame(['acuerdo_comercial.activado'], $this->eventos->tipos());

        $this->handler->manejar(new CambiarEstadoAcuerdoCommand(
            acuerdoId: 'acu-1',
            estadoDestino: EstadoAcuerdo::FINALIZADO,
            solicitadoPor: 'admin-1',
            motivo: 'Fin de vigencia',
        ));

        self::assertSame(
            ['acuerdo_comercial.activado', 'acuerdo_comercial.finalizado'],
            $this->eventos->tipos(),
        );
        self::assertSame('anf-1', $this->eventos->publicados()[1]->payload()['anfitrion_id']);
    }

    public function test_las_transiciones_invalidas_se_rechazan_y_no_publican_eventos(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));

        try {
            $this->handler->manejar(new CambiarEstadoAcuerdoCommand(
                acuerdoId: 'acu-1',
                estadoDestino: EstadoAcuerdo::SUSPENDIDO,
                solicitadoPor: 'admin-1',
                motivo: 'No corresponde',
            ));
            self::fail('Un BORRADOR no se puede suspender');
        } catch (DomainException) {
            // esperado
        }

        self::assertSame(EstadoAcuerdo::BORRADOR, $this->acuerdos->buscar('acu-1')?->estado());
        self::assertSame(0, $this->eventos->cantidad());
        self::assertSame(1, $this->transacciones->revertidas());
    }

    public function test_guarda_y_publica_dentro_de_una_sola_transaccion(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));

        $this->handler->manejar($this->activarComando('acu-1'));

        self::assertSame(1, $this->transacciones->transacciones());
        self::assertSame(0, $this->transacciones->revertidas());
        self::assertSame(1, $this->eventos->cantidad());
    }

    public function test_el_evento_arrastra_el_saga_id_cuando_lo_dispara_un_evento(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));

        $this->handler->manejar(new CambiarEstadoAcuerdoCommand(
            acuerdoId: 'acu-1',
            estadoDestino: EstadoAcuerdo::ACTIVO,
            solicitadoPor: 'admin-1',
            sagaId: 'saga-77',
        ));

        self::assertSame('saga-77', $this->eventos->primero()?->sagaId());
    }

    public function test_rescindir_publica_finalizado_con_el_estado_real(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));
        $this->handler->manejar($this->activarComando('acu-1'));

        $this->handler->manejar(new CambiarEstadoAcuerdoCommand(
            acuerdoId: 'acu-1',
            estadoDestino: EstadoAcuerdo::RESCINDIDO,
            solicitadoPor: 'admin-1',
            motivo: 'Incumplimiento',
        ));

        $ultimo = $this->eventos->publicados()[1];
        self::assertSame('acuerdo_comercial.finalizado', $ultimo->tipoEvento());
        self::assertSame('RESCINDIDO', $ultimo->payload()['estado_final']);
        self::assertSame(EstadoAcuerdo::RESCINDIDO, $this->acuerdos->buscar('acu-1')?->estado());
    }

    public function test_un_acuerdo_que_no_existe_se_rechaza(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        $this->handler->manejar($this->activarComando('acu-fantasma'));
    }

    public function test_no_se_puede_volver_a_borrador(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));

        $this->expectException(DomainException::class);

        $this->handler->manejar(new CambiarEstadoAcuerdoCommand(
            acuerdoId: 'acu-1',
            estadoDestino: EstadoAcuerdo::BORRADOR,
            solicitadoPor: 'admin-1',
        ));
    }

    public function test_registra_el_cambio_de_estado_en_auditoria(): void
    {
        $this->acuerdos->guardar($this->borrador('acu-1', 'anf-1'));

        $this->handler->manejar($this->activarComando('acu-1'));

        self::assertSame(
            [['accion' => 'CAMBIAR_ESTADO_ACUERDO', 'entidad' => 'acuerdo_comercial', 'entidadId' => 'acu-1']],
            $this->auditoria->registros(),
        );
    }

    private function activarComando(string $acuerdoId): CambiarEstadoAcuerdoCommand
    {
        return new CambiarEstadoAcuerdoCommand(
            acuerdoId: $acuerdoId,
            estadoDestino: EstadoAcuerdo::ACTIVO,
            solicitadoPor: 'admin-1',
        );
    }

    private function borrador(string $acuerdoId, string $anfitrionId): AcuerdoComercial
    {
        return AcuerdoComercial::crearBorrador(
            acuerdoId: $acuerdoId,
            anfitrionId: $anfitrionId,
            fechaInicio: new DateTimeImmutable('2026-11-01'),
        );
    }
}

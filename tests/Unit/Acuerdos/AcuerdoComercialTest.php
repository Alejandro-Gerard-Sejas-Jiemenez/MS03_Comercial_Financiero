<?php

declare(strict_types=1);

namespace Tests\Unit\Acuerdos;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Acuerdos\Domain\AcuerdoComercial;
use Src\Acuerdos\Domain\EstadoAcuerdo;
use Src\Shared\Domain\DomainEvent;

/**
 * CU-ACU-02: las transiciones de estado del acuerdo comercial y los eventos
 * que MS-01 consume, sin Laravel ni base de datos.
 */
final class AcuerdoComercialTest extends TestCase
{
    public function test_un_acuerdo_nace_en_borrador_y_sin_eventos(): void
    {
        $acuerdo = $this->borrador();

        self::assertSame(EstadoAcuerdo::BORRADOR, $acuerdo->estado());
        self::assertFalse($acuerdo->estaActivo());
        self::assertSame([], $acuerdo->sacarEventos());
    }

    public function test_activar_un_borrador_emite_acuerdo_comercial_activado(): void
    {
        $acuerdo = $this->borrador();

        $acuerdo->activar('admin-1', new DateTimeImmutable('2026-10-23 10:00:00'));

        self::assertSame(EstadoAcuerdo::ACTIVO, $acuerdo->estado());
        self::assertTrue($acuerdo->estaActivo());
        self::assertSame('admin-1', $acuerdo->aprobadoPor());

        $evento = $this->unicoEvento($acuerdo);
        self::assertSame('acuerdo_comercial.activado', $evento->tipoEvento());
        self::assertSame('acuerdo_comercial', $evento->agregadoTipo());
        self::assertSame('acu-1', $evento->agregadoId());
        self::assertSame('anf-1', $evento->payload()['anfitrion_id']);
        self::assertSame(7, $evento->payload()['dias_liquidacion']);
    }

    public function test_suspender_un_acuerdo_activo_emite_acuerdo_comercial_suspendido(): void
    {
        $acuerdo = $this->activo();

        $acuerdo->suspender('Documentacion vencida');

        self::assertSame(EstadoAcuerdo::SUSPENDIDO, $acuerdo->estado());
        self::assertSame('Documentacion vencida', $acuerdo->motivoEstado());

        $evento = $this->unicoEvento($acuerdo);
        self::assertSame('acuerdo_comercial.suspendido', $evento->tipoEvento());
        self::assertSame('Documentacion vencida', $evento->payload()['motivo']);
    }

    public function test_finalizar_un_acuerdo_activo_emite_acuerdo_comercial_finalizado(): void
    {
        $acuerdo = $this->activo();

        $acuerdo->finalizar('Fin de la vigencia pactada');

        self::assertSame(EstadoAcuerdo::FINALIZADO, $acuerdo->estado());

        $evento = $this->unicoEvento($acuerdo);
        self::assertSame('acuerdo_comercial.finalizado', $evento->tipoEvento());
        self::assertSame('FINALIZADO', $evento->payload()['estado_final']);
    }

    public function test_rescindir_tambien_emite_finalizado_pero_con_su_estado_final(): void
    {
        $acuerdo = $this->activo();

        $acuerdo->rescindir('Incumplimiento del anfitrion');

        self::assertSame(EstadoAcuerdo::RESCINDIDO, $acuerdo->estado());

        $evento = $this->unicoEvento($acuerdo);
        self::assertSame('acuerdo_comercial.finalizado', $evento->tipoEvento());
        self::assertSame('RESCINDIDO', $evento->payload()['estado_final']);
        self::assertSame('Incumplimiento del anfitrion', $evento->payload()['motivo']);
    }

    public function test_reactivar_un_acuerdo_suspendido_vuelve_a_emitir_activado(): void
    {
        $acuerdo = $this->activo();
        $acuerdo->suspender('Revision de datos');
        $acuerdo->sacarEventos();

        $acuerdo->activar('admin-2', new DateTimeImmutable('2026-10-24 09:00:00'));

        self::assertSame(EstadoAcuerdo::ACTIVO, $acuerdo->estado());
        self::assertSame('acuerdo_comercial.activado', $this->unicoEvento($acuerdo)->tipoEvento());
    }

    public function test_un_borrador_no_se_puede_suspender(): void
    {
        $acuerdo = $this->borrador();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('BORRADOR');

        $acuerdo->suspender('Sin motivo real');
    }

    public function test_un_borrador_no_se_puede_finalizar(): void
    {
        $acuerdo = $this->borrador();

        $this->expectException(DomainException::class);

        $acuerdo->finalizar('No corresponde');
    }

    public function test_un_acuerdo_finalizado_no_admite_mas_transiciones(): void
    {
        $acuerdo = $this->activo();
        $acuerdo->finalizar('Cierre');
        $acuerdo->sacarEventos();

        $this->expectException(DomainException::class);

        $acuerdo->activar('admin-1', new DateTimeImmutable('2026-11-01 10:00:00'));
    }

    public function test_un_acuerdo_rescindido_no_se_puede_reactivar(): void
    {
        $acuerdo = $this->activo();
        $acuerdo->rescindir('Fraude');
        $acuerdo->sacarEventos();

        $this->expectException(DomainException::class);

        $acuerdo->activar('admin-1', new DateTimeImmutable('2026-11-01 10:00:00'));
    }

    public function test_un_borrador_se_puede_rescindir_para_descartarlo(): void
    {
        $acuerdo = $this->borrador();

        $acuerdo->rescindir('El anfitrion no acepto las condiciones');

        self::assertSame(EstadoAcuerdo::RESCINDIDO, $acuerdo->estado());
    }

    public function test_suspender_exige_un_motivo(): void
    {
        $acuerdo = $this->activo();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('motivo');

        $acuerdo->suspender('   ');
    }

    public function test_rechaza_dias_de_liquidacion_fuera_del_rango_permitido(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('dias de liquidacion');

        AcuerdoComercial::crearBorrador(
            acuerdoId: 'acu-1',
            anfitrionId: 'anf-1',
            fechaInicio: new DateTimeImmutable('2026-11-01'),
            diasLiquidacion: 61,
        );
    }

    public function test_rechaza_una_fecha_de_fin_anterior_al_inicio(): void
    {
        $this->expectException(DomainException::class);

        AcuerdoComercial::crearBorrador(
            acuerdoId: 'acu-1',
            anfitrionId: 'anf-1',
            fechaInicio: new DateTimeImmutable('2026-11-01'),
            fechaFin: new DateTimeImmutable('2026-10-01'),
        );
    }

    public function test_actualizar_condiciones_cambia_plazo_regla_y_clausulas(): void
    {
        $acuerdo = $this->borrador();

        $acuerdo->actualizarCondiciones(
            diasLiquidacion: 15,
            reglaComisionId: 'regla-9',
            condiciones: ['retencion' => '2.5', 'clausula' => 'Pago quincenal'],
            fechaFin: new DateTimeImmutable('2027-01-31'),
        );

        self::assertSame(15, $acuerdo->diasLiquidacion());
        self::assertSame('regla-9', $acuerdo->reglaComisionId());
        self::assertSame('2.5', $acuerdo->condiciones()['retencion']);
        self::assertSame('2027-01-31', $acuerdo->fechaFin()?->format('Y-m-d'));
    }

    public function test_un_acuerdo_finalizado_no_admite_cambio_de_condiciones(): void
    {
        $acuerdo = $this->activo();
        $acuerdo->finalizar('Cierre');
        $acuerdo->sacarEventos();

        $this->expectException(DomainException::class);

        $acuerdo->actualizarCondiciones(diasLiquidacion: 10);
    }

    public function test_reconstituir_no_revalida_ni_emite_eventos(): void
    {
        $acuerdo = AcuerdoComercial::reconstituir(
            acuerdoId: 'acu-7',
            anfitrionId: 'anf-7',
            reglaComisionId: null,
            estado: EstadoAcuerdo::SUSPENDIDO,
            fechaInicio: new DateTimeImmutable('2026-01-01'),
            fechaFin: null,
            condiciones: [],
            diasLiquidacion: 30,
            aprobadoPor: 'admin-0',
            aprobadoEn: new DateTimeImmutable('2026-01-02 08:00:00'),
            motivoEstado: 'Observaciones',
        );

        self::assertSame(EstadoAcuerdo::SUSPENDIDO, $acuerdo->estado());
        self::assertSame([], $acuerdo->sacarEventos());
    }

    private function borrador(): AcuerdoComercial
    {
        return AcuerdoComercial::crearBorrador(
            acuerdoId: 'acu-1',
            anfitrionId: 'anf-1',
            fechaInicio: new DateTimeImmutable('2026-11-01'),
        );
    }

    private function activo(): AcuerdoComercial
    {
        $acuerdo = $this->borrador();
        $acuerdo->activar('admin-1', new DateTimeImmutable('2026-10-23 10:00:00'));
        $acuerdo->sacarEventos();

        return $acuerdo;
    }

    private function unicoEvento(AcuerdoComercial $acuerdo): DomainEvent
    {
        $eventos = $acuerdo->sacarEventos();

        self::assertCount(1, $eventos);

        return $eventos[0];
    }
}

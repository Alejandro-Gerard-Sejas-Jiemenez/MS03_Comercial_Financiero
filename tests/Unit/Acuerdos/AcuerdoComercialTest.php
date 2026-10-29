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

    public function test_un_borrador_no_se_puede_suspender(): void
    {
        $acuerdo = $this->borrador();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('BORRADOR');

        $acuerdo->suspender('Sin motivo real');
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

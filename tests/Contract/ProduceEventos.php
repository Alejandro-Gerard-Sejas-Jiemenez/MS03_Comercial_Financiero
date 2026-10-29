<?php

declare(strict_types=1);

namespace Tests\Contract;

use Src\Promociones\Presentation\Messaging\PromocionesSuscriptor;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Presentation\Messaging\ConsumidorDeEventos;
use Tests\Doubles\InMemoryEventPublisher;
use Tests\Doubles\InMemoryInboxRegistry;
use Tests\Feature\ConDobles;
use Tests\Feature\EmiteTokens;

/**
 * Recorre el sistema por HTTP y por mensajeria, y devuelve los eventos de
 * dominio que MS-03 publica de verdad (los que van al Outbox).
 */
trait ProduceEventos
{
    use ConDobles;
    use EmiteTokens;

    protected const ADMIN = 'aaaaaaaa-0000-4000-8000-000000000001';

    protected const ANFITRION = 'bbbbbbbb-0000-4000-8000-000000000002';

    protected const CLIENTE = 'dddddddd-0000-4000-8000-000000000004';

    /** @return array<string, DomainEvent> tipo => ultimo evento de ese tipo */
    protected function eventosProducidos(): array
    {
        $this->prepararKeycloakFalso();
        $this->conDobles();
        (require base_path('database/migrations/2026_10_29_000011_create_idempotencia_http_table.php'))->up();
        $admin = $this->como(self::ADMIN, 'ADMIN');
        $cliente = $this->como(self::CLIENTE, 'CLIENTE');
        $anfitrion = $this->como(self::ANFITRION, 'ANFITRION');

        // acuerdo_comercial.activado
        $acuerdo = $this->postJson('api/v1/acuerdos', ['anfitrion_id' => self::ANFITRION, 'fecha_inicio' => '2026-10-01'], $admin)->json('data.acuerdo_id');
        $this->postJson("api/v1/acuerdos/{$acuerdo}/estado", ['estado' => 'ACTIVO'], $admin)->assertOk();

        // pago.capturado y pago.cancelado
        $pago = fn (string $reserva, string $clave) => $this->postJson('api/v1/pagos', [
            'reserva_id' => $reserva, 'anfitrion_id' => self::ANFITRION,
            'subtotal_centavos' => 100000, 'cargo_servicio_centavos' => 5000, 'metodo' => 'QR',
        ], $cliente + ['Idempotency-Key' => $clave])->assertCreated()->json('data.pago_id');
        $capturado = $pago('11111111-0000-4000-8000-000000000001', 'k-1');
        $this->postJson("api/v1/pagos/{$capturado}/cobro", [], $cliente + ['Idempotency-Key' => 'c-1'])->assertOk();
        $cancelado = $pago('11111111-0000-4000-8000-000000000002', 'k-2');
        $this->postJson("api/v1/pagos/{$cancelado}/cancelacion", ['motivo' => 'ya no viajo'], $cliente)->assertOk();

        // promocion.aplicada (llega un pago.capturado con promocion por mensajeria)
        $promo = $this->postJson('api/v1/promociones', [
            'nombre' => 'Verano', 'tipo' => 'TEMPORADA', 'tipo_descuento' => 'PORCENTAJE', 'valor' => 10,
            'vigente_desde' => '2026-10-01', 'vigente_hasta' => '2026-12-31',
        ], $anfitrion)->json('data.promocion_id');
        $this->postJson("api/v1/promociones/{$promo}/activacion", [], $anfitrion)->assertOk();
        (new ConsumidorDeEventos(
            [$this->app->make(PromocionesSuscriptor::class)],
            new InMemoryInboxRegistry,
            $this->app->make(TransactionManager::class),
        ))->procesar(['id' => 'e-1', 'tipo' => 'pago.capturado', 'data' => [
            'pago_id' => $capturado, 'reserva_id' => '11111111-0000-4000-8000-000000000001',
            'promocion_id' => $promo, 'descuento' => '100.00', 'moneda' => 'BOB',
        ]], 'MS-03');

        /** @var InMemoryEventPublisher $publicador */
        $publicador = $this->app->make(EventPublisher::class);
        $porTipo = [];
        foreach ($publicador->publicados() as $evento) {
            $porTipo[$evento->tipoEvento()] = $evento;
        }

        return $porTipo;
    }
}

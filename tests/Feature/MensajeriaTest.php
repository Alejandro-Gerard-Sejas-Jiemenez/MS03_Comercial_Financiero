<?php

declare(strict_types=1);

namespace Tests\Feature;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionClass;
use RuntimeException;
use Src\Promociones\Presentation\Messaging\PromocionesSuscriptor;
use Src\Shared\Application\Port\InboxRegistry;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Infrastructure\Messaging\RabbitMqMessageBroker;
use Src\Shared\Presentation\Messaging\ConsumidorDeEventos;
use Src\Shared\Presentation\Messaging\SuscriptorDeEventos;
use Tests\Doubles\InMemoryInboxRegistry;
use Tests\Doubles\SyncTransactionManager;
use Tests\TestCase;

final class MensajeriaTest extends TestCase
{
    use ConDobles;
    use EmiteTokens;

    public function test_un_mensaje_repetido_se_procesa_una_sola_vez_y_uno_que_falla_no_queda_marcado(): void
    {
        $vistos = [];
        $suscriptor = new class($vistos) implements SuscriptorDeEventos
        {
            public function __construct(public array &$vistos) {}

            public function manejadores(): array
            {
                return [
                    'a.ocurrio' => function (array $d): void {
                        $this->vistos[] = $d['n'];
                    },
                    'a.falla' => function (): void {
                        throw new RuntimeException('boom');
                    },
                ];
            }
        };
        $inbox = new InMemoryInboxRegistry;
        $consumidor = new ConsumidorDeEventos([$suscriptor], $inbox, new SyncTransactionManager);

        $mensaje = ['id' => 'm-1', 'tipo' => 'a.ocurrio', 'data' => ['n' => 7]];
        self::assertTrue($consumidor->procesar($mensaje, 'MS-02'));
        self::assertFalse($consumidor->procesar($mensaje, 'MS-02'), 'la repeticion se descarta');
        self::assertSame([7], $vistos);
        self::assertFalse($consumidor->procesar(['id' => 'm-2', 'tipo' => 'otro.evento', 'data' => []], 'MS-02'));

        try {
            $consumidor->procesar(['id' => 'm-3', 'tipo' => 'a.falla', 'data' => []], 'MS-02');
            self::fail('debio propagar el error');
        } catch (RuntimeException) {
            self::assertFalse($inbox->yaProcesado('m-3'), 'si fallo, se puede reintentar');
        }
    }

    public function test_pago_capturado_con_promocion_suma_un_uso_aunque_el_mensaje_llegue_dos_veces(): void
    {
        $this->prepararKeycloakFalso();
        $this->conDobles();
        $anfitrion = 'bbbbbbbb-0000-4000-8000-000000000002';
        $h = $this->como($anfitrion, 'ANFITRION');

        $id = $this->postJson('api/v1/promociones', [
            'nombre' => 'Verano', 'tipo' => 'TEMPORADA', 'tipo_descuento' => 'PORCENTAJE', 'valor' => 10,
            'vigente_desde' => '2026-10-01', 'vigente_hasta' => '2026-12-31', 'max_usos' => 5,
        ], $h)->assertCreated()->json('data.promocion_id');
        $this->postJson("api/v1/promociones/{$id}/activacion", [], $h)->assertOk();

        $this->app->instance(InboxRegistry::class, new InMemoryInboxRegistry);
        $this->app->instance(TransactionManager::class, new SyncTransactionManager);
        $consumidor = new ConsumidorDeEventos(
            [$this->app->make(PromocionesSuscriptor::class)],
            $this->app->make(InboxRegistry::class),
            $this->app->make(TransactionManager::class),
        );
        $mensaje = ['id' => 'evt-1', 'tipo' => 'pago.capturado', 'saga_id' => null, 'data' => [
            'pago_id' => '11111111-0000-4000-8000-000000000001', 'reserva_id' => '22222222-0000-4000-8000-000000000002',
            'promocion_id' => $id, 'descuento' => '100.00', 'moneda' => 'BOB',
        ]];

        $consumidor->procesar($mensaje, 'MS-03');
        $consumidor->procesar($mensaje, 'MS-03');

        $this->getJson("api/v1/promociones/{$id}", $h)->assertJsonPath('data.usos_actuales', 1);
    }

    public function test_el_broker_de_rabbitmq_publica_persistente_con_la_routing_key_y_envuelve_los_fallos(): void
    {
        $canal = $this->canalFalso(falla: false);
        $broker = new RabbitMqMessageBroker(static fn (): AMQPChannel => $canal, 'comercial.events');

        $broker->enviar('pago.capturado', ['id' => 'e-1', 'tipo' => 'pago.capturado', 'data' => ['x' => 1]]);

        [$msg, $exchange, $clave] = $canal->publicados[0];
        self::assertSame(['comercial.events', 'pago.capturado'], [$exchange, $clave]);
        self::assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $msg->get('delivery_mode'));
        self::assertSame('e-1', $msg->get('message_id'));
        self::assertSame(1, json_decode($msg->getBody(), true)['data']['x']);

        $roto = new RabbitMqMessageBroker(fn () => $this->canalFalso(falla: true), 'comercial.events');
        $this->expectException(RuntimeException::class);
        $roto->enviar('pago.capturado', ['id' => 'e-2']);
    }

    private function canalFalso(bool $falla): AMQPChannel
    {
        $clase = new class extends AMQPChannel
        {
            public array $publicados = [];

            public bool $falla = false;

            public function __construct() {}

            public function exchange_declare(...$args): mixed
            {
                return null;
            }

            public function basic_publish(...$args): void
            {
                if ($this->falla) {
                    throw new RuntimeException('conexion caida');
                }
                $this->publicados[] = $args;
            }
        };
        $canal = (new ReflectionClass($clase))->newInstanceWithoutConstructor();
        $canal->falla = $falla;

        return $canal;
    }
}

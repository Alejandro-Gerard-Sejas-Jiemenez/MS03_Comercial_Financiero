<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Src\Acuerdos\Application\Port\AcuerdoComercialQuery;
use Src\Acuerdos\Application\Port\CifradorDeDatos;
use Src\Acuerdos\Application\Port\MetodoCobroQuery;
use Src\Acuerdos\Domain\AcuerdoComercialRepository;
use Src\Acuerdos\Domain\MetodoCobroRepository;
use Src\Acuerdos\Infrastructure\Persistence\EloquentAcuerdoComercialQuery;
use Src\Acuerdos\Infrastructure\Persistence\EloquentAcuerdoComercialRepository;
use Src\Acuerdos\Infrastructure\Persistence\EloquentMetodoCobroQuery;
use Src\Acuerdos\Infrastructure\Persistence\EloquentMetodoCobroRepository;
use Src\Acuerdos\Infrastructure\Security\CryptCifradorDeDatos;
use Src\Liquidaciones\Application\Port\ConsultaDeReportes;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\LibroContableRepository;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Liquidaciones\Infrastructure\Persistence\EloquentComisionRepository;
use Src\Liquidaciones\Infrastructure\Persistence\EloquentConsultaDeReportes;
use Src\Liquidaciones\Infrastructure\Persistence\EloquentLibroContableRepository;
use Src\Liquidaciones\Infrastructure\Persistence\EloquentLiquidacionRepository;
use Src\Liquidaciones\Presentation\Console\GenerarLiquidacionesCommand;
use Src\Liquidaciones\Presentation\Messaging\LiquidacionesSuscriptor;
use Src\Monetizacion\Domain\ReglaMonetizacionRepository;
use Src\Monetizacion\Infrastructure\Persistence\EloquentReglaMonetizacionRepository;
use Src\Pagos\Application\Port\NumeradorComprobantes;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Domain\ComprobanteRepository;
use Src\Pagos\Domain\ConciliacionRepository;
use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Pagos\Domain\TransaccionPagoRepository;
use Src\Pagos\Infrastructure\Gateway\PasarelaPagoSimulada;
use Src\Pagos\Infrastructure\Numeracion\NumeradorComprobantesSecuencial;
use Src\Pagos\Infrastructure\Persistence\EloquentComprobanteRepository;
use Src\Pagos\Infrastructure\Persistence\EloquentConciliacionRepository;
use Src\Pagos\Infrastructure\Persistence\EloquentPagoRepository;
use Src\Pagos\Infrastructure\Persistence\EloquentReembolsoRepository;
use Src\Pagos\Infrastructure\Persistence\EloquentTransaccionPagoRepository;
use Src\Pagos\Presentation\Console\EjecutarConciliacionCommand;
use Src\Pagos\Presentation\Console\ExpirarPagosPendientesCommand;
use Src\Pagos\Presentation\Messaging\PagosSuscriptor;
use Src\Promociones\Application\Port\CalculadorDeDescuento;
use Src\Promociones\Application\UseCase\ValidarCuponYCalcularDescuento\ValidarCuponYCalcularDescuentoHandler;
use Src\Promociones\Domain\PromocionRepository;
use Src\Promociones\Infrastructure\Persistence\EloquentPromocionRepository;
use Src\Promociones\Presentation\Console\VencerPromocionesCommand;
use Src\Promociones\Presentation\Messaging\PromocionesSuscriptor;
use Src\Referencias\Domain\AnfitrionReferenceRepository;
use Src\Referencias\Domain\ReservaReferenceRepository;
use Src\Referencias\Infrastructure\Persistence\EloquentAnfitrionReferenceRepository;
use Src\Referencias\Infrastructure\Persistence\EloquentReservaReferenceRepository;
use Src\Referencias\Presentation\Messaging\ReferenciasSuscriptor;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\InboxRegistry;
use Src\Shared\Application\Port\MessageBroker;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\OutboxRepository;
use Src\Shared\Infrastructure\Messaging\LogMessageBroker;
use Src\Shared\Infrastructure\Messaging\OutboxEventPublisher;
use Src\Shared\Infrastructure\Messaging\RabbitMqMessageBroker;
use Src\Shared\Infrastructure\Persistence\EloquentAuditLogger;
use Src\Shared\Infrastructure\Persistence\EloquentInboxRegistry;
use Src\Shared\Infrastructure\Persistence\EloquentOutboxRepository;
use Src\Shared\Infrastructure\Persistence\EloquentTransactionManager;
use Src\Shared\Infrastructure\System\LaravelUuidGenerator;
use Src\Shared\Infrastructure\System\SystemClock;
use Src\Shared\Presentation\Auth\AutenticadorDeSolicitud;
use Src\Shared\Presentation\Auth\KeycloakJwtAutenticador;
use Src\Shared\Presentation\Console\ConsumirMensajesCommand;
use Src\Shared\Presentation\Console\GenerarOpenApiCommand;
use Src\Shared\Presentation\Console\PublicarOutboxCommand;
use Src\Shared\Presentation\Http\Middleware\AutenticarSesion;
use Src\Shared\Presentation\Http\Middleware\IdempotenciaMiddleware;
use Src\Shared\Presentation\Http\Middleware\RequiereRol;
use Src\Shared\Presentation\Messaging\ConsumidorDeEventos;

/**
 * Enlaza cada interfaz de MS-03 con su implementacion.
 *
 * Es el unico lugar del proyecto donde el nucleo (Dominio y Aplicacion) se
 * encuentra con la infraestructura. Cambiar de proveedor de pagos, de broker o
 * de base de datos es cambiar una linea aqui: ningun caso de uso se toca.
 *
 * Tambien registra la capa HTTP: autenticador, alias de middleware y las
 * rutas de cada paquete (`src/<Paquete>/Presentation/Http/routes.php`).
 */
final class ComercialServiceProvider extends ServiceProvider
{
    /**
     * Interfaz => implementacion. Todas como singleton: no guardan estado de
     * una peticion, solo traducen.
     *
     * @var array<class-string, class-string>
     */
    private const PAQUETES_CON_RUTAS = [
        'Shared', 'Pagos', 'Promociones', 'Acuerdos', 'Monetizacion', 'Liquidaciones', 'Referencias',
    ];

    private const ENLACES = [
        // Shared: transversal
        OutboxRepository::class => EloquentOutboxRepository::class,
        EventPublisher::class => OutboxEventPublisher::class,
        TransactionManager::class => EloquentTransactionManager::class,
        InboxRegistry::class => EloquentInboxRegistry::class,
        AuditLogger::class => EloquentAuditLogger::class,
        Clock::class => SystemClock::class,
        UuidGenerator::class => LaravelUuidGenerator::class,

        // Referencias: copias locales de MS-01 y MS-02
        AnfitrionReferenceRepository::class => EloquentAnfitrionReferenceRepository::class,
        ReservaReferenceRepository::class => EloquentReservaReferenceRepository::class,

        // Monetizacion (F16)
        ReglaMonetizacionRepository::class => EloquentReglaMonetizacionRepository::class,

        // Acuerdos (F17)
        AcuerdoComercialRepository::class => EloquentAcuerdoComercialRepository::class,
        MetodoCobroRepository::class => EloquentMetodoCobroRepository::class,
        AcuerdoComercialQuery::class => EloquentAcuerdoComercialQuery::class,
        MetodoCobroQuery::class => EloquentMetodoCobroQuery::class,
        CifradorDeDatos::class => CryptCifradorDeDatos::class,

        // Promociones (F09)
        PromocionRepository::class => EloquentPromocionRepository::class,
        CalculadorDeDescuento::class => ValidarCuponYCalcularDescuentoHandler::class,

        // Pagos (F05)
        PagoRepository::class => EloquentPagoRepository::class,
        TransaccionPagoRepository::class => EloquentTransaccionPagoRepository::class,
        ReembolsoRepository::class => EloquentReembolsoRepository::class,
        ComprobanteRepository::class => EloquentComprobanteRepository::class,
        ConciliacionRepository::class => EloquentConciliacionRepository::class,
        NumeradorComprobantes::class => NumeradorComprobantesSecuencial::class,

        // Liquidaciones (F05 + F16)
        ComisionRepository::class => EloquentComisionRepository::class,
        LiquidacionRepository::class => EloquentLiquidacionRepository::class,
        LibroContableRepository::class => EloquentLibroContableRepository::class,
        ConsultaDeReportes::class => EloquentConsultaDeReportes::class,
    ];

    public function register(): void
    {
        foreach (self::ENLACES as $interfaz => $implementacion) {
            $this->app->singleton($interfaz, $implementacion);
        }

        $this->registrarBroker();
        $this->registrarPasarela();
        $this->registrarAutenticador();
        $this->registrarConsumidor();
    }

    public function boot(Router $router): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PublicarOutboxCommand::class,
                GenerarOpenApiCommand::class,
                ConsumirMensajesCommand::class,
                VencerPromocionesCommand::class,
                GenerarLiquidacionesCommand::class,
                EjecutarConciliacionCommand::class,
                ExpirarPagosPendientesCommand::class,
            ]);
        }

        $router->aliasMiddleware('sesion', AutenticarSesion::class);
        $router->aliasMiddleware('rol', RequiereRol::class);
        $router->aliasMiddleware('idempotente', IdempotenciaMiddleware::class);

        foreach (self::PAQUETES_CON_RUTAS as $paquete) {
            $archivo = base_path("src/{$paquete}/Presentation/Http/routes.php");
            if (is_file($archivo)) {
                $this->loadRoutesFrom($archivo);
            }
        }
    }

    /**
     * Para cambiar de proveedor de identidad se cambia solo este enlace.
     * El JWKS de Keycloak se cachea para no pedirlo en cada peticion.
     */
    private function registrarAutenticador(): void
    {
        $this->app->singleton(
            AutenticadorDeSolicitud::class,
            static fn (): AutenticadorDeSolicitud => new KeycloakJwtAutenticador(
                (string) config('comercial.keycloak.issuer'),
                (string) config('comercial.keycloak.audience'),
                static fn (): array => Cache::remember(
                    'comercial.keycloak.jwks',
                    (int) config('comercial.keycloak.jwks_ttl'),
                    static fn (): array => Http::timeout(5)
                        ->get((string) config('comercial.keycloak.jwks_url'))
                        ->throw()
                        ->json(),
                ),
            ),
        );
    }

    /**
     * El broker de mensajeria se elige por configuracion. Hoy solo esta el de
     * log; el adaptador de RabbitMQ entra aqui cuando exista la capa de
     * Presentation/Messaging.
     */
    private function registrarBroker(): void
    {
        $this->app->singleton(MessageBroker::class, static function (): MessageBroker {
            if (config('comercial.broker.driver') === 'rabbitmq') {
                $c = (array) config('comercial.rabbitmq');

                return new RabbitMqMessageBroker(
                    static fn () => AMQPConnectionFactory::create(
                        (new AMQPConnectionConfig)
                            ->setHost((string) $c['host'])
                            ->setPort((int) $c['port'])
                            ->setUser((string) $c['usuario'])
                            ->setPassword((string) $c['password'])
                            ->setIsLazy(true),
                    )->channel(),
                    (string) $c['exchange'],
                );
            }

            return new LogMessageBroker((string) config('comercial.outbox.canal_log', 'stack'));
        });
    }

    /**
     * Quien reacciona a cada evento. El orden importa: Referencias sincroniza
     * la reserva antes de que Pagos calcule un reembolso con sus fechas.
     */
    private function registrarConsumidor(): void
    {
        $this->app->singleton(
            ConsumidorDeEventos::class,
            static fn ($app): ConsumidorDeEventos => new ConsumidorDeEventos(
                array_map($app->make(...), [
                    ReferenciasSuscriptor::class,
                    PagosSuscriptor::class,
                    PromocionesSuscriptor::class,
                    LiquidacionesSuscriptor::class,
                ]),
                $app->make(InboxRegistry::class),
                $app->make(TransactionManager::class),
            ),
        );
    }

    /**
     * Este es el `bind` que el documento de arquitectura pone como ejemplo:
     * para cambiar de proveedor de pagos se cambia solo esta linea.
     */
    private function registrarPasarela(): void
    {
        $this->app->singleton(
            PasarelaPago::class,
            static fn (): PasarelaPago => new PasarelaPagoSimulada(
                (string) config('comercial.pasarela.nombre', 'SIMULADA'),
            ),
        );
    }
}

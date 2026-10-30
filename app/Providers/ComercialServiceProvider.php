<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Src\Comercial\Adapters\Console\VencerPromocionesCommand;
use Src\Comercial\Adapters\Messaging\ComercialSuscriptor;
use Src\Comercial\Application\Port\CalculadorDeDescuento;
use Src\Comercial\Application\Port\PromocionRepository;
use Src\Comercial\Application\UseCase\AplicarPromocion\AplicarPromocionService;
use Src\Comercial\Application\UseCase\AplicarPromocion\AplicarPromocionUseCase;
use Src\Comercial\Application\UseCase\GestionarPromociones\GestionarPromocionesService;
use Src\Comercial\Application\UseCase\GestionarPromociones\GestionarPromocionesUseCase;
use Src\Comercial\Infrastructure\Persistence\Repository\EloquentPromocionRepository;
use Src\Financiero\Adapters\Console\EjecutarConciliacionCommand;
use Src\Financiero\Adapters\Console\ExpirarPagosPendientesCommand;
use Src\Financiero\Adapters\Console\GenerarLiquidacionesCommand;
use Src\Financiero\Adapters\Messaging\FinancieroSuscriptor;
use Src\Financiero\Application\Port\ComisionRepository;
use Src\Financiero\Application\Port\ComprobanteRepository;
use Src\Financiero\Application\Port\ConciliacionRepository;
use Src\Financiero\Application\Port\ConsultaDeReportes;
use Src\Financiero\Application\Port\LibroContableRepository;
use Src\Financiero\Application\Port\LiquidacionRepository;
use Src\Financiero\Application\Port\NumeradorComprobantes;
use Src\Financiero\Application\Port\PagoRepository;
use Src\Financiero\Application\Port\PasarelaPago;
use Src\Financiero\Application\Port\ReembolsoRepository;
use Src\Financiero\Application\Port\TransaccionPagoRepository;
use Src\Financiero\Application\UseCase\ConciliarTransacciones\ConciliarTransaccionesService;
use Src\Financiero\Application\UseCase\ConciliarTransacciones\ConciliarTransaccionesUseCase;
use Src\Financiero\Application\UseCase\ConsultarPagos\ConsultarPagosService;
use Src\Financiero\Application\UseCase\ConsultarPagos\ConsultarPagosUseCase;
use Src\Financiero\Application\UseCase\ConsultarSaldosYReportes\ConsultarSaldosYReportesService;
use Src\Financiero\Application\UseCase\ConsultarSaldosYReportes\ConsultarSaldosYReportesUseCase;
use Src\Financiero\Application\UseCase\GestionarComisiones\GestionarComisionesService;
use Src\Financiero\Application\UseCase\GestionarComisiones\GestionarComisionesUseCase;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\GestionarLiquidacionesService;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\GestionarLiquidacionesUseCase;
use Src\Financiero\Application\UseCase\GestionarReembolsos\GestionarReembolsosService;
use Src\Financiero\Application\UseCase\GestionarReembolsos\GestionarReembolsosUseCase;
use Src\Financiero\Application\UseCase\ProcesarPago\ProcesarPagoService;
use Src\Financiero\Application\UseCase\ProcesarPago\ProcesarPagoUseCase;
use Src\Financiero\Infrastructure\Gateway\PasarelaPagoSimulada;
use Src\Financiero\Infrastructure\Numeracion\NumeradorComprobantesSecuencial;
use Src\Financiero\Infrastructure\Persistence\Query\EloquentConsultaDeReportes;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentComisionRepository;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentComprobanteRepository;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentConciliacionRepository;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentLibroContableRepository;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentLiquidacionRepository;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentPagoRepository;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentReembolsoRepository;
use Src\Financiero\Infrastructure\Persistence\Repository\EloquentTransaccionPagoRepository;
use Src\Monetizacion\Application\Port\CalculadorDeDesglose;
use Src\Monetizacion\Application\Port\ReglaMonetizacionRepository;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialService;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialUseCase;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\GestionarReglasMonetizacionService;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\GestionarReglasMonetizacionUseCase;
use Src\Monetizacion\Infrastructure\Persistence\Repository\EloquentReglaMonetizacionRepository;
use Src\Relaciones\Adapters\Console\FinalizarAcuerdosVencidosCommand;
use Src\Relaciones\Application\Port\AcuerdoComercialQuery;
use Src\Relaciones\Application\Port\AcuerdoComercialRepository;
use Src\Relaciones\Application\Port\CifradorDeDatos;
use Src\Relaciones\Application\Port\MetodoCobroQuery;
use Src\Relaciones\Application\Port\MetodoCobroRepository;
use Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales\GestionarAcuerdosComercialesService;
use Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales\GestionarAcuerdosComercialesUseCase;
use Src\Relaciones\Application\UseCase\GestionarMetodosCobro\GestionarMetodosCobroService;
use Src\Relaciones\Application\UseCase\GestionarMetodosCobro\GestionarMetodosCobroUseCase;
use Src\Relaciones\Infrastructure\Persistence\Query\EloquentAcuerdoComercialQuery;
use Src\Relaciones\Infrastructure\Persistence\Query\EloquentMetodoCobroQuery;
use Src\Relaciones\Infrastructure\Persistence\Repository\EloquentAcuerdoComercialRepository;
use Src\Relaciones\Infrastructure\Persistence\Repository\EloquentMetodoCobroRepository;
use Src\Relaciones\Infrastructure\Security\CryptCifradorDeDatos;
use Src\Shared\Adapters\Console\PublicarOutboxCommand;
use Src\Shared\Adapters\Console\VerificarArquitecturaCommand;
use Src\Shared\Adapters\Http\Middleware\AutenticarSesion;
use Src\Shared\Adapters\Http\Middleware\IdempotenciaMiddleware;
use Src\Shared\Adapters\Http\Middleware\RequiereRol;
use Src\Shared\Adapters\Messaging\ConsumidorDeEventos;
use Src\Shared\Adapters\Messaging\ReferenciasSuscriptor;
use Src\Shared\Application\Auth\AutenticadorDeSolicitud;
use Src\Shared\Application\Port\AnfitrionReferenceRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\InboxRegistry;
use Src\Shared\Application\Port\MessageBroker;
use Src\Shared\Application\Port\OutboxRepository;
use Src\Shared\Application\Port\ReservaReferenceRepository;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Application\UseCase\PublicarEventos\PublicarEventosService;
use Src\Shared\Application\UseCase\PublicarEventos\PublicarEventosUseCase;
use Src\Shared\Application\UseCase\SincronizarReferencias\SincronizarReferenciasService;
use Src\Shared\Application\UseCase\SincronizarReferencias\SincronizarReferenciasUseCase;
use Src\Shared\Infrastructure\Auth\KeycloakJwtAutenticador;
use Src\Shared\Infrastructure\Console\ConsumirMensajesCommand;
use Src\Shared\Infrastructure\Messaging\ConexionRabbitMq;
use Src\Shared\Infrastructure\Messaging\LogMessageBroker;
use Src\Shared\Infrastructure\Messaging\OutboxEventPublisher;
use Src\Shared\Infrastructure\Messaging\RabbitMqMessageBroker;
use Src\Shared\Infrastructure\Persistence\Repository\EloquentAnfitrionReferenceRepository;
use Src\Shared\Infrastructure\Persistence\Repository\EloquentOutboxRepository;
use Src\Shared\Infrastructure\Persistence\Repository\EloquentReservaReferenceRepository;
use Src\Shared\Infrastructure\Persistence\Service\EloquentAuditLogger;
use Src\Shared\Infrastructure\Persistence\Service\EloquentInboxRegistry;
use Src\Shared\Infrastructure\Persistence\Service\EloquentTransactionManager;
use Src\Shared\Infrastructure\System\LaravelUuidGenerator;
use Src\Shared\Infrastructure\System\SystemClock;

/**
 * Enlaza cada interfaz de MS-03 con su implementacion.
 *
 * Es el unico lugar del proyecto donde el nucleo (Dominio y Aplicacion) se
 * encuentra con la infraestructura. Cambiar de proveedor de pagos, de broker o
 * de base de datos es cambiar una linea aqui: ningun caso de uso se toca.
 *
 * Tambien registra la capa HTTP: autenticador, alias de middleware y las
 * rutas de cada modulo (`src/<Modulo>/Adapters/Http/routes*.php`).
 */
final class ComercialServiceProvider extends ServiceProvider
{
    /**
     * Interfaz => implementacion. Todas como singleton: no guardan estado de
     * una peticion, solo traducen.
     *
     * @var array<class-string, class-string>
     */
    private const MODULOS_CON_RUTAS = [
        'Shared', 'Financiero', 'Comercial', 'Relaciones', 'Monetizacion',
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

        // Shared: copias locales de MS-01 y MS-02
        AnfitrionReferenceRepository::class => EloquentAnfitrionReferenceRepository::class,
        ReservaReferenceRepository::class => EloquentReservaReferenceRepository::class,

        // Monetizacion (F16)
        ReglaMonetizacionRepository::class => EloquentReglaMonetizacionRepository::class,

        // Relaciones (F17)
        AcuerdoComercialRepository::class => EloquentAcuerdoComercialRepository::class,
        MetodoCobroRepository::class => EloquentMetodoCobroRepository::class,
        AcuerdoComercialQuery::class => EloquentAcuerdoComercialQuery::class,
        MetodoCobroQuery::class => EloquentMetodoCobroQuery::class,
        CifradorDeDatos::class => CryptCifradorDeDatos::class,

        // Comercial (F09)
        PromocionRepository::class => EloquentPromocionRepository::class,
        CalculadorDeDescuento::class => AplicarPromocionService::class,

        // Financiero: pagos (F05)
        PagoRepository::class => EloquentPagoRepository::class,
        TransaccionPagoRepository::class => EloquentTransaccionPagoRepository::class,
        ReembolsoRepository::class => EloquentReembolsoRepository::class,
        ComprobanteRepository::class => EloquentComprobanteRepository::class,
        ConciliacionRepository::class => EloquentConciliacionRepository::class,
        NumeradorComprobantes::class => NumeradorComprobantesSecuencial::class,

        // Financiero: liquidaciones (F05 + F16)
        ComisionRepository::class => EloquentComisionRepository::class,
        LiquidacionRepository::class => EloquentLiquidacionRepository::class,
        LibroContableRepository::class => EloquentLibroContableRepository::class,
        ConsultaDeReportes::class => EloquentConsultaDeReportes::class,
        CalculadorDeDesglose::class => CalcularDesgloseComercialService::class,

        // Casos de uso (Input Boundary => servicio)
        ProcesarPagoUseCase::class => ProcesarPagoService::class,
        ConsultarPagosUseCase::class => ConsultarPagosService::class,
        GestionarReembolsosUseCase::class => GestionarReembolsosService::class,
        ConciliarTransaccionesUseCase::class => ConciliarTransaccionesService::class,
        GestionarComisionesUseCase::class => GestionarComisionesService::class,
        GestionarLiquidacionesUseCase::class => GestionarLiquidacionesService::class,
        ConsultarSaldosYReportesUseCase::class => ConsultarSaldosYReportesService::class,
        GestionarPromocionesUseCase::class => GestionarPromocionesService::class,
        AplicarPromocionUseCase::class => AplicarPromocionService::class,
        GestionarReglasMonetizacionUseCase::class => GestionarReglasMonetizacionService::class,
        CalcularDesgloseComercialUseCase::class => CalcularDesgloseComercialService::class,
        GestionarAcuerdosComercialesUseCase::class => GestionarAcuerdosComercialesService::class,
        GestionarMetodosCobroUseCase::class => GestionarMetodosCobroService::class,
        SincronizarReferenciasUseCase::class => SincronizarReferenciasService::class,
        PublicarEventosUseCase::class => PublicarEventosService::class,
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
        $this->crearEsquemaAntesDeMigrar();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublicarOutboxCommand::class,
                ConsumirMensajesCommand::class,
                VencerPromocionesCommand::class,
                FinalizarAcuerdosVencidosCommand::class,
                VerificarArquitecturaCommand::class,
                GenerarLiquidacionesCommand::class,
                EjecutarConciliacionCommand::class,
                ExpirarPagosPendientesCommand::class,
            ]);
        }

        $router->aliasMiddleware('sesion', AutenticarSesion::class);
        $router->aliasMiddleware('rol', RequiereRol::class);
        $router->aliasMiddleware('idempotente', IdempotenciaMiddleware::class);

        foreach (self::MODULOS_CON_RUTAS as $modulo) {
            foreach (glob(base_path("src/{$modulo}/Adapters/Http/routes*.php")) ?: [] as $archivo) {
                $this->loadRoutesFrom($archivo);
            }
        }
    }

    /**
     * Con `search_path = comercial,public`, si el esquema `comercial` todavia no
     * existe Laravel crea la tabla `migrations` en `public`, y en la corrida
     * siguiente no la encuentra (la busca en `comercial`), vuelve a correr todo y
     * falla con "relation already exists". Crear el esquema antes de migrar lo evita.
     */
    private function crearEsquemaAntesDeMigrar(): void
    {
        Event::listen(CommandStarting::class, static function (CommandStarting $evento): void {
            if (! str_starts_with((string) $evento->command, 'migrate') || config('database.default') !== 'pgsql') {
                return;
            }

            $esquema = trim(explode(',', (string) config('database.connections.pgsql.search_path'))[0] ?? '');
            if (preg_match('/^[a-z_][a-z0-9_]*$/', $esquema) !== 1) {
                return;
            }

            try {
                DB::statement("CREATE SCHEMA IF NOT EXISTS {$esquema}");
            } catch (\Throwable) {
                // Sin base de datos alcanzable, que lo reporte el propio migrate.
            }
        });
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
     * El broker de mensajeria se elige por configuracion. Por omision se usa el de
     * log; con `COMERCIAL_BROKER_DRIVER=rabbitmq` se usa el de RabbitMQ.
     */
    private function registrarBroker(): void
    {
        $this->app->singleton(MessageBroker::class, static function (): MessageBroker {
            if (config('comercial.broker.driver') === 'rabbitmq') {
                $c = (array) config('comercial.rabbitmq');

                return new RabbitMqMessageBroker(
                    static fn () => ConexionRabbitMq::desde($c)->channel(),
                    (string) $c['exchange'],
                );
            }

            return new LogMessageBroker((string) config('comercial.outbox.canal_log', 'stack'));
        });
    }

    /**
     * Quien reacciona a cada evento. El orden importa: `ReferenciasSuscriptor` sincroniza
     * la reserva antes de que Financiero calcule un reembolso con sus fechas.
     */
    private function registrarConsumidor(): void
    {
        $this->app->singleton(
            ConsumidorDeEventos::class,
            static fn ($app): ConsumidorDeEventos => new ConsumidorDeEventos(
                array_map($app->make(...), [
                    ReferenciasSuscriptor::class,
                    ComercialSuscriptor::class,
                    FinancieroSuscriptor::class,
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

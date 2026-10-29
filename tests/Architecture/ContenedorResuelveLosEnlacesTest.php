<?php

declare(strict_types=1);

namespace Tests\Architecture;

use ReflectionClass;
use Src\Acuerdos\Application\Port\AcuerdoComercialQuery;
use Src\Acuerdos\Application\Port\CifradorDeDatos;
use Src\Acuerdos\Application\Port\MetodoCobroQuery;
use Src\Acuerdos\Domain\AcuerdoComercialRepository;
use Src\Acuerdos\Domain\MetodoCobroRepository;
use Src\Liquidaciones\Application\Port\ConsultaDeReportes;
use Src\Liquidaciones\Application\UseCase\GenerarLiquidaciones\GenerarLiquidacionesHandler;
use Src\Liquidaciones\Application\UseCase\PagarLiquidacion\PagarLiquidacionHandler;
use Src\Liquidaciones\Application\UseCase\RegistrarComision\RegistrarComisionHandler;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\LibroContableRepository;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Monetizacion\Domain\ReglaMonetizacionRepository;
use Src\Pagos\Application\Port\NumeradorComprobantes;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Application\UseCase\CobrarPago\CobrarPagoHandler;
use Src\Pagos\Application\UseCase\CotizarReserva\CotizarReservaHandler;
use Src\Pagos\Application\UseCase\ProcesarReembolso\ProcesarReembolsoHandler;
use Src\Pagos\Domain\ComprobanteRepository;
use Src\Pagos\Domain\ConciliacionRepository;
use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Pagos\Domain\TransaccionPagoRepository;
use Src\Promociones\Application\Port\CalculadorDeDescuento;
use Src\Promociones\Domain\PromocionRepository;
use Src\Referencias\Domain\AnfitrionReferenceRepository;
use Src\Referencias\Domain\ReservaReferenceRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\InboxRegistry;
use Src\Shared\Application\Port\MessageBroker;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\OutboxRepository;
use Tests\TestCase;

/**
 * El `ComercialServiceProvider` enlaza cada interfaz con su implementacion. Si
 * alguien agrega un puerto y se olvida del enlace, o renombra una clase de
 * infraestructura, esto se pone rojo en vez de reventar en produccion la
 * primera vez que se llame al caso de uso.
 *
 * Resolver no toca la base de datos: solo construye objetos.
 */
final class ContenedorResuelveLosEnlacesTest extends TestCase
{
    /** @return list<array{class-string}> */
    public static function puertos(): array
    {
        return array_map(static fn (string $puerto): array => [$puerto], [
            // Shared
            OutboxRepository::class,
            EventPublisher::class,
            TransactionManager::class,
            InboxRegistry::class,
            AuditLogger::class,
            Clock::class,
            UuidGenerator::class,
            MessageBroker::class,
            // Referencias
            AnfitrionReferenceRepository::class,
            ReservaReferenceRepository::class,
            // Monetizacion
            ReglaMonetizacionRepository::class,
            // Acuerdos
            AcuerdoComercialRepository::class,
            MetodoCobroRepository::class,
            AcuerdoComercialQuery::class,
            MetodoCobroQuery::class,
            CifradorDeDatos::class,
            // Promociones
            PromocionRepository::class,
            CalculadorDeDescuento::class,
            // Pagos
            PagoRepository::class,
            TransaccionPagoRepository::class,
            ReembolsoRepository::class,
            ComprobanteRepository::class,
            ConciliacionRepository::class,
            PasarelaPago::class,
            NumeradorComprobantes::class,
            // Liquidaciones
            ComisionRepository::class,
            LiquidacionRepository::class,
            LibroContableRepository::class,
            ConsultaDeReportes::class,
        ]);
    }

    public function test_cada_puerto_tiene_su_implementacion(): void
    {
        foreach (self::puertos() as [$puerto]) {
            $resuelto = $this->app->make($puerto);

            self::assertInstanceOf($puerto, $resuelto, $puerto);
            self::assertTrue(
                (new ReflectionClass($resuelto))->getNamespaceName() !== '',
                "{$puerto}: la implementacion debe ser una clase con namespace propio",
            );
        }
    }

    /** @return list<array{class-string}> */
    public static function casosDeUsoConMuchasDependencias(): array
    {
        return array_map(static fn (string $clase): array => [$clase], [
            CotizarReservaHandler::class,
            CobrarPagoHandler::class,
            ProcesarReembolsoHandler::class,
            RegistrarComisionHandler::class,
            GenerarLiquidacionesHandler::class,
            PagarLiquidacionHandler::class,
        ]);
    }

    /**
     * Los casos de uso que cruzan paquetes son los que mas facil se rompen:
     * si el contenedor los arma, el grafo completo de puertos esta enlazado.
     */
    public function test_el_contenedor_arma_los_casos_de_uso_que_cruzan_paquetes(): void
    {
        foreach (self::casosDeUsoConMuchasDependencias() as [$handler]) {
            self::assertInstanceOf($handler, $this->app->make($handler), $handler);
        }
    }
}

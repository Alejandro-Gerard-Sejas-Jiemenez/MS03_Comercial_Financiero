<?php

declare(strict_types=1);

namespace Tests\Feature;

use Src\Acuerdos\Domain\AcuerdoComercialRepository;
use Src\Monetizacion\Domain\ReglaMonetizacionRepository;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Domain\ComprobanteRepository;
use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Pagos\Domain\TransaccionPagoRepository;
use Src\Promociones\Domain\PromocionRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Tests\Doubles\FakePasarelaPago;
use Tests\Doubles\FrozenClock;
use Tests\Doubles\InMemoryAcuerdoComercialRepository;
use Tests\Doubles\InMemoryComprobanteRepository;
use Tests\Doubles\InMemoryEventPublisher;
use Tests\Doubles\InMemoryPagoRepository;
use Tests\Doubles\InMemoryPromocionRepository;
use Tests\Doubles\InMemoryReembolsoRepository;
use Tests\Doubles\InMemoryReglaMonetizacionRepository;
use Tests\Doubles\InMemoryTransaccionPagoRepository;
use Tests\Doubles\SequentialUuidGenerator;
use Tests\Doubles\SpyAuditLogger;
use Tests\Doubles\SyncTransactionManager;

/**
 * Cambia los repositorios de Eloquent por los de memoria: las pruebas HTTP
 * recorren ruta, middleware, controlador y caso de uso reales sin PostgreSQL.
 */
trait ConDobles
{
    protected function conDobles(): void
    {
        $this->app->instance(PromocionRepository::class, new InMemoryPromocionRepository);
        $this->app->instance(ReglaMonetizacionRepository::class, new InMemoryReglaMonetizacionRepository);
        $this->app->instance(AcuerdoComercialRepository::class, new InMemoryAcuerdoComercialRepository);
        $this->app->instance(PagoRepository::class, new InMemoryPagoRepository);
        $this->app->instance(TransaccionPagoRepository::class, new InMemoryTransaccionPagoRepository);
        $this->app->instance(ReembolsoRepository::class, new InMemoryReembolsoRepository);
        $this->app->instance(ComprobanteRepository::class, new InMemoryComprobanteRepository);
        $this->app->instance(PasarelaPago::class, new FakePasarelaPago);
        $this->app->instance(TransactionManager::class, new SyncTransactionManager);
        $this->app->instance(EventPublisher::class, new InMemoryEventPublisher);
        $this->app->instance(AuditLogger::class, new SpyAuditLogger);
        $this->app->instance(UuidGenerator::class, new SequentialUuidGenerator);
        $this->app->instance(Clock::class, new FrozenClock);
    }

    /** @return array<string, string> */
    protected function como(string $usuarioId, string ...$roles): array
    {
        return ['Authorization' => 'Bearer '.$this->token(['sub' => $usuarioId], array_values($roles))];
    }
}

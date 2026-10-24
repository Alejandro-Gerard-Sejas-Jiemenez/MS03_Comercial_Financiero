<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Gateway;

use DateTimeImmutable;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Application\Port\ResultadoCobro;
use Src\Pagos\Application\Port\ResultadoDevolucion;
use Src\Pagos\Domain\MetodoPago;
use Src\Shared\Domain\Money;

/**
 * Pasarela de desarrollo: aprueba todo y devuelve una referencia derivada de la
 * `idempotencyKey`, asi un reintento con la misma clave produce la misma
 * referencia y el filtro de idempotencia se puede probar de punta a punta.
 *
 * El adaptador real (`PasarelaPagoHttp`, con el cliente HTTP del proveedor)
 * implementa esta misma interfaz y se cambia con una linea en el
 * `ComercialServiceProvider`. Ningun caso de uso se toca: es el ejemplo de
 * "para cambiar de proveedor de pagos se cambia solo el bind" del documento de
 * arquitectura.
 */
final class PasarelaPagoSimulada implements PasarelaPago
{
    public function __construct(private readonly string $nombre = 'SIMULADA')
    {
    }

    public function cobrar(
        string $pagoId,
        Money $monto,
        string $idempotencyKey,
        MetodoPago $metodo,
    ): ResultadoCobro {
        return ResultadoCobro::exitoso(
            $this->referencia('cobro', $idempotencyKey),
            [
                'simulada' => true,
                'pago_id' => $pagoId,
                'monto' => $monto->aDecimal(),
                'moneda' => $monto->moneda(),
                'metodo' => $metodo->value,
            ],
        );
    }

    public function devolver(
        string $pagoId,
        Money $monto,
        string $referenciaOriginal,
        string $idempotencyKey,
    ): ResultadoDevolucion {
        return ResultadoDevolucion::exitoso(
            $this->referencia('devolucion', $idempotencyKey),
            [
                'simulada' => true,
                'pago_id' => $pagoId,
                'monto' => $monto->aDecimal(),
                'referencia_original' => $referenciaOriginal,
            ],
        );
    }

    public function reporteDelDia(DateTimeImmutable $fechaCorte): array
    {
        // Sin proveedor real no hay reporte: la conciliacion de ese corte
        // quedara con diferencias, que es la respuesta honesta.
        return [];
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    private function referencia(string $prefijo, string $idempotencyKey): string
    {
        return $prefijo . '-' . substr(hash('sha256', $idempotencyKey), 0, 24);
    }
}

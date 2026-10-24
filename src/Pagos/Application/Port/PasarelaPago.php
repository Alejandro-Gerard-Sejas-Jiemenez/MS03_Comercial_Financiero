<?php

declare(strict_types=1);

namespace Src\Pagos\Application\Port;

use DateTimeImmutable;
use Src\Pagos\Domain\MetodoPago;
use Src\Pagos\Domain\MovimientoPasarela;
use Src\Shared\Domain\Money;

/**
 * Puerto hacia la pasarela de pagos: lo define la capa de Aplicacion y lo
 * implementa Infraestructura.
 *
 * Es el ejemplo del documento de arquitectura: para cambiar de proveedor de
 * pagos se cambia solo el `bind` de esta interfaz, y ningun caso de uso se
 * toca. En las pruebas se reemplaza por un doble en memoria.
 */
interface PasarelaPago
{
    /**
     * Cobra el monto. La `idempotencyKey` es la misma que recibio MS-03, para
     * que un reintento no cobre dos veces ni aqui ni en el proveedor.
     */
    public function cobrar(
        string $pagoId,
        Money $monto,
        string $idempotencyKey,
        MetodoPago $metodo,
    ): ResultadoCobro;

    /** Devuelve dinero de un cobro ya capturado. */
    public function devolver(
        string $pagoId,
        Money $monto,
        string $referenciaOriginal,
        string $idempotencyKey,
    ): ResultadoDevolucion;

    /**
     * Reporte de movimientos de la fecha de corte, para CU-PAG-11.
     *
     * @return list<MovimientoPasarela>
     */
    public function reporteDelDia(DateTimeImmutable $fechaCorte): array;

    /** Nombre del proveedor, que se guarda en `pago.pasarela`. */
    public function nombre(): string;
}

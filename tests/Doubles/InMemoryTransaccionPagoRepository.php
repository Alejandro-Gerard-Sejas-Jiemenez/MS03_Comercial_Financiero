<?php

declare(strict_types=1);

namespace Tests\Doubles;

use DateTimeImmutable;
use Src\Pagos\Domain\EstadoTransaccion;
use Src\Pagos\Domain\TransaccionPago;
use Src\Pagos\Domain\TransaccionPagoRepository;

final class InMemoryTransaccionPagoRepository implements TransaccionPagoRepository
{
    /** @var list<TransaccionPago> */
    private array $transacciones = [];

    public function agregar(TransaccionPago $transaccion): void
    {
        $this->transacciones[] = $transaccion;
    }

    public function buscarPorReferencia(string $referenciaPasarela): ?TransaccionPago
    {
        foreach ($this->transacciones as $transaccion) {
            if ($transaccion->referenciaPasarela === $referenciaPasarela) {
                return $transaccion;
            }
        }

        return null;
    }

    public function delPago(string $pagoId): array
    {
        return array_values(array_filter(
            $this->transacciones,
            static fn (TransaccionPago $t): bool => $t->pagoId === $pagoId,
        ));
    }

    public function exitosasDelDia(string $pasarela, DateTimeImmutable $fechaCorte): array
    {
        return array_values(array_filter(
            $this->transacciones,
            static fn (TransaccionPago $t): bool => $t->estado === EstadoTransaccion::EXITOSA
                && $t->referenciaPasarela !== null,
        ));
    }

    /** @return list<TransaccionPago> */
    public function todas(): array
    {
        return $this->transacciones;
    }

    public function cantidad(): int
    {
        return count($this->transacciones);
    }
}

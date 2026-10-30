<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\TransaccionPago;

interface TransaccionPagoRepository
{
    /** Append-only: solo se agrega, nunca se actualiza. */
    public function agregar(TransaccionPago $transaccion): void;

    public function buscarPorReferencia(string $referenciaPasarela): ?TransaccionPago;

    /** @return list<TransaccionPago> */
    public function delPago(string $pagoId): array;

    /**
     * Transacciones EXITOSAS del dia de corte, para comparar con el reporte de
     * la pasarela.
     *
     * @return list<TransaccionPago>
     */
    public function exitosasDelDia(string $pasarela, DateTimeImmutable $fechaCorte): array;
}

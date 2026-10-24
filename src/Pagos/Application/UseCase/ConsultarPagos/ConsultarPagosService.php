<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\ConsultarPagos;

use DomainException;
use Src\Pagos\Domain\Comprobante;
use Src\Pagos\Domain\ComprobanteRepository;
use Src\Pagos\Domain\CriterioDePagos;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\Reembolso;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Pagos\Domain\TransaccionPago;
use Src\Pagos\Domain\TransaccionPagoRepository;

/**
 * CU-PAG-06 ConsultarPagos (QRY, REST).
 *
 * Solo lectura: detalle de un pago, listados filtrados y descarga del
 * comprobante. Los filtros por rol (el cliente ve los suyos, el anfitrion los
 * de sus alojamientos, el Administrador todos) los resuelve la capa de
 * Presentacion con el JWT de Keycloak y los pasa ya armados en el criterio.
 */
final class ConsultarPagosService
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly TransaccionPagoRepository $transacciones,
        private readonly ReembolsoRepository $reembolsos,
        private readonly ComprobanteRepository $comprobantes,
    ) {
    }

    public function detalle(string $pagoId): DetalleDePago
    {
        $pago = $this->pagos->buscar($pagoId)
            ?? throw new DomainException("El pago {$pagoId} no existe");

        return new DetalleDePago(
            pago: $pago,
            transacciones: $this->transacciones->delPago($pagoId),
            reembolsos: $this->reembolsos->delPago($pagoId),
            comprobante: $this->comprobantes->delPago($pagoId),
            totalReembolsado: $this->pagos->totalReembolsado($pagoId),
        );
    }

    /** @return list<Pago> */
    public function listar(CriterioDePagos $criterio): array
    {
        return $this->pagos->listar($criterio);
    }

    public function comprobanteDe(string $pagoId): Comprobante
    {
        return $this->comprobantes->delPago($pagoId)
            ?? throw new DomainException("El pago {$pagoId} no tiene comprobante emitido");
    }

    /** @return list<TransaccionPago> */
    public function transaccionesDe(string $pagoId): array
    {
        return $this->transacciones->delPago($pagoId);
    }

    /** @return list<Reembolso> */
    public function reembolsosDe(string $pagoId): array
    {
        return $this->reembolsos->delPago($pagoId);
    }
}

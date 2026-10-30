<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConsultarPagos;

use Src\Financiero\Application\Port\ComprobanteRepository;
use Src\Financiero\Application\Port\PagoRepository;
use Src\Financiero\Application\Port\ReembolsoRepository;
use Src\Financiero\Application\Port\TransaccionPagoRepository;
use Src\Financiero\Application\Response\DetalleDePagoResponse;
use Src\Financiero\Application\Response\PagoResponse;
use Src\Financiero\Domain\ValueObject\CriterioDePagos;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;

/** Caso de uso ConsultarPagos: implementa la interfaz de entrada ConsultarPagosUseCase. */
final class ConsultarPagosService implements ConsultarPagosUseCase
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly TransaccionPagoRepository $transacciones,
        private readonly ReembolsoRepository $reembolsos,
        private readonly ComprobanteRepository $comprobantes,
    ) {}

    public function detalle(string $pagoId): DetalleDePagoResponse
    {
        $pago = $this->pagos->buscar($pagoId)
            ?? throw new RecursoNoEncontrado("El pago {$pagoId} no existe");

        return DetalleDePagoResponse::desde(new DetalleDePago(
            pago: $pago,
            transacciones: $this->transacciones->delPago($pagoId),
            reembolsos: $this->reembolsos->delPago($pagoId),
            comprobante: $this->comprobantes->delPago($pagoId),
            totalReembolsado: $this->pagos->totalReembolsado($pagoId),
        ));
    }

    /** @return list<PagoResponse> */
    public function listar(ListarPagosRequest $comando): array
    {
        // Quien no es ADMIN solo ve lo suyo, segun el papel con que consulta.
        $comoAnfitrion = $comando->como === ListarPagosRequest::COMO_ANFITRION;
        $clienteId = $comando->esAdmin ? $comando->clienteId : ($comoAnfitrion ? null : $comando->usuarioId);
        $anfitrionId = $comando->esAdmin ? $comando->anfitrionId : ($comoAnfitrion ? $comando->usuarioId : null);

        return array_map(PagoResponse::desde(...), $this->pagos->listar(new CriterioDePagos(
            clienteId: $clienteId,
            anfitrionId: $anfitrionId,
            reservaId: $comando->reservaId,
            estado: $comando->estado,
            desde: $comando->desde,
            hasta: $comando->hasta,
            limite: $comando->limite,
        )));
    }
}

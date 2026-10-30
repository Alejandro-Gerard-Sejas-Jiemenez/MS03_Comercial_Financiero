<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Application\UseCase\ConsultarPagos\DetalleDePago;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de DetalleDePago: solo datos primitivos, sin reglas. */
final class DetalleDePagoResponse
{
    public function __construct(
        public readonly PagoResponse $pago,
        /** @var list<TransaccionPagoResponse> */
        public readonly array $transacciones,
        /** @var list<ReembolsoResponse> */
        public readonly array $reembolsos,
        public readonly ?ComprobanteResponse $comprobante,
        public readonly MontoResponse $totalReembolsado,
    ) {}

    public static function desde(DetalleDePago $e): self
    {
        return new self(
            pago: PagoResponse::desde($e->pago),
            transacciones: array_map(TransaccionPagoResponse::desde(...), $e->transacciones),
            reembolsos: array_map(ReembolsoResponse::desde(...), $e->reembolsos),
            comprobante: ComprobanteResponse::desdeOpcional($e->comprobante),
            totalReembolsado: MontoResponse::desde($e->totalReembolsado),
        );
    }

    public static function desdeOpcional(?DetalleDePago $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

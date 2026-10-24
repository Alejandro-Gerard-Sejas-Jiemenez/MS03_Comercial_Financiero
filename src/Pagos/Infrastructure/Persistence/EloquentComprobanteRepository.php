<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Persistence;

use Src\Pagos\Domain\Comprobante;
use Src\Pagos\Domain\ComprobanteRepository;
use Src\Pagos\Domain\TipoComprobante;
use Src\Shared\Domain\Money;

final class EloquentComprobanteRepository implements ComprobanteRepository
{
    public function buscar(string $comprobanteId): ?Comprobante
    {
        $fila = ComprobanteModel::query()->find($comprobanteId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function delPago(string $pagoId): ?Comprobante
    {
        $fila = ComprobanteModel::query()->where('pago_id', $pagoId)->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(Comprobante $comprobante): void
    {
        ComprobanteModel::query()->updateOrCreate(
            ['comprobante_id' => $comprobante->comprobanteId()],
            [
                'pago_id' => $comprobante->pagoId(),
                'tipo' => $comprobante->tipo()->value,
                'numero' => $comprobante->numero(),
                'nit_cliente' => $comprobante->nitCliente(),
                'razon_social' => $comprobante->razonSocial(),
                'monto' => $comprobante->monto()->aDecimal(),
                'archivo_url' => $comprobante->archivoUrl(),
                'hash_documento' => $comprobante->hashDocumento(),
            ],
        );
    }

    private function aEntidad(ComprobanteModel $fila): Comprobante
    {
        return Comprobante::reconstituir(
            comprobanteId: $fila->comprobante_id,
            pagoId: $fila->pago_id,
            tipo: TipoComprobante::from($fila->tipo),
            numero: $fila->numero,
            // `comprobante` no tiene columna de moneda: hereda la del pago.
            monto: Money::deDecimal(
                $fila->monto,
                PagoModel::query()->whereKey($fila->pago_id)->value('moneda') ?? 'BOB',
            ),
            nitCliente: $fila->nit_cliente,
            razonSocial: $fila->razon_social,
            archivoUrl: $fila->archivo_url,
            hashDocumento: $fila->hash_documento,
        );
    }
}

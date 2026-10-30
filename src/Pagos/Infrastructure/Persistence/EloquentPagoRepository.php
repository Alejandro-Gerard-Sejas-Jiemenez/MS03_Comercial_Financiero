<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\Pagos\Domain\CriterioDePagos;
use Src\Pagos\Domain\EstadoPago;
use Src\Pagos\Domain\EstadoReembolso;
use Src\Pagos\Domain\MetodoPago;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\PagoRepository;
use Src\Shared\Domain\Money;

/**
 * Traduce entre la fila de `pago` y la entidad `Pago`.
 *
 * `monto_total` no se mapea de vuelta a la entidad: la entidad lo calcula con
 * `total()`, y el CHECK de la base garantiza que la columna diga lo mismo.
 */
final class EloquentPagoRepository implements PagoRepository
{
    /** Estados que ocupan la reserva (`uq_pago_vivo_por_reserva`). */
    private const ESTADOS_VIVOS = ['PENDIENTE', 'AUTORIZADO', 'CAPTURADO'];

    public function buscar(string $pagoId): ?Pago
    {
        $fila = PagoModel::query()->find($pagoId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function buscarPorIdempotencyKey(string $idempotencyKey): ?Pago
    {
        $fila = PagoModel::query()->where('idempotency_key', $idempotencyKey)->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    private function seCobro(Pago $pago): bool
    {
        return in_array($pago->estado(), [EstadoPago::CAPTURADO, EstadoPago::REEMBOLSADO_PARCIAL, EstadoPago::REEMBOLSADO], true);
    }

    public function buscarVivoPorReserva(string $reservaId): ?Pago
    {
        $fila = PagoModel::query()
            ->where('reserva_id', $reservaId)
            ->whereIn('estado', self::ESTADOS_VIVOS)
            ->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(Pago $pago): void
    {
        // La fecha de cobro se estampa una vez, al capturar, y no se pisa en guardados posteriores.
        $pagadoEn = PagoModel::query()->whereKey($pago->pagoId())->value('pagado_en')
            ?? ($this->seCobro($pago) ? now() : null);

        PagoModel::query()->updateOrCreate(
            ['pago_id' => $pago->pagoId()],
            [
                'reserva_id' => $pago->reservaId(),
                'cliente_id' => $pago->clienteId(),
                'anfitrion_id' => $pago->anfitrionId(),
                'promocion_id' => $pago->promocionId(),
                'subtotal_alojamiento' => $pago->subtotal()->aDecimal(),
                'descuento' => $pago->descuento()->aDecimal(),
                'cargo_servicio' => $pago->cargoServicio()->aDecimal(),
                'monto_total' => $pago->total()->aDecimal(),
                'moneda' => $pago->moneda(),
                'metodo' => $pago->metodo()->value,
                'pasarela' => $pago->pasarela(),
                'estado' => $pago->estado()->value,
                'idempotency_key' => $pago->idempotencyKey(),
                'saga_id' => $pago->sagaId(),
                // `pagado_en` se sella cuando el dinero entro de verdad.
                'pagado_en' => $pagadoEn,
            ],
        );
    }

    public function totalReembolsado(string $pagoId): Money
    {
        $fila = PagoModel::query()->find($pagoId);
        $moneda = $fila === null ? 'BOB' : $fila->moneda;

        $suma = ReembolsoModel::query()
            ->where('pago_id', $pagoId)
            ->where('estado', EstadoReembolso::PROCESADO->value)
            ->sum('monto');

        return Money::deDecimal(number_format((float) $suma, 2, '.', ''), $moneda);
    }

    public function listar(CriterioDePagos $criterio): array
    {
        return PagoModel::query()
            ->when(
                $criterio->clienteId !== null,
                static fn (Builder $q): Builder => $q->where('cliente_id', $criterio->clienteId),
            )
            ->when(
                $criterio->anfitrionId !== null,
                static fn (Builder $q): Builder => $q->where('anfitrion_id', $criterio->anfitrionId),
            )
            ->when(
                $criterio->reservaId !== null,
                static fn (Builder $q): Builder => $q->where('reserva_id', $criterio->reservaId),
            )
            ->when(
                $criterio->estado !== null,
                static fn (Builder $q): Builder => $q->where('estado', $criterio->estado?->value),
            )
            ->when(
                $criterio->desde !== null,
                static fn (Builder $q): Builder => $q->where('created_at', '>=', $criterio->desde),
            )
            ->when(
                $criterio->hasta !== null,
                static fn (Builder $q): Builder => $q->where('created_at', '<=', $criterio->hasta),
            )
            ->orderByDesc('created_at')
            ->limit($criterio->limite)
            ->get()
            ->map(fn (PagoModel $fila): Pago => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function pendientesAnterioresA(DateTimeImmutable $limite, int $lote = 100): array
    {
        return PagoModel::query()
            ->where('estado', EstadoPago::PENDIENTE->value)
            ->where('created_at', '<', $limite)
            ->orderBy('created_at')
            ->limit($lote)
            ->get()
            ->map(fn (PagoModel $fila): Pago => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(PagoModel $fila): Pago
    {
        $moneda = $fila->moneda;

        return Pago::reconstituir(
            pagoId: $fila->pago_id,
            reservaId: $fila->reserva_id,
            clienteId: $fila->cliente_id,
            anfitrionId: $fila->anfitrion_id,
            promocionId: $fila->promocion_id,
            subtotal: Money::deDecimal($fila->subtotal_alojamiento, $moneda),
            descuento: Money::deDecimal($fila->descuento, $moneda),
            cargoServicio: Money::deDecimal($fila->cargo_servicio, $moneda),
            metodo: MetodoPago::from($fila->metodo),
            estado: EstadoPago::from($fila->estado),
            idempotencyKey: $fila->idempotency_key,
            sagaId: $fila->saga_id,
            pasarela: $fila->pasarela,
            // La ultima referencia EXITOSA con la pasarela, que es la que sirve
            // para pedir una devolucion sobre ese cobro.
            referenciaPasarela: TransaccionPagoModel::query()
                ->where('pago_id', $fila->pago_id)
                ->where('estado', 'EXITOSA')
                ->whereNotNull('referencia_pasarela')
                ->orderByDesc('created_at')
                ->value('referencia_pasarela'),
        );
    }
}

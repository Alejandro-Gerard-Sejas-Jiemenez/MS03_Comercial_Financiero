<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

use Src\Pagos\Domain\Event\PagoCancelado;
use Src\Pagos\Domain\Event\PagoCapturado;
use Src\Pagos\Domain\Event\PagoFallido;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;
use Src\Shared\Domain\RegistraEventos;

/**
 * Raiz de agregado del cobro de una reserva.
 *
 * Aqui estan las reglas que el documento de arquitectura manda proteger en PHP
 * puro, sin base de datos ni Laravel:
 *  - el descuento no puede superar el subtotal;
 *  - `total = subtotal - descuento + cargo_servicio`;
 *  - no se captura dos veces el mismo pago;
 *  - no se reembolsa mas de lo cobrado.
 *
 * La entidad acumula sus eventos y el caso de uso los publica dentro de la
 * misma transaccion en que la guarda (patron Outbox).
 */
final class Pago
{
    use RegistraEventos;

    private function __construct(
        private readonly string $pagoId,
        private readonly string $reservaId,
        private readonly string $clienteId,
        private readonly string $anfitrionId,
        private readonly ?string $promocionId,
        private readonly Money $subtotal,
        private readonly Money $descuento,
        private readonly Money $cargoServicio,
        private readonly MetodoPago $metodo,
        private EstadoPago $estado,
        private readonly string $idempotencyKey,
        private readonly ?string $sagaId,
        private ?string $pasarela,
        private ?string $referenciaPasarela,
    ) {}

    public static function crear(
        string $pagoId,
        string $reservaId,
        string $clienteId,
        string $anfitrionId,
        Money $subtotal,
        Money $descuento,
        Money $cargoServicio,
        MetodoPago $metodo,
        string $idempotencyKey,
        ?string $sagaId = null,
        ?string $promocionId = null,
        ?string $pasarela = null,
    ): self {
        if ($subtotal->esNegativo()) {
            throw new ReglaDeNegocioViolada('El subtotal no puede ser negativo');
        }

        if ($cargoServicio->esNegativo()) {
            throw new ReglaDeNegocioViolada('El cargo de servicio no puede ser negativo');
        }

        if ($descuento->esNegativo() || $descuento->esMayorQue($subtotal)) {
            throw new ReglaDeNegocioViolada('El descuento no puede superar el subtotal');
        }

        if (trim($idempotencyKey) === '') {
            throw new ReglaDeNegocioViolada('El pago necesita una Idempotency-Key');
        }

        return new self(
            $pagoId,
            $reservaId,
            $clienteId,
            $anfitrionId,
            $promocionId,
            $subtotal,
            $descuento,
            $cargoServicio,
            $metodo,
            EstadoPago::PENDIENTE,
            trim($idempotencyKey),
            $sagaId,
            $pasarela,
            null,
        );
    }

    /** Reconstruye desde la base de datos, sin revalidar reglas de creacion. */
    public static function reconstituir(
        string $pagoId,
        string $reservaId,
        string $clienteId,
        string $anfitrionId,
        ?string $promocionId,
        Money $subtotal,
        Money $descuento,
        Money $cargoServicio,
        MetodoPago $metodo,
        EstadoPago $estado,
        string $idempotencyKey,
        ?string $sagaId,
        ?string $pasarela,
        ?string $referenciaPasarela = null,
    ): self {
        return new self(
            $pagoId,
            $reservaId,
            $clienteId,
            $anfitrionId,
            $promocionId,
            $subtotal,
            $descuento,
            $cargoServicio,
            $metodo,
            $estado,
            $idempotencyKey,
            $sagaId,
            $pasarela,
            $referenciaPasarela,
        );
    }

    /** `monto_total = subtotal_alojamiento - descuento + cargo_servicio`. */
    public function total(): Money
    {
        return $this->subtotal->menos($this->descuento)->mas($this->cargoServicio);
    }

    /**
     * Lo que el anfitrion vendio: el alojamiento menos el descuento. El cargo de
     * servicio no entra, porque es ingreso de la plataforma.
     */
    public function montoDelAnfitrion(): Money
    {
        return $this->subtotal->menos($this->descuento);
    }

    public function autorizar(string $referenciaPasarela, ?string $pasarela = null): void
    {
        if ($this->estado !== EstadoPago::PENDIENTE) {
            throw new ConflictoDeEstado(
                "No se puede autorizar un pago en estado {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::AUTORIZADO;
        $this->referenciaPasarela = $referenciaPasarela;
        $this->pasarela ??= $pasarela;
    }

    /**
     * Cobro confirmado por la pasarela. Solo se puede capturar una vez: un
     * reintento de la saga sobre un pago ya capturado es un error de negocio,
     * no una captura nueva.
     */
    public function capturar(string $referenciaPasarela, ?string $pasarela = null): void
    {
        if (! in_array($this->estado, [EstadoPago::PENDIENTE, EstadoPago::AUTORIZADO], true)) {
            throw new ConflictoDeEstado(
                "No se puede capturar un pago en estado {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::CAPTURADO;
        $this->referenciaPasarela = $referenciaPasarela;
        $this->pasarela ??= $pasarela;

        $this->registrar(new PagoCapturado(
            pagoId: $this->pagoId,
            reservaId: $this->reservaId,
            clienteId: $this->clienteId,
            anfitrionId: $this->anfitrionId,
            subtotal: $this->subtotal,
            descuento: $this->descuento,
            cargoServicio: $this->cargoServicio,
            total: $this->total(),
            promocionId: $this->promocionId,
            sagaId: $this->sagaId,
            referenciaPasarela: $referenciaPasarela,
        ));
    }

    public function marcarFallido(?string $motivo = null): void
    {
        if (! in_array($this->estado, [EstadoPago::PENDIENTE, EstadoPago::AUTORIZADO], true)) {
            throw new ConflictoDeEstado(
                "No se puede marcar fallido un pago en estado {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::FALLIDO;

        $this->registrar(new PagoFallido($this->pagoId, $this->reservaId, $motivo, $this->sagaId));
    }

    /** Compensacion de la saga, o expiracion del pago pendiente (CU-PAG-04). */
    public function cancelar(string $motivo): void
    {
        if (! in_array($this->estado, [EstadoPago::PENDIENTE, EstadoPago::AUTORIZADO], true)) {
            throw new ConflictoDeEstado(
                "Solo se cancela un pago pendiente o autorizado, no uno {$this->estado->value}",
            );
        }

        $this->estado = EstadoPago::CANCELADO;

        $this->registrar(new PagoCancelado($this->pagoId, $this->reservaId, $motivo, $this->sagaId));
    }

    /**
     * Mueve el estado segun el total acumulado de reembolsos PROCESADOS,
     * incluido el que se acaba de procesar.
     *
     * El acumulado lo calcula el caso de uso: el pago no guarda el historial de
     * reembolsos, lo guardan las filas de `reembolso`.
     */
    public function aplicarReembolso(Money $reembolsadoAcumulado): void
    {
        if (! in_array($this->estado, [EstadoPago::CAPTURADO, EstadoPago::REEMBOLSADO_PARCIAL], true)) {
            throw new ConflictoDeEstado(
                "No se puede reembolsar un pago en estado {$this->estado->value}",
            );
        }

        if ($reembolsadoAcumulado->esNegativo()) {
            throw new ReglaDeNegocioViolada('El monto reembolsado no puede ser negativo');
        }

        if ($reembolsadoAcumulado->esMayorQue($this->total())) {
            throw new ConflictoDeEstado(
                'No se puede reembolsar mas que el total del pago '
                ."({$reembolsadoAcumulado->aDecimal()} > {$this->total()->aDecimal()})",
            );
        }

        $this->estado = $reembolsadoAcumulado->igualA($this->total())
            ? EstadoPago::REEMBOLSADO
            : EstadoPago::REEMBOLSADO_PARCIAL;
    }

    public function estaCapturado(): bool
    {
        return $this->estado === EstadoPago::CAPTURADO;
    }

    /** Un pago capturado o parcialmente reembolsado todavia tiene dinero que devolver. */
    public function admiteReembolso(): bool
    {
        return in_array($this->estado, [EstadoPago::CAPTURADO, EstadoPago::REEMBOLSADO_PARCIAL], true);
    }

    public function pagoId(): string
    {
        return $this->pagoId;
    }

    public function reservaId(): string
    {
        return $this->reservaId;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function promocionId(): ?string
    {
        return $this->promocionId;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function descuento(): Money
    {
        return $this->descuento;
    }

    public function cargoServicio(): Money
    {
        return $this->cargoServicio;
    }

    public function moneda(): string
    {
        return $this->subtotal->moneda();
    }

    public function metodo(): MetodoPago
    {
        return $this->metodo;
    }

    public function estado(): EstadoPago
    {
        return $this->estado;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }

    public function pasarela(): ?string
    {
        return $this->pasarela;
    }

    public function referenciaPasarela(): ?string
    {
        return $this->referenciaPasarela;
    }
}

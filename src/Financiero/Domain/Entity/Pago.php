<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity;

use Src\Financiero\Domain\Entity\Pago\CicloDeVida;
use Src\Financiero\Domain\Entity\Pago\Lectura;
use Src\Financiero\Domain\Entity\Pago\Reembolsos;
use Src\Financiero\Domain\Enum\EstadoPago;
use Src\Financiero\Domain\Enum\MetodoPago;
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
    use CicloDeVida;
    use Lectura;
    use Reembolsos;
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
}

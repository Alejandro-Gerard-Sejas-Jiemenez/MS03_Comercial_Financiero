<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

use Src\Shared\Domain\Money;

/**
 * Una linea del cuadre.
 *
 * Dentro de una conciliacion la identidad es la `referencia_pasarela`: es
 * unica, porque la base de datos no admite dos transacciones con la misma
 * referencia. El UUID de la fila lo pone el repositorio al insertar y vuelve
 * en `itemId` cuando se lee de la base.
 *
 * `observacion` es la explicacion que escribe una persona en CU-PAG-12; por eso
 * un item con observacion es un item revisado, y los items recien calculados
 * nacen sin ella.
 */
final class ConciliacionItem
{
    private function __construct(
        private readonly string $referenciaPasarela,
        private readonly Money $montoPasarela,
        private readonly ResultadoConciliacionItem $resultado,
        private ?string $transaccionId,
        private ?string $observacion,
        private readonly ?string $itemId,
    ) {}

    public static function coincide(MovimientoPasarela $movimiento, string $transaccionId): self
    {
        return new self(
            $movimiento->referenciaPasarela,
            $movimiento->monto,
            ResultadoConciliacionItem::COINCIDE,
            $transaccionId,
            null,
            null,
        );
    }

    public static function diferenciaDeMonto(MovimientoPasarela $movimiento, string $transaccionId): self
    {
        return new self(
            $movimiento->referenciaPasarela,
            $movimiento->monto,
            ResultadoConciliacionItem::DIFERENCIA_MONTO,
            $transaccionId,
            null,
            null,
        );
    }

    /** La pasarela reporta un cobro que MS-03 no tiene registrado. */
    public static function noRegistrada(MovimientoPasarela $movimiento): self
    {
        return new self(
            $movimiento->referenciaPasarela,
            $movimiento->monto,
            ResultadoConciliacionItem::NO_REGISTRADA,
            null,
            null,
            null,
        );
    }

    /**
     * Al reves: MS-03 tiene la transaccion y la pasarela no la reporto. Se
     * guarda como DIFERENCIA_MONTO con `monto_pasarela = 0.00`, porque el
     * esquema solo admite tres resultados.
     */
    public static function sinReportar(TransaccionPago $transaccion, string $moneda = 'BOB'): self
    {
        return new self(
            (string) $transaccion->referenciaPasarela,
            Money::cero($moneda),
            ResultadoConciliacionItem::DIFERENCIA_MONTO,
            $transaccion->transaccionId,
            null,
            null,
        );
    }

    public static function reconstituir(
        string $itemId,
        string $referenciaPasarela,
        Money $montoPasarela,
        ResultadoConciliacionItem $resultado,
        ?string $transaccionId,
        ?string $observacion,
    ): self {
        return new self(
            $referenciaPasarela,
            $montoPasarela,
            $resultado,
            $transaccionId,
            $observacion,
            $itemId,
        );
    }

    public function revisar(string $observacion, ?string $transaccionId = null): void
    {
        $this->observacion = $observacion;

        if ($transaccionId !== null) {
            $this->transaccionId = $transaccionId;
        }
    }

    public function estaRevisado(): bool
    {
        return $this->observacion !== null && trim($this->observacion) !== '';
    }

    public function referenciaPasarela(): string
    {
        return $this->referenciaPasarela;
    }

    public function montoPasarela(): Money
    {
        return $this->montoPasarela;
    }

    public function resultado(): ResultadoConciliacionItem
    {
        return $this->resultado;
    }

    public function transaccionId(): ?string
    {
        return $this->transaccionId;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function itemId(): ?string
    {
        return $this->itemId;
    }
}

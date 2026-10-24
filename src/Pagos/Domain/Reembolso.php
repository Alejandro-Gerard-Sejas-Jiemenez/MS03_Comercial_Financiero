<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

use DomainException;
use Src\Pagos\Domain\Event\ReembolsoProcesado;
use Src\Shared\Domain\Money;
use Src\Shared\Domain\RegistraEventos;

/**
 * Devolucion de dinero sobre un pago capturado.
 *
 * El ciclo es SOLICITADO -> APROBADO -> PROCESADO, y cada paso es un caso de
 * uso distinto (CU-PAG-07, CU-PAG-08, CU-PAG-09) porque cada uno lo dispara
 * alguien distinto: un evento de MS-02, el Administrador y la pasarela.
 */
final class Reembolso
{
    use RegistraEventos;

    private function __construct(
        private readonly string $reembolsoId,
        private readonly string $pagoId,
        private readonly Money $monto,
        private readonly MotivoReembolso $motivo,
        private EstadoReembolso $estado,
        private ?string $detalle,
        private readonly ?string $solicitadoPor,
        private ?string $transaccionId,
    ) {
    }

    public static function solicitar(
        string $reembolsoId,
        string $pagoId,
        Money $monto,
        MotivoReembolso $motivo,
        ?string $detalle = null,
        ?string $solicitadoPor = null,
    ): self {
        if (! $monto->esPositivo()) {
            throw new DomainException('Un reembolso debe tener monto positivo');
        }

        return new self(
            $reembolsoId,
            $pagoId,
            $monto,
            $motivo,
            EstadoReembolso::SOLICITADO,
            $detalle,
            $solicitadoPor,
            null,
        );
    }

    public static function reconstituir(
        string $reembolsoId,
        string $pagoId,
        Money $monto,
        MotivoReembolso $motivo,
        EstadoReembolso $estado,
        ?string $detalle,
        ?string $solicitadoPor,
        ?string $transaccionId,
    ): self {
        return new self(
            $reembolsoId,
            $pagoId,
            $monto,
            $motivo,
            $estado,
            $detalle,
            $solicitadoPor,
            $transaccionId,
        );
    }

    public function aprobar(?string $detalle = null): void
    {
        if ($this->estado !== EstadoReembolso::SOLICITADO) {
            throw new DomainException(
                "Solo se aprueba un reembolso SOLICITADO, no uno {$this->estado->value}",
            );
        }

        $this->estado = EstadoReembolso::APROBADO;

        if ($detalle !== null) {
            $this->detalle = $detalle;
        }
    }

    public function rechazar(string $detalle): void
    {
        if ($this->estado !== EstadoReembolso::SOLICITADO) {
            throw new DomainException(
                "Solo se rechaza un reembolso SOLICITADO, no uno {$this->estado->value}",
            );
        }

        if (trim($detalle) === '') {
            throw new DomainException('Un rechazo necesita explicacion');
        }

        $this->estado = EstadoReembolso::RECHAZADO;
        $this->detalle = $detalle;
    }

    /**
     * Devolucion confirmada por la pasarela. `reservaId` y `anfitrionId` llegan
     * del pago: el reembolso no los guarda, pero el evento los necesita para
     * que MS-02 y Liquidaciones sepan a quien corresponde.
     */
    public function procesar(
        string $referenciaPasarela,
        ?string $transaccionId,
        string $reservaId,
        string $anfitrionId,
        bool $esTotal,
        ?string $sagaId = null,
    ): void {
        if ($this->estado !== EstadoReembolso::APROBADO) {
            throw new DomainException(
                "Solo se procesa un reembolso APROBADO, no uno {$this->estado->value}",
            );
        }

        $this->estado = EstadoReembolso::PROCESADO;
        $this->transaccionId = $transaccionId;

        $this->registrar(new ReembolsoProcesado(
            reembolsoId: $this->reembolsoId,
            pagoId: $this->pagoId,
            reservaId: $reservaId,
            anfitrionId: $anfitrionId,
            monto: $this->monto,
            esTotal: $esTotal,
            referenciaPasarela: $referenciaPasarela,
            sagaId: $sagaId,
        ));
    }

    public function estaProcesado(): bool
    {
        return $this->estado === EstadoReembolso::PROCESADO;
    }

    public function reembolsoId(): string
    {
        return $this->reembolsoId;
    }

    public function pagoId(): string
    {
        return $this->pagoId;
    }

    public function monto(): Money
    {
        return $this->monto;
    }

    public function motivo(): MotivoReembolso
    {
        return $this->motivo;
    }

    public function estado(): EstadoReembolso
    {
        return $this->estado;
    }

    public function detalle(): ?string
    {
        return $this->detalle;
    }

    public function solicitadoPor(): ?string
    {
        return $this->solicitadoPor;
    }

    public function transaccionId(): ?string
    {
        return $this->transaccionId;
    }
}

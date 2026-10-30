<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity\AcuerdoComercial;

use DateTimeImmutable;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;
use Src\Relaciones\Domain\Event\AcuerdoActivado;
use Src\Relaciones\Domain\Event\AcuerdoFinalizado;
use Src\Relaciones\Domain\Event\AcuerdoSuspendido;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Cambios de estado del acuerdo: activar, suspender, finalizar y rescindir.
 *
 * Parte de `AcuerdoComercial`: usa sus campos privados.
 */
trait CicloDeVida
{
    /**
     * Activa el acuerdo (o lo reactiva desde SUSPENDIDO) y emite
     * `acuerdo_comercial.activado`.
     */
    public function activar(string $aprobadoPor, DateTimeImmutable $aprobadoEn, ?string $sagaId = null): void
    {
        $this->asegurarTransicion(EstadoAcuerdo::ACTIVO);

        $this->estado = EstadoAcuerdo::ACTIVO;
        $this->aprobadoPor = $aprobadoPor;
        $this->aprobadoEn = $aprobadoEn;
        $this->motivoEstado = null;

        $this->registrar(new AcuerdoActivado(
            $this->acuerdoId,
            $this->anfitrionId,
            $this->reglaComisionId,
            $this->diasLiquidacion,
            $this->fechaInicio,
            $this->fechaFin,
            $aprobadoPor,
            $sagaId,
        ));
    }

    /** Suspende el acuerdo y emite `acuerdo_comercial.suspendido`. */
    public function suspender(string $motivo, ?string $sagaId = null): void
    {
        $this->asegurarTransicion(EstadoAcuerdo::SUSPENDIDO);

        $limpio = self::validarMotivo($motivo);
        $this->estado = EstadoAcuerdo::SUSPENDIDO;
        $this->motivoEstado = $limpio;

        $this->registrar(new AcuerdoSuspendido($this->acuerdoId, $this->anfitrionId, $limpio, $sagaId));
    }

    /** Cierre normal del acuerdo: emite `acuerdo_comercial.finalizado`. */
    public function finalizar(?string $motivo = null, ?string $sagaId = null): void
    {
        $this->cerrar(EstadoAcuerdo::FINALIZADO, $motivo, $sagaId);
    }

    /**
     * Rescision: el acuerdo se corta antes de tiempo. Emite el mismo
     * `acuerdo_comercial.finalizado` con `estado_final = RESCINDIDO`, porque
     * para MS-01 el efecto es identico: el acuerdo dejo de regir.
     */
    public function rescindir(string $motivo, ?string $sagaId = null): void
    {
        $this->cerrar(EstadoAcuerdo::RESCINDIDO, self::validarMotivo($motivo), $sagaId);
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoAcuerdo::ACTIVO;
    }

    private function cerrar(EstadoAcuerdo $destino, ?string $motivo, ?string $sagaId): void
    {
        $this->asegurarTransicion($destino);

        $this->estado = $destino;
        $this->motivoEstado = $motivo;

        $this->registrar(new AcuerdoFinalizado(
            $this->acuerdoId,
            $this->anfitrionId,
            $destino,
            $motivo,
            $this->fechaFin,
            $sagaId,
        ));
    }

    private function asegurarTransicion(EstadoAcuerdo $destino): void
    {
        if (! $this->estado->puedeTransicionarA($destino)) {
            throw new ConflictoDeEstado(
                "El acuerdo {$this->acuerdoId} esta {$this->estado->value} y no puede pasar a {$destino->value}",
            );
        }
    }

    private static function validarMotivo(string $motivo): string
    {
        $limpio = trim($motivo);

        if ($limpio === '') {
            throw new ReglaDeNegocioViolada('El cambio de estado necesita un motivo');
        }

        return $limpio;
    }
}

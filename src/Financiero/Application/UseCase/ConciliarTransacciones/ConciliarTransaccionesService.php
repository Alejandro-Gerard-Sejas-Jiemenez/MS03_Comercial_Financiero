<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConciliarTransacciones;

use Src\Financiero\Application\Port\ConciliacionRepository;
use Src\Financiero\Application\Port\PasarelaPago;
use Src\Financiero\Application\Port\TransaccionPagoRepository;
use Src\Financiero\Application\Response\ConciliacionResponse;
use Src\Financiero\Domain\Entity\Conciliacion;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;

/** Caso de uso ConciliarTransacciones: implementa la interfaz de entrada ConciliarTransaccionesUseCase. */
final class ConciliarTransaccionesService implements ConciliarTransaccionesUseCase
{
    public function __construct(
        private readonly TransaccionPagoRepository $transacciones,
        private readonly ConciliacionRepository $conciliaciones,
        private readonly PasarelaPago $pasarela,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly TransactionManager $tx,
        private readonly AuditLogger $auditoria,
    ) {}

    /**
     * Disparador: diario, tambien disparable por REST.
     *
     * Compara las transacciones EXITOSAS del corte con el reporte de la pasarela.
     * La comparacion la hace la entidad `Conciliacion`: aqui solo se juntan las dos
     * listas y se guarda el resultado.
     *
     * La base no admite dos conciliaciones del mismo corte (`UNIQUE (pasarela,
     * fecha_corte)`), asi que repetir el job devuelve la que ya existe.
     */
    public function ejecutar(EjecutarConciliacionRequest $comando): ConciliacionResponse
    {
        $fechaCorte = $comando->fechaCorte ?? $this->clock->hoy()->modify('-1 day');
        $pasarela = $this->pasarela->nombre();

        $existente = $this->conciliaciones->buscarPorCorte($pasarela, $fechaCorte);

        if ($existente !== null) {
            return ConciliacionResponse::desde($existente);
        }

        $conciliacion = Conciliacion::ejecutar(
            conciliacionId: $this->uuid->generar(),
            pasarela: $pasarela,
            fechaCorte: $fechaCorte,
            transaccionesDelSistema: $this->transacciones->exitosasDelDia($pasarela, $fechaCorte),
            reporteDeLaPasarela: $this->pasarela->reporteDelDia($fechaCorte),
            ejecutadaPor: $comando->ejecutadaPor,
            moneda: $comando->moneda,
        );

        return ConciliacionResponse::desde($this->tx->ejecutar(function () use ($conciliacion): Conciliacion {
            $this->conciliaciones->guardar($conciliacion);

            return $conciliacion;
        }));
    }

    /**
     * Disparador: REST.
     *
     * El Administrador explica un item que no cuadro y, si ya no queda nada
     * pendiente, cierra la conciliacion. Queda auditado con su nombre: una
     * diferencia de dinero explicada por nadie no sirve de nada.
     */
    public function revisarDiferencia(RevisarDiferenciaConciliacionRequest $comando): ConciliacionResponse
    {
        $conciliacion = $this->conciliaciones->buscar($comando->conciliacionId)
            ?? throw new RecursoNoEncontrado("La conciliacion {$comando->conciliacionId} no existe");

        $estadoAnterior = $conciliacion->estado()->value;

        return ConciliacionResponse::desde($this->tx->ejecutar(function () use ($conciliacion, $comando, $estadoAnterior): Conciliacion {
            $conciliacion->revisarItem(
                $comando->referenciaPasarela,
                $comando->observacion,
                $comando->transaccionId,
            );

            if ($comando->cerrar) {
                $conciliacion->cerrar();
            }

            $this->conciliaciones->guardar($conciliacion);

            $this->auditoria->registrar(
                'REVISAR_DIFERENCIA_CONCILIACION',
                'conciliacion',
                $conciliacion->conciliacionId(),
                ['estado' => $estadoAnterior],
                [
                    'estado' => $conciliacion->estado()->value,
                    'referencia_pasarela' => $comando->referenciaPasarela,
                    'observacion' => $comando->observacion,
                ],
                $comando->revisadoPor,
            );

            return $conciliacion;
        }));
    }
}

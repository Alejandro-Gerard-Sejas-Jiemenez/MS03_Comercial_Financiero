<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\EjecutarConciliacion;

use DomainException;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Domain\Conciliacion;
use Src\Pagos\Domain\ConciliacionRepository;
use Src\Pagos\Domain\TransaccionPagoRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;

/**
 * CU-PAG-11 EjecutarConciliacion (JOB diario, tambien disparable por REST).
 *
 * Compara las transacciones EXITOSAS del corte con el reporte de la pasarela.
 * La comparacion la hace la entidad `Conciliacion`: aqui solo se juntan las dos
 * listas y se guarda el resultado.
 *
 * La base no admite dos conciliaciones del mismo corte (`UNIQUE (pasarela,
 * fecha_corte)`), asi que repetir el job devuelve la que ya existe.
 */
final class EjecutarConciliacionHandler
{
    public function __construct(
        private readonly TransaccionPagoRepository $transacciones,
        private readonly ConciliacionRepository $conciliaciones,
        private readonly PasarelaPago $pasarela,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(EjecutarConciliacionCommand $comando): Conciliacion
    {
        $fechaCorte = $comando->fechaCorte ?? $this->clock->hoy()->modify('-1 day');
        $pasarela = $this->pasarela->nombre();

        $existente = $this->conciliaciones->buscarPorCorte($pasarela, $fechaCorte);

        if ($existente !== null) {
            return $existente;
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

        return $this->tx->ejecutar(function () use ($conciliacion): Conciliacion {
            $this->conciliaciones->guardar($conciliacion);

            return $conciliacion;
        });
    }
}

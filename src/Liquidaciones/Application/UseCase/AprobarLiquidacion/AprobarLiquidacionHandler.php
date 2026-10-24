<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\AprobarLiquidacion;

use DomainException;
use Src\Acuerdos\Application\Port\MetodoCobroQuery;
use Src\Liquidaciones\Domain\Liquidacion;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Referencias\Domain\AnfitrionReferenceRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\TransactionManager;

/**
 * CU-LIQ-05 AprobarLiquidacion (NEG, REST).
 *
 * Decision del Administrador antes de que salga el dinero. Comprueba dos cosas
 * que pueden haber cambiado desde que el JOB genero la liquidacion: que el
 * anfitrion siga habilitado y que su metodo de cobro siga verificado.
 */
final class AprobarLiquidacionHandler
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly AnfitrionReferenceRepository $anfitriones,
        private readonly MetodoCobroQuery $metodosDeCobro,
        private readonly AuditLogger $auditoria,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(AprobarLiquidacionCommand $comando): Liquidacion
    {
        $liquidacion = $this->liquidaciones->buscar($comando->liquidacionId)
            ?? throw new DomainException("La liquidacion {$comando->liquidacionId} no existe");

        $anfitrion = $this->anfitriones->buscar($liquidacion->anfitrionId());

        if ($anfitrion === null || ! $anfitrion->puedeRecibirLiquidaciones()) {
            throw new DomainException(
                "El anfitrion {$liquidacion->anfitrionId()} no esta habilitado para recibir pagos",
            );
        }

        $metodo = $this->metodosDeCobro->metodoPrincipalVerificado($liquidacion->anfitrionId());

        if ($metodo === null) {
            throw new DomainException(
                "El anfitrion {$liquidacion->anfitrionId()} no tiene metodo de cobro verificado",
            );
        }

        return $this->tx->ejecutar(function () use ($liquidacion, $comando, $metodo): Liquidacion {
            if ($liquidacion->metodoId() !== $metodo) {
                $liquidacion->asignarMetodoDeCobro($metodo);
            }

            $liquidacion->aprobar($comando->aprobadoPor);
            $this->liquidaciones->guardar($liquidacion);

            $this->auditoria->registrar(
                'APROBAR_LIQUIDACION',
                'liquidacion',
                $liquidacion->liquidacionId(),
                ['estado' => 'GENERADA'],
                ['estado' => $liquidacion->estado()->value, 'metodo_id' => $metodo],
                $comando->aprobadoPor,
            );

            return $liquidacion;
        });
    }
}

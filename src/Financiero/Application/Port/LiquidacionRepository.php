<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

use Src\Financiero\Domain\Entity\Liquidacion;
use Src\Financiero\Domain\Enum\EstadoLiquidacion;

interface LiquidacionRepository
{
    public function buscar(string $liquidacionId): ?Liquidacion;

    public function guardar(Liquidacion $liquidacion): void;

    /**
     * Liquidaciones de un anfitrion, de la mas nueva a la mas vieja.
     *
     * @return list<Liquidacion>
     */
    public function deAnfitrion(string $anfitrionId, ?EstadoLiquidacion $estado = null, int $limite = 50): array;

    /**
     * Todas las liquidaciones (vista del ADMIN), de la mas nueva a la mas vieja.
     *
     * @return list<Liquidacion>
     */
    public function todas(?EstadoLiquidacion $estado = null, ?string $anfitrionId = null, int $limite = 100): array;

    /**
     * Liquidaciones que todavia no se pagaron (GENERADA o APROBADA), para
     * retenerlas cuando se suspende al anfitrion.
     *
     * @return list<Liquidacion>
     */
    public function pendientesDePago(string $anfitrionId): array;
}

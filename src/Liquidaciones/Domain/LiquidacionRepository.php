<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

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
     * Liquidaciones que todavia no se pagaron (GENERADA o APROBADA), para
     * retenerlas cuando se suspende al anfitrion (CU-LIQ-08).
     *
     * @return list<Liquidacion>
     */
    public function pendientesDePago(string $anfitrionId): array;
}

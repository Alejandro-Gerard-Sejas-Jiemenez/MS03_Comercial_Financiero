<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

interface ReembolsoRepository
{
    public function buscar(string $reembolsoId): ?Reembolso;

    public function guardar(Reembolso $reembolso): void;

    /** @return list<Reembolso> */
    public function delPago(string $pagoId): array;

    /**
     * Reembolsos APROBADOS esperando que la pasarela los devuelva
     * (CU-PAG-09 disparado por JOB).
     *
     * @return list<Reembolso>
     */
    public function aprobadosPendientesDeProcesar(int $lote = 50): array;
}

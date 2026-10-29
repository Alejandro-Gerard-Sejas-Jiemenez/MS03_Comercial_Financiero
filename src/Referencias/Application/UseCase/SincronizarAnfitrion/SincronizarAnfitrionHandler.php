<?php

declare(strict_types=1);

namespace Src\Referencias\Application\UseCase\SincronizarAnfitrion;

use Src\Referencias\Domain\AnfitrionReference;
use Src\Referencias\Domain\AnfitrionReferenceRepository;
use Src\Referencias\Domain\EstadoVerificacionAnfitrion;

/**
 * CU-REF-01 SincronizarAnfitrion (EVT: `anfitrion.verificado` / `anfitrion.suspendido`).
 *
 * Upsert en `ref_anfitrion`. El consumidor de RabbitMQ que lo invoque debe
 * filtrar antes los mensajes repetidos con el inbox (`evento_procesado`), que
 * vive en `Shared`; ese consumidor es capa de Presentacion y todavia no existe.
 */
final class SincronizarAnfitrionHandler
{
    public function __construct(private readonly AnfitrionReferenceRepository $anfitriones) {}

    public function __invoke(SincronizarAnfitrionCommand $comando): AnfitrionReference
    {
        $anfitrion = new AnfitrionReference(
            anfitrionId: $comando->anfitrionId,
            nombreCompleto: $comando->nombreCompleto,
            estadoVerificacion: EstadoVerificacionAnfitrion::from($comando->estado),
            nit: $comando->nit,
            razonSocial: $comando->razonSocial,
        );

        $this->anfitriones->guardar($anfitrion);

        return $anfitrion;
    }
}

<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Response;

use Src\Financiero\Domain\Entity\Comprobante;
use Src\Shared\Application\Response\MontoResponse;

/** Modelo de respuesta (Clean Architecture: Response Model) de Comprobante: solo datos primitivos, sin reglas. */
final class ComprobanteResponse
{
    public function __construct(
        public readonly string $comprobanteId,
        public readonly string $pagoId,
        public readonly string $tipo,
        public readonly string $numero,
        public readonly MontoResponse $monto,
        public readonly ?string $nitCliente,
        public readonly ?string $razonSocial,
        public readonly ?string $archivoUrl,
        public readonly ?string $hashDocumento,
    ) {}

    public static function desde(Comprobante $e): self
    {
        return new self(
            comprobanteId: $e->comprobanteId(),
            pagoId: $e->pagoId(),
            tipo: $e->tipo()->value,
            numero: $e->numero(),
            monto: MontoResponse::desde($e->monto()),
            nitCliente: $e->nitCliente(),
            razonSocial: $e->razonSocial(),
            archivoUrl: $e->archivoUrl(),
            hashDocumento: $e->hashDocumento(),
        );
    }

    public static function desdeOpcional(?Comprobante $e): ?self
    {
        return $e === null ? null : self::desde($e);
    }
}

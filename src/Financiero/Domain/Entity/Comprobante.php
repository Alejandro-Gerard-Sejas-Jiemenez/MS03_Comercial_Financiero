<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity;

use Src\Financiero\Domain\Enum\TipoComprobante;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Recibo o factura de un pago capturado. El numero es unico en la base de
 * datos: un comprobante no se emite dos veces para el mismo pago.
 */
final class Comprobante
{
    private function __construct(
        private readonly string $comprobanteId,
        private readonly string $pagoId,
        private readonly TipoComprobante $tipo,
        private readonly string $numero,
        private readonly Money $monto,
        private readonly ?string $nitCliente,
        private readonly ?string $razonSocial,
        private ?string $archivoUrl,
        private ?string $hashDocumento,
    ) {}

    public static function emitir(
        string $comprobanteId,
        string $pagoId,
        TipoComprobante $tipo,
        string $numero,
        Money $monto,
        ?string $nitCliente = null,
        ?string $razonSocial = null,
    ): self {
        if (trim($numero) === '') {
            throw new ReglaDeNegocioViolada('El comprobante necesita un numero');
        }

        if ($monto->esNegativo()) {
            throw new ReglaDeNegocioViolada('El comprobante no puede tener monto negativo');
        }

        // Una factura en Bolivia necesita a quien se le factura.
        if ($tipo === TipoComprobante::FACTURA && ($nitCliente === null || trim($nitCliente) === '')) {
            throw new ReglaDeNegocioViolada('Una FACTURA necesita el NIT del cliente');
        }

        return new self(
            $comprobanteId,
            $pagoId,
            $tipo,
            trim($numero),
            $monto,
            $nitCliente,
            $razonSocial,
            null,
            null,
        );
    }

    public static function reconstituir(
        string $comprobanteId,
        string $pagoId,
        TipoComprobante $tipo,
        string $numero,
        Money $monto,
        ?string $nitCliente,
        ?string $razonSocial,
        ?string $archivoUrl,
        ?string $hashDocumento,
    ): self {
        return new self(
            $comprobanteId,
            $pagoId,
            $tipo,
            $numero,
            $monto,
            $nitCliente,
            $razonSocial,
            $archivoUrl,
            $hashDocumento,
        );
    }

    /**
     * El PDF lo genera la infraestructura; el dominio solo guarda donde quedo y
     * su hash, para poder probar despues que el archivo no cambio.
     */
    public function adjuntarArchivo(string $archivoUrl, string $hashDocumento): void
    {
        if (strlen($hashDocumento) !== 64) {
            throw new ReglaDeNegocioViolada('El hash del documento debe ser un SHA-256 de 64 caracteres');
        }

        $this->archivoUrl = $archivoUrl;
        $this->hashDocumento = $hashDocumento;
    }

    public function comprobanteId(): string
    {
        return $this->comprobanteId;
    }

    public function pagoId(): string
    {
        return $this->pagoId;
    }

    public function tipo(): TipoComprobante
    {
        return $this->tipo;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function monto(): Money
    {
        return $this->monto;
    }

    public function nitCliente(): ?string
    {
        return $this->nitCliente;
    }

    public function razonSocial(): ?string
    {
        return $this->razonSocial;
    }

    public function archivoUrl(): ?string
    {
        return $this->archivoUrl;
    }

    public function hashDocumento(): ?string
    {
        return $this->hashDocumento;
    }
}

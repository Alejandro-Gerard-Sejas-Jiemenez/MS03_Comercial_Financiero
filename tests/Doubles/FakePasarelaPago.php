<?php

declare(strict_types=1);

namespace Tests\Doubles;

use DateTimeImmutable;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Application\Port\ResultadoCobro;
use Src\Pagos\Application\Port\ResultadoDevolucion;
use Src\Pagos\Domain\MetodoPago;
use Src\Pagos\Domain\MovimientoPasarela;
use Src\Shared\Domain\Money;

final class FakePasarelaPago implements PasarelaPago
{
    private bool $apruebaCobros = true;

    private bool $apruebaDevoluciones = true;

    private string $motivoDeRechazo = 'fondos insuficientes';

    /** @var list<array{operacion: string, idempotencyKey: string, monto: string}> */
    private array $llamadas = [];

    /** @var list<MovimientoPasarela> */
    private array $reporte = [];

    public function __construct(private readonly string $nombre = 'FAKE') {}

    public function cobrar(
        string $pagoId,
        Money $monto,
        string $idempotencyKey,
        MetodoPago $metodo,
    ): ResultadoCobro {
        $this->llamadas[] = [
            'operacion' => 'cobrar',
            'idempotencyKey' => $idempotencyKey,
            'monto' => $monto->aDecimal(),
        ];

        return $this->apruebaCobros
            ? ResultadoCobro::exitoso('ref-cobro-'.count($this->llamadas), ['fake' => true])
            : ResultadoCobro::fallido($this->motivoDeRechazo, ['fake' => true]);
    }

    public function devolver(
        string $pagoId,
        Money $monto,
        string $referenciaOriginal,
        string $idempotencyKey,
    ): ResultadoDevolucion {
        $this->llamadas[] = [
            'operacion' => 'devolver',
            'idempotencyKey' => $idempotencyKey,
            'monto' => $monto->aDecimal(),
        ];

        return $this->apruebaDevoluciones
            ? ResultadoDevolucion::exitoso('ref-dev-'.count($this->llamadas), ['fake' => true])
            : ResultadoDevolucion::fallido($this->motivoDeRechazo, ['fake' => true]);
    }

    public function reporteDelDia(DateTimeImmutable $fechaCorte): array
    {
        return $this->reporte;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function rechazandoCobros(string $motivo = 'fondos insuficientes'): self
    {
        $this->apruebaCobros = false;
        $this->motivoDeRechazo = $motivo;

        return $this;
    }

    public function rechazandoDevoluciones(string $motivo = 'no se puede devolver'): self
    {
        $this->apruebaDevoluciones = false;
        $this->motivoDeRechazo = $motivo;

        return $this;
    }

    /** @param list<MovimientoPasarela> $movimientos */
    public function conReporte(array $movimientos): self
    {
        $this->reporte = $movimientos;

        return $this;
    }

    /** @return list<array{operacion: string, idempotencyKey: string, monto: string}> */
    public function llamadas(): array
    {
        return $this->llamadas;
    }
}

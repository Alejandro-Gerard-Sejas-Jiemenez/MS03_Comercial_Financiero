<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Console;

use DomainException;
use Illuminate\Console\Command;
use Src\Financiero\Application\Port\PagoRepository;
use Src\Financiero\Application\UseCase\ProcesarPago\CancelarPagoPendienteRequest;
use Src\Financiero\Application\UseCase\ProcesarPago\ProcesarPagoUseCase;
use Src\Shared\Application\Port\Clock;

/** JOB: cancela los pagos que llevan demasiado tiempo PENDIENTES y libera la reserva. */
final class ExpirarPagosPendientesCommand extends Command
{
    protected $signature = 'pagos:expirar-pendientes';

    protected $description = 'Cancela los pagos pendientes vencidos';

    public function handle(PagoRepository $pagos, ProcesarPagoUseCase $cancelar, Clock $reloj): int
    {
        $horas = (int) config('comercial.pagos.horas_para_expirar_pendiente');
        $limite = $reloj->ahora()->modify("-{$horas} hours");

        $cancelados = 0;
        foreach ($pagos->pendientesAnterioresA($limite) as $pago) {
            try {
                $cancelar->cancelarPendiente(new CancelarPagoPendienteRequest($pago->pagoId(), 'expirado por falta de pago'));
                $cancelados++;
            } catch (DomainException $e) {
                // Uno que ya cambio de estado no debe frenar a los demas.
                $this->warn("{$pago->pagoId()}: {$e->getMessage()}");
            }
        }
        $this->info("{$cancelados} pagos cancelados");

        return self::SUCCESS;
    }
}

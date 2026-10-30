<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Console;

use Illuminate\Console\Command;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\GenerarLiquidacionesRequest as Comando;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\GestionarLiquidacionesUseCase;

/** JOB: una liquidacion por anfitrion con sus comisiones liquidables. */
final class GenerarLiquidacionesCommand extends Command
{
    protected $signature = 'liquidaciones:generar {--hasta= : fecha limite (Y-m-d); por omision, hoy} {--moneda=BOB}';

    protected $description = 'Genera las liquidaciones pendientes de los anfitriones';

    public function handle(GestionarLiquidacionesUseCase $handler): int
    {
        $hasta = $this->option('hasta');
        $generadas = $handler->generar(new Comando(
            hasta: $hasta ? new \DateTimeImmutable((string) $hasta) : null,
            moneda: (string) $this->option('moneda'),
        ));
        $this->info(count($generadas).' liquidaciones generadas');

        return self::SUCCESS;
    }
}

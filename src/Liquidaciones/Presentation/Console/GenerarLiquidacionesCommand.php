<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Presentation\Console;

use Illuminate\Console\Command;
use Src\Liquidaciones\Application\UseCase\GenerarLiquidaciones\GenerarLiquidacionesCommand as Comando;
use Src\Liquidaciones\Application\UseCase\GenerarLiquidaciones\GenerarLiquidacionesHandler;

/** CU-LIQ-04 (JOB): una liquidacion por anfitrion con sus comisiones liquidables. */
final class GenerarLiquidacionesCommand extends Command
{
    protected $signature = 'liquidaciones:generar {--hasta= : fecha limite (Y-m-d); por omision, hoy} {--moneda=BOB}';

    protected $description = 'Genera las liquidaciones pendientes de los anfitriones (CU-LIQ-04)';

    public function handle(GenerarLiquidacionesHandler $handler): int
    {
        $hasta = $this->option('hasta');
        $generadas = $handler(new Comando(
            hasta: $hasta ? new \DateTimeImmutable((string) $hasta) : null,
            moneda: (string) $this->option('moneda'),
        ));
        $this->info(count($generadas).' liquidaciones generadas');

        return self::SUCCESS;
    }
}

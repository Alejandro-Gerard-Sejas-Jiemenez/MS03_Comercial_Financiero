<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Console;

use Illuminate\Console\Command;
use Src\Financiero\Application\UseCase\ConciliarTransacciones\ConciliarTransaccionesUseCase;
use Src\Financiero\Application\UseCase\ConciliarTransacciones\EjecutarConciliacionRequest as Comando;

/** JOB: compara el reporte del dia de la pasarela con nuestras transacciones. */
final class EjecutarConciliacionCommand extends Command
{
    protected $signature = 'conciliacion:ejecutar {--fecha= : fecha de corte (Y-m-d); por omision, hoy} {--moneda=BOB}';

    protected $description = 'Ejecuta la conciliacion diaria con la pasarela';

    public function handle(ConciliarTransaccionesUseCase $handler): int
    {
        $fecha = $this->option('fecha');
        $conciliacion = $handler->ejecutar(new Comando(
            fechaCorte: $fecha ? new \DateTimeImmutable((string) $fecha) : null,
            ejecutadaPor: null,
            moneda: (string) $this->option('moneda'),
        ));
        $this->info('Conciliacion '.$conciliacion->estado);

        return self::SUCCESS;
    }
}

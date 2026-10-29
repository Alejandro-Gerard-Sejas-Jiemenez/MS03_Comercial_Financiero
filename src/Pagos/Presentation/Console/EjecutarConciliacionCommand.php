<?php

declare(strict_types=1);

namespace Src\Pagos\Presentation\Console;

use Illuminate\Console\Command;
use Src\Pagos\Application\UseCase\EjecutarConciliacion\EjecutarConciliacionCommand as Comando;
use Src\Pagos\Application\UseCase\EjecutarConciliacion\EjecutarConciliacionHandler;

/** CU-PAG-11 (JOB): compara el reporte del dia de la pasarela con nuestras transacciones. */
final class EjecutarConciliacionCommand extends Command
{
    protected $signature = 'conciliacion:ejecutar {--fecha= : fecha de corte (Y-m-d); por omision, hoy} {--moneda=BOB}';

    protected $description = 'Ejecuta la conciliacion diaria con la pasarela (CU-PAG-11)';

    public function handle(EjecutarConciliacionHandler $handler): int
    {
        $fecha = $this->option('fecha');
        $conciliacion = $handler(new Comando(
            fechaCorte: $fecha ? new \DateTimeImmutable((string) $fecha) : null,
            ejecutadaPor: null,
            moneda: (string) $this->option('moneda'),
        ));
        $this->info('Conciliacion '.$conciliacion->estado()->value);

        return self::SUCCESS;
    }
}

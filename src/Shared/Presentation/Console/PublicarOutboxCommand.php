<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Console;

use Illuminate\Console\Command;
use Src\Shared\Application\UseCase\PublicarEventosOutbox\PublicarEventosOutboxCommand as Comando;
use Src\Shared\Application\UseCase\PublicarEventosOutbox\PublicarEventosOutboxHandler;

/** CU-SHA-01 (JOB): publica en el broker los eventos pendientes del Outbox. */
final class PublicarOutboxCommand extends Command
{
    protected $signature = 'outbox:publicar {--lote= : cuantos eventos por corrida}';

    protected $description = 'Publica los eventos pendientes del Outbox (CU-SHA-01)';

    public function handle(PublicarEventosOutboxHandler $handler): int
    {
        $lote = (int) ($this->option('lote') ?: config('comercial.outbox.lote'));
        $resultado = $handler(new Comando($lote));
        $this->info("{$resultado->publicados} publicados, {$resultado->fallidos} con error");

        return $resultado->fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }
}

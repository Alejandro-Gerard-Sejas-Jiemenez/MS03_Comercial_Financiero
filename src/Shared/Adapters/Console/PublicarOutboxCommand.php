<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Console;

use Illuminate\Console\Command;
use Src\Shared\Application\UseCase\PublicarEventos\PublicarEventosOutboxRequest as Comando;
use Src\Shared\Application\UseCase\PublicarEventos\PublicarEventosUseCase;

/** JOB: publica en el broker los eventos pendientes del Outbox. */
final class PublicarOutboxCommand extends Command
{
    protected $signature = 'outbox:publicar {--lote= : cuantos eventos por corrida}';

    protected $description = 'Publica los eventos pendientes del Outbox';

    public function handle(PublicarEventosUseCase $handler): int
    {
        $lote = (int) ($this->option('lote') ?: config('comercial.outbox.lote'));
        $resultado = $handler->publicarPendientes(new Comando($lote));
        $this->info("{$resultado->publicados} publicados, {$resultado->fallidos} con error");

        return $resultado->fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }
}

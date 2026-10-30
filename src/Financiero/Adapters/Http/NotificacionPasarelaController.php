<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Financiero\Adapters\Presenter\FinancieroPresentador;
use Src\Financiero\Application\UseCase\ProcesarPago\ProcesarPagoUseCase;
use Src\Financiero\Application\UseCase\ProcesarPago\RecibirNotificacionPasarelaRequest;
use Src\Financiero\Domain\Enum\TipoTransaccion;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Money;

/**
 * webhook de la pasarela. No lleva JWT (la pasarela no es un
 * usuario): se autentica con un secreto compartido en `X-Webhook-Secret`.
 * Sin secreto configurado se rechaza todo.
 */
final class NotificacionPasarelaController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new FinancieroPresentador;
    }

    public function __construct(private readonly ProcesarPagoUseCase $recibir) {}

    public function __invoke(Request $request): JsonResponse
    {
        $esperado = (string) config('comercial.pasarela.webhook_secreto');
        $recibido = (string) $request->header('X-Webhook-Secret');
        if ($esperado === '' || ! hash_equals($esperado, $recibido)) {
            throw new OperacionNoPermitida('Notificacion no autenticada');
        }

        $d = $request->validate([
            'pago_id' => 'required|uuid',
            'referencia_pasarela' => 'required|string',
            'exitosa' => 'required|boolean',
            'pasarela' => 'nullable|string',
            'motivo' => 'nullable|string',
            'monto_centavos' => 'nullable|integer|min:0',
            'moneda' => 'nullable|string|size:3',
            'tipo' => 'nullable|string',
        ]);

        $resultado = $this->recibir->recibirNotificacionPasarela(new RecibirNotificacionPasarelaRequest(
            pagoId: $d['pago_id'],
            referenciaPasarela: $d['referencia_pasarela'],
            exitosa: (bool) $d['exitosa'],
            pasarela: $d['pasarela'] ?? null,
            motivo: $d['motivo'] ?? null,
            montoNotificado: isset($d['monto_centavos'])
                ? Money::deCentavos($d['monto_centavos'], $d['moneda'] ?? 'BOB')
                : null,
            tipo: $this->enum(TipoTransaccion::class, $d['tipo'] ?? null),
            payload: $request->all(),
        ));

        return $this->responder($resultado);
    }
}

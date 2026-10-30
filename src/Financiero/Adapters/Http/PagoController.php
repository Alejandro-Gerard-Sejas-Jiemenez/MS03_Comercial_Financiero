<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Financiero\Adapters\Presenter\FinancieroPresentador;
use Src\Financiero\Application\Response\DetalleDePagoResponse;
use Src\Financiero\Application\UseCase\ConsultarPagos\ConsultarPagosUseCase;
use Src\Financiero\Application\UseCase\ConsultarPagos\ListarPagosRequest;
use Src\Financiero\Application\UseCase\ProcesarPago\CancelarPagoPendienteRequest;
use Src\Financiero\Application\UseCase\ProcesarPago\CobrarPagoRequest;
use Src\Financiero\Application\UseCase\ProcesarPago\CotizarReservaRequest;
use Src\Financiero\Application\UseCase\ProcesarPago\CrearPagoRequest;
use Src\Financiero\Application\UseCase\ProcesarPago\EmitirComprobanteRequest;
use Src\Financiero\Application\UseCase\ProcesarPago\ProcesarPagoUseCase;
use Src\Financiero\Domain\Enum\EstadoPago;
use Src\Financiero\Domain\Enum\MetodoPago;
use Src\Financiero\Domain\Enum\TipoComprobante;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Domain\Exception\OperacionNoPermitida;

/** Pagos: cotizar, crear, cobrar, cancelar, consultar y emitir comprobante. Controlador delgado: request -> caso de uso -> presentador. */
final class PagoController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new FinancieroPresentador;
    }

    public function __construct(
        private readonly ProcesarPagoUseCase $procesarPago,
        private readonly ConsultarPagosUseCase $consultas,
    ) {}

    public function cotizar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'anfitrion_id' => 'required|uuid',
            'subtotal_centavos' => 'required|integer|min:0',
            'noches' => 'required|integer|min:1',
            'alojamiento_id' => 'nullable|string',
            'categoria_alojamiento' => 'nullable|string',
            'nivel_anfitrion' => 'nullable|string',
            'codigo_cupon' => 'nullable|string',
            'promocion_id' => 'nullable|uuid',
            'moneda' => 'nullable|string|size:3',
            'fecha' => 'nullable|date',
        ]);

        return $this->responder($this->procesarPago->cotizar(new CotizarReservaRequest(
            anfitrionId: $d['anfitrion_id'],
            subtotalCentavos: $d['subtotal_centavos'],
            noches: $d['noches'],
            alojamientoId: $d['alojamiento_id'] ?? null,
            categoriaAlojamiento: $d['categoria_alojamiento'] ?? null,
            nivelAnfitrion: $d['nivel_anfitrion'] ?? null,
            codigoCupon: $d['codigo_cupon'] ?? null,
            promocionId: $d['promocion_id'] ?? null,
            moneda: $d['moneda'] ?? 'BOB',
            fecha: $this->fecha($d['fecha'] ?? null),
        )));
    }

    public function crear(Request $request): JsonResponse
    {
        $d = $request->validate([
            'reserva_id' => 'required|uuid',
            'cliente_id' => 'nullable|uuid',
            'anfitrion_id' => 'required|uuid',
            'subtotal_centavos' => 'required|integer|min:0',
            'cargo_servicio_centavos' => 'required|integer|min:0',
            'descuento_centavos' => 'nullable|integer|min:0',
            'metodo' => 'required|string',
            'moneda' => 'nullable|string|size:3',
            'saga_id' => 'nullable|uuid',
            'promocion_id' => 'nullable|uuid',
            'pasarela' => 'nullable|string',
        ]);

        // Un cliente solo paga a su nombre; el ADMIN (o la saga) puede indicar otro.
        $clienteId = $this->esAdmin($request)
            ? ($d['cliente_id'] ?? $this->sesion($request)->usuarioId)
            : $this->sesion($request)->usuarioId;

        $pago = $this->procesarPago->crear(new CrearPagoRequest(
            reservaId: $d['reserva_id'],
            clienteId: $clienteId,
            anfitrionId: $d['anfitrion_id'],
            subtotalCentavos: $d['subtotal_centavos'],
            cargoServicioCentavos: $d['cargo_servicio_centavos'],
            metodo: $this->enumObligatorio(MetodoPago::class, $d['metodo']),
            idempotencyKey: (string) $request->header('Idempotency-Key'),
            descuentoCentavos: $d['descuento_centavos'] ?? 0,
            moneda: $d['moneda'] ?? 'BOB',
            sagaId: $d['saga_id'] ?? null,
            promocionId: $d['promocion_id'] ?? null,
            pasarela: $d['pasarela'] ?? null,
        ));

        return $this->responder($pago, 201);
    }

    public function cobrar(Request $request, string $pagoId): JsonResponse
    {
        $this->exigirDuenoDelPago($request, $pagoId, soloCliente: true);

        return $this->responder($this->procesarPago->cobrar(new CobrarPagoRequest(
            pagoId: $pagoId,
            idempotencyKey: $request->header('Idempotency-Key'),
        )));
    }

    public function cancelar(Request $request, string $pagoId): JsonResponse
    {
        $d = $request->validate(['motivo' => 'required|string|max:200']);
        $this->exigirDuenoDelPago($request, $pagoId, soloCliente: true);

        return $this->responder($this->procesarPago->cancelarPendiente(new CancelarPagoPendienteRequest($pagoId, $d['motivo'])));
    }

    public function listar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cliente_id' => 'nullable|uuid',
            'anfitrion_id' => 'nullable|uuid',
            'reserva_id' => 'nullable|uuid',
            'estado' => 'nullable|string',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'limite' => 'nullable|integer|min:1|max:200',
            'como' => 'nullable|in:cliente,anfitrion',
        ]);

        return $this->responder($this->consultas->listar(new ListarPagosRequest(
            usuarioId: $this->sesion($request)->usuarioId,
            esAdmin: $this->esAdmin($request),
            como: $d['como'] ?? ListarPagosRequest::COMO_CLIENTE,
            clienteId: $d['cliente_id'] ?? null,
            anfitrionId: $d['anfitrion_id'] ?? null,
            reservaId: $d['reserva_id'] ?? null,
            estado: $this->enum(EstadoPago::class, $d['estado'] ?? null),
            desde: $this->fecha($d['desde'] ?? null),
            hasta: $this->fecha($d['hasta'] ?? null),
            limite: $d['limite'] ?? 50,
        )));
    }

    public function mostrar(Request $request, string $pagoId): JsonResponse
    {
        return $this->responder($this->exigirDuenoDelPago($request, $pagoId));
    }

    public function emitirComprobante(Request $request, string $pagoId): JsonResponse
    {
        $d = $request->validate([
            'tipo' => 'nullable|string',
            'nit_cliente' => 'nullable|string|max:20',
            'razon_social' => 'nullable|string|max:150',
        ]);
        $this->exigirDuenoDelPago($request, $pagoId, soloCliente: true);

        return $this->responder($this->procesarPago->emitirComprobante(new EmitirComprobanteRequest(
            pagoId: $pagoId,
            tipo: $this->enum(TipoComprobante::class, $d['tipo'] ?? null) ?? TipoComprobante::RECIBO,
            nitCliente: $d['nit_cliente'] ?? null,
            razonSocial: $d['razon_social'] ?? null,
        )), 201);
    }

    /**
     * El ADMIN ve todo; el cliente y el anfitrion del pago ven el suyo. Las
     * operaciones que mueven dinero (`soloCliente`) son del cliente o del ADMIN.
     */
    private function exigirDuenoDelPago(Request $request, string $pagoId, bool $soloCliente = false): DetalleDePagoResponse
    {
        $detalle = $this->consultas->detalle($pagoId);
        if ($this->esAdmin($request)) {
            return $detalle;
        }

        $yo = $this->sesion($request)->usuarioId;
        $esCliente = $detalle->pago->clienteId === $yo;
        $esAnfitrion = ! $soloCliente && $detalle->pago->anfitrionId === $yo;
        if (! $esCliente && ! $esAnfitrion) {
            throw new OperacionNoPermitida('Este pago no es tuyo');
        }

        return $detalle;
    }
}

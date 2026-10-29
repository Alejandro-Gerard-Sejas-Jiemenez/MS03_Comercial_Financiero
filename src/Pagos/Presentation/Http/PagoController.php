<?php

declare(strict_types=1);

namespace Src\Pagos\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Pagos\Application\UseCase\CancelarPagoPendiente\CancelarPagoPendienteCommand;
use Src\Pagos\Application\UseCase\CancelarPagoPendiente\CancelarPagoPendienteHandler;
use Src\Pagos\Application\UseCase\CobrarPago\CobrarPagoCommand;
use Src\Pagos\Application\UseCase\CobrarPago\CobrarPagoHandler;
use Src\Pagos\Application\UseCase\ConsultarPagos\ConsultarPagosService;
use Src\Pagos\Application\UseCase\ConsultarPagos\DetalleDePago;
use Src\Pagos\Application\UseCase\CotizarReserva\CotizarReservaCommand;
use Src\Pagos\Application\UseCase\CotizarReserva\CotizarReservaHandler;
use Src\Pagos\Application\UseCase\CrearPago\CrearPagoCommand;
use Src\Pagos\Application\UseCase\CrearPago\CrearPagoHandler;
use Src\Pagos\Application\UseCase\EmitirComprobante\EmitirComprobanteCommand;
use Src\Pagos\Application\UseCase\EmitirComprobante\EmitirComprobanteHandler;
use Src\Pagos\Domain\CriterioDePagos;
use Src\Pagos\Domain\EstadoPago;
use Src\Pagos\Domain\MetodoPago;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\TipoComprobante;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Presentation\Http\ControladorBase;
use Src\Shared\Presentation\Http\Presentador;

/** CU-PAG-01, 02, 03, 04, 06 y 10. Controlador delgado: request -> Command -> Handler -> JSON. */
final class PagoController extends ControladorBase
{
    public function __construct(
        private readonly CotizarReservaHandler $cotizar,
        private readonly CrearPagoHandler $crear,
        private readonly CobrarPagoHandler $cobrar,
        private readonly CancelarPagoPendienteHandler $cancelar,
        private readonly EmitirComprobanteHandler $emitirComprobante,
        private readonly ConsultarPagosService $consultas,
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

        return $this->responder(($this->cotizar)(new CotizarReservaCommand(
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

        $pago = ($this->crear)(new CrearPagoCommand(
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

        return $this->responder($this->conTotal($pago), 201);
    }

    public function cobrar(Request $request, string $pagoId): JsonResponse
    {
        $this->exigirDuenoDelPago($request, $pagoId, soloCliente: true);

        return $this->responder($this->conTotal(($this->cobrar)(new CobrarPagoCommand(
            pagoId: $pagoId,
            idempotencyKey: $request->header('Idempotency-Key'),
        ))));
    }

    public function cancelar(Request $request, string $pagoId): JsonResponse
    {
        $d = $request->validate(['motivo' => 'required|string|max:200']);
        $this->exigirDuenoDelPago($request, $pagoId, soloCliente: true);

        return $this->responder($this->conTotal(($this->cancelar)(new CancelarPagoPendienteCommand($pagoId, $d['motivo']))));
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

        $clienteId = $d['cliente_id'] ?? null;
        $anfitrionId = $d['anfitrion_id'] ?? null;
        if (! $this->esAdmin($request)) {
            // Quien no es ADMIN solo ve lo suyo, segun el papel con que consulta.
            $yo = $this->sesion($request)->usuarioId;
            $comoAnfitrion = ($d['como'] ?? 'cliente') === 'anfitrion';
            $clienteId = $comoAnfitrion ? null : $yo;
            $anfitrionId = $comoAnfitrion ? $yo : null;
        }

        return $this->responder($this->consultas->listar(new CriterioDePagos(
            clienteId: $clienteId,
            anfitrionId: $anfitrionId,
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

    public function transacciones(Request $request, string $pagoId): JsonResponse
    {
        $this->exigirDuenoDelPago($request, $pagoId);

        return $this->responder($this->consultas->transaccionesDe($pagoId));
    }

    public function reembolsos(Request $request, string $pagoId): JsonResponse
    {
        $this->exigirDuenoDelPago($request, $pagoId);

        return $this->responder($this->consultas->reembolsosDe($pagoId));
    }

    public function comprobante(Request $request, string $pagoId): JsonResponse
    {
        $this->exigirDuenoDelPago($request, $pagoId);

        return $this->responder($this->consultas->comprobanteDe($pagoId));
    }

    public function emitirComprobante(Request $request, string $pagoId): JsonResponse
    {
        $d = $request->validate([
            'tipo' => 'nullable|string',
            'nit_cliente' => 'nullable|string|max:20',
            'razon_social' => 'nullable|string|max:150',
        ]);
        $this->exigirDuenoDelPago($request, $pagoId, soloCliente: true);

        return $this->responder(($this->emitirComprobante)(new EmitirComprobanteCommand(
            pagoId: $pagoId,
            tipo: $this->enum(TipoComprobante::class, $d['tipo'] ?? null) ?? TipoComprobante::RECIBO,
            nitCliente: $d['nit_cliente'] ?? null,
            razonSocial: $d['razon_social'] ?? null,
        )), 201);
    }

    /**
     * El total y lo que recibe el anfitrion se calculan en el dominio, no son
     * propiedades: se agregan para que quien consume no tenga que sumarlos.
     *
     * @return array<string, mixed>
     */
    private function conTotal(Pago $pago): array
    {
        return array_merge((array) Presentador::aArreglo($pago), [
            'total' => Presentador::aArreglo($pago->total()),
            'monto_anfitrion' => Presentador::aArreglo($pago->montoDelAnfitrion()),
        ]);
    }

    /**
     * El ADMIN ve todo; el cliente y el anfitrion del pago ven el suyo. Las
     * operaciones que mueven dinero (`soloCliente`) son del cliente o del ADMIN.
     */
    private function exigirDuenoDelPago(Request $request, string $pagoId, bool $soloCliente = false): DetalleDePago
    {
        $detalle = $this->consultas->detalle($pagoId);
        if ($this->esAdmin($request)) {
            return $detalle;
        }

        $yo = $this->sesion($request)->usuarioId;
        $esCliente = $detalle->pago->clienteId() === $yo;
        $esAnfitrion = ! $soloCliente && $detalle->pago->anfitrionId() === $yo;
        if (! $esCliente && ! $esAnfitrion) {
            throw new OperacionNoPermitida('Este pago no es tuyo');
        }

        return $detalle;
    }
}

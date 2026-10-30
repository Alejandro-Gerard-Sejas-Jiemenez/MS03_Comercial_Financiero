import { llamar } from '@/api/client'
import type { Cotizacion, DetallePago, Pago } from '@/types'

export const listarPagos = (como: 'cliente' | 'anfitrion') => llamar<Pago[]>(`/pagos?como=${como}`)

export const detallePago = (id: string) => llamar<DetallePago>(`/pagos/${id}`)

export interface DatosCotizacion {
  anfitrionId: string
  subtotalCentavos: number
  noches: number
  cupon?: string
}

/** La API aplica el cupon y el cargo de servicio y devuelve el desglose. */
export const cotizar = (d: DatosCotizacion) =>
  llamar<Cotizacion>('/pagos/cotizaciones', {
    metodo: 'POST',
    cuerpo: {
      anfitrion_id: d.anfitrionId,
      subtotal_centavos: d.subtotalCentavos,
      noches: d.noches,
      ...(d.cupon ? { codigo_cupon: d.cupon } : {}),
    },
  })

export interface DatosPago {
  reservaId: string
  anfitrionId: string
  metodo: string
  cotizacion: Cotizacion
}

/** Crea el pago con el mismo desglose cotizado y lo cobra. Devuelve el id del pago. */
export async function crearYCobrarPago(d: DatosPago): Promise<string> {
  const desglose = d.cotizacion.desglose
  const pago = await llamar<{ pago_id: string }>('/pagos', {
    metodo: 'POST',
    idempotente: true,
    cuerpo: {
      reserva_id: d.reservaId,
      anfitrion_id: d.anfitrionId,
      subtotal_centavos: desglose.subtotal.centavos,
      descuento_centavos: desglose.descuento.centavos,
      cargo_servicio_centavos: desglose.cargo_servicio.centavos,
      metodo: d.metodo,
      ...(d.cotizacion.promocion_id ? { promocion_id: d.cotizacion.promocion_id } : {}),
    },
  })
  await cobrarPago(pago.pago_id)
  return pago.pago_id
}

export const cobrarPago = (id: string) => llamar(`/pagos/${id}/cobro`, { metodo: 'POST', idempotente: true })

export const cancelarPago = (id: string) =>
  llamar(`/pagos/${id}/cancelacion`, { metodo: 'POST', cuerpo: { motivo: 'Cancelado por el cliente' } })

export const emitirComprobante = (id: string) =>
  llamar(`/pagos/${id}/comprobante`, { metodo: 'POST', cuerpo: { tipo: 'RECIBO' } })

export const pedirReembolso = (reservaId: string) =>
  llamar('/reembolsos', { metodo: 'POST', cuerpo: { reserva_id: reservaId, motivo: 'CANCELACION_CLIENTE' } })

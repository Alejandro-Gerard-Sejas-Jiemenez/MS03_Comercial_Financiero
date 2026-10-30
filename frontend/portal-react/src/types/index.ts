import type { Dinero } from '@/utils/formato'

export type { Dinero }

export interface Pago {
  pago_id: string
  reserva_id: string
  estado: string
  metodo: string
  subtotal: Dinero
  descuento: Dinero
  cargo_servicio: Dinero
}

export interface Cotizacion {
  desglose: { subtotal: Dinero; descuento: Dinero; cargo_servicio: Dinero; total: Dinero }
  promocion_id: string | null
}

export interface DetallePago {
  pago: Pago & { referencia_pasarela: string | null }
  transacciones: { transaccion_id: string; tipo: string; estado: string; monto: Dinero }[]
  reembolsos: { reembolso_id: string; estado: string; monto: Dinero }[]
  comprobante: { numero: string; tipo: string } | null
  total_reembolsado: Dinero
}

export interface Promocion {
  promocion_id: string
  nombre: string
  tipo: string
  tipo_descuento: string
  valor: string
  codigo: string | null
  estado: string
  vigente_desde: string
  vigente_hasta: string
  usos_actuales: number
  max_usos: number | null
}

export type EstadoEditable = 'ACTIVA' | 'PAUSADA' | 'CANCELADA'

export interface MetodoCobro {
  metodo_id: string
  tipo: string
  banco: string | null
  titular: string
  ultimos_digitos: string | null
  es_principal: boolean
  verificado: boolean
}

export interface Liquidacion {
  liquidacion_id: string
  estado: string
  periodo_desde: string
  periodo_hasta: string
  total_bruto: Dinero
  total_comisiones: Dinero
  total_neto: Dinero
}

import { llamar } from '@/api/client'
import type { EstadoEditable, Promocion } from '@/types'

export const listarPromociones = () => llamar<Promocion[]>('/promociones')

export interface DatosPromocion {
  nombre: string
  tipo: string
  tipoDescuento: string
  valor: string
  desde: string
  hasta: string
  codigo?: string
  maxUsos?: number
}

export const crearPromocion = (d: DatosPromocion) =>
  llamar('/promociones', {
    metodo: 'POST',
    cuerpo: {
      nombre: d.nombre,
      tipo: d.tipo,
      tipo_descuento: d.tipoDescuento,
      valor: d.valor,
      vigente_desde: d.desde,
      vigente_hasta: d.hasta,
      ...(d.codigo ? { codigo: d.codigo } : {}),
      ...(d.maxUsos ? { max_usos: d.maxUsos } : {}),
    },
  })

/** El estado viaja solo en el PUT: la API rechaza mezclarlo con otros campos. */
export const cambiarEstadoPromocion = (id: string, estado: EstadoEditable) =>
  llamar(`/promociones/${id}`, { metodo: 'PUT', cuerpo: { estado } })

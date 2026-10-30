import { llamar } from '@/api/client'
import type { MetodoCobro } from '@/types'

export const listarMetodosCobro = (anfitrionId: string) =>
  llamar<MetodoCobro[]>(`/anfitriones/${anfitrionId}/metodos-cobro`)

export interface DatosMetodoCobro {
  tipo: string
  titular: string
  numero: string
  banco?: string
}

/** El numero se envia una vez: la API lo guarda cifrado y solo devuelve los ultimos 4 digitos. */
export const registrarMetodoCobro = (anfitrionId: string, d: DatosMetodoCobro) =>
  llamar(`/anfitriones/${anfitrionId}/metodos-cobro`, {
    metodo: 'POST',
    cuerpo: { tipo: d.tipo, titular: d.titular, numero: d.numero, ...(d.banco ? { banco: d.banco } : {}) },
  })

export const hacerPrincipal = (metodoId: string) =>
  llamar(`/metodos-cobro/${metodoId}`, { metodo: 'PUT', cuerpo: { es_principal: true } })

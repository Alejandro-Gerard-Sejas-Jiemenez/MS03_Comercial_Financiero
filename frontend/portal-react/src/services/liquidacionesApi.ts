import { llamar } from '@/api/client'
import type { Dinero, Liquidacion } from '@/types'

export const saldoDelAnfitrion = (anfitrionId: string) => llamar<Dinero>(`/anfitriones/${anfitrionId}/saldo`)

export const listarLiquidaciones = (anfitrionId: string) =>
  llamar<Liquidacion[]>(`/anfitriones/${anfitrionId}/liquidaciones`)

/** Dinero como lo manda la API: `{centavos, moneda, monto}`. */
export interface Dinero {
  centavos: number
  moneda: string
  monto: string
}

export function dinero(d?: Dinero | null): string {
  if (!d) return '—'
  return `${d.moneda} ${(d.centavos / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

/** Bs ingresados por la persona ("1250.50") a centavos enteros, sin errores de coma flotante. */
export function aCentavos(texto: string): number {
  const limpio = texto.trim().replace(',', '.')
  if (!/^\d+(\.\d{1,2})?$/.test(limpio)) throw new Error('Monto invalido: use numeros con hasta 2 decimales')
  const [enteros, decimales = ''] = limpio.split('.')
  return Number(enteros) * 100 + Number(decimales.padEnd(2, '0'))
}

export function fecha(iso?: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString('es-BO')
}

/** El total no viene en los listados: se arma con subtotal - descuento + cargo de servicio. */
export function totalDe(p: { subtotal?: Dinero; descuento?: Dinero; cargo_servicio?: Dinero }): Dinero | undefined {
  if (!p.subtotal) return undefined
  const centavos = p.subtotal.centavos - (p.descuento?.centavos ?? 0) + (p.cargo_servicio?.centavos ?? 0)
  return { centavos, moneda: p.subtotal.moneda, monto: (centavos / 100).toFixed(2) }
}

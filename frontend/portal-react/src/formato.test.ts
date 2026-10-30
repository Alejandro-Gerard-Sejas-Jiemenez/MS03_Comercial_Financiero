import { describe, expect, it } from 'vitest'
import { aCentavos, dinero, totalDe } from './formato'

describe('dinero', () => {
  it('convierte Bs a centavos enteros sin errores de coma flotante', () => {
    expect(aCentavos('1250.50')).toBe(125050)
    expect(aCentavos('0,1')).toBe(10)
    expect(aCentavos('19.99')).toBe(1999)
    expect(aCentavos('1000')).toBe(100000)
  })

  it('rechaza montos invalidos', () => {
    expect(() => aCentavos('12.345')).toThrow()
    expect(() => aCentavos('-5')).toThrow()
    expect(() => aCentavos('abc')).toThrow()
  })

  it('arma el total de un pago de un listado: subtotal - descuento + cargo', () => {
    const m = (centavos: number) => ({ centavos, moneda: 'BOB', monto: (centavos / 100).toFixed(2) })
    expect(totalDe({ subtotal: m(100000), descuento: m(10000), cargo_servicio: m(5000) })?.centavos).toBe(95000)
    expect(dinero(undefined)).toBe('—')
  })
})

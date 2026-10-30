import { useState } from 'react'

/** Ejecuta una accion que llama a la API y guarda su resultado o su error. */
export function useAccion(alTerminar?: () => void) {
  const [error, setError] = useState<string>()
  const [exito, setExito] = useState<string>()
  const [ocupado, setOcupado] = useState(false)

  async function correr(textoExito: string, accion: () => Promise<unknown>) {
    setOcupado(true)
    setError(undefined)
    setExito(undefined)
    try {
      await accion()
      setExito(textoExito)
      alTerminar?.()
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setOcupado(false)
    }
  }
  return { error, exito, ocupado, correr }
}

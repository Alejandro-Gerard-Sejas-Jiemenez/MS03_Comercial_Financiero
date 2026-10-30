import { useCallback, useEffect, useState } from 'react'

export interface Carga<T> {
  datos: T | undefined
  cargando: boolean
  error: string | undefined
  recargar: () => void
}

/** Carga datos al montar y permite recargarlos tras una accion. */
export function useCarga<T>(buscar: () => Promise<T>, dependencias: unknown[] = []): Carga<T> {
  const [datos, setDatos] = useState<T>()
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState<string>()
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let vigente = true
    setCargando(true)
    buscar()
      .then((d) => {
        if (vigente) {
          setDatos(d)
          setError(undefined)
        }
      })
      .catch((e: Error) => vigente && setError(e.message))
      .finally(() => vigente && setCargando(false))
    return () => {
      vigente = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [version, ...dependencias])

  const recargar = useCallback(() => setVersion((v) => v + 1), [])
  return { datos, cargando, error, recargar }
}

import { useState, type ReactNode } from 'react'

export function Insignia({ valor }: { valor?: string }) {
  const v = valor ?? '—'
  const tono = /CAPTURADO|ACTIV|APROBAD|PAGAD|VERIFICAD|COMPLETAD/.test(v)
    ? 'ok'
    : /PENDIENTE|BORRADOR|GENERAD|SOLICITAD|PAUSAD|RETENID/.test(v)
      ? 'espera'
      : /FALLIDO|CANCEL|RECHAZ|ANULAD|VENCID/.test(v)
        ? 'mal'
        : 'neutro'
  return <span className={`insignia ${tono}`}>{v.replaceAll('_', ' ')}</span>
}

export function Aviso({ tipo, children }: { tipo: 'error' | 'ok'; children: ReactNode }) {
  return <p className={`aviso ${tipo}`}>{children}</p>
}

export function Estado({ cargando, error, vacio, children }: { cargando: boolean; error?: string; vacio?: boolean; children: ReactNode }) {
  if (cargando) return <p className="suave">Cargando…</p>
  if (error) return <Aviso tipo="error">{error}</Aviso>
  if (vacio) return <p className="suave">No hay nada para mostrar todavía.</p>
  return <>{children}</>
}

/** Ejecuta una accion que llama a la API y muestra su resultado o su error. */
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
